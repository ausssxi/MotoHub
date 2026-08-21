<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\ShopNameNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 販売店モデル
 */
final class Shop extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'service_tags' => 'array',
    ];

    /** データ由来: スクレイパー自動収集 / ユーザー投稿→承認 */
    public const SOURCE_SCRAPER = 'scraper';

    public const SOURCE_USER = 'user';

    /**
     * 掲載停止対象の画像配信ホスト（image_url に対する部分一致キーワード）。
     *
     * webike-cdn: 権利者・株式会社リバークレイン（ウェビック）より 2026-08-10 付で
     * 「取得済の画像も含めた掲載の停止」を要請され承諾。Listing::IMAGE_SUPPRESSED_SITE_IDS と同思想で、
     * DB の値は消さず表示アクセサでのみ隠す。
     * ※ image_url を消すと display_image_url が local_image_path（先方画像のローカルコピー）を
     *   出し続け、逆に local_image_path だけ消すと image_url（先方CDN直リンク）が生き残るため、両方同時に抑止する。
     *
     * @var array<int, string>
     */
    public const SUPPRESSED_IMAGE_HOST_KEYWORDS = ['webike-cdn'];

    /**
     * ユーザー投稿画像の公開ディレクトリ接頭辞（ShopSubmissionImageService::PUBLIC_DIR と一致）。
     * この配下のローカル画像は先方由来ではないため、掲載停止でも抑止しない。
     */
    private const USER_IMAGE_PATH_PREFIX = 'shop-user/';

    /**
     * 与えた画像URLが掲載停止ホスト由来か（大文字小文字は無視）。
     */
    public static function imageSourceIsSuppressed(?string $url): bool
    {
        if ($url === null || $url === '') {
            return false;
        }
        $needle = strtolower($url);
        foreach (self::SUPPRESSED_IMAGE_HOST_KEYWORDS as $keyword) {
            if ($keyword !== '' && str_contains($needle, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }

    /**
     * この店舗の画像（image_url / local_image_path / display_image_url）を表示上すべて隠すべきか。
     * ※ アクセサ経由で読むと無限再帰になるため、判定は必ず生値（getRawOriginal）で行う。
     */
    public function imagesAreSuppressed(): bool
    {
        return self::imageSourceIsSuppressed($this->getRawOriginal('image_url'));
    }

    /**
     * 店名からチェーンslugを解決する（config/bike.php の pattern/patterns＝チェーン横断ページと同一判定）。
     * 表記ゆれ吸収: 全角→半角・小文字化・空白除去で正規化してから部分一致（REVERSE AUTO/全角SBS 等）。
     * 非チェーン店は null。マップのチェーン別ピン・チェーン横断導線で共用。
     */
    public static function chainSlug(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }
        $n = self::normalizeForMatch($name);
        foreach (config('bike.chains', []) as $slug => $chain) {
            $patterns = $chain['patterns'] ?? (isset($chain['pattern']) ? [$chain['pattern']] : []);
            foreach ($patterns as $p) {
                if ($p !== '' && str_contains($n, self::normalizeForMatch($p))) {
                    return $slug;
                }
            }
        }

        return null;
    }

    /**
     * 指定チェーン設定（config/bike.php の1エントリ）の pattern/patterns による店名の LIKE 部分一致で絞り込む。
     * チェーン横断ページ（ShopController::chainShow）とブログ用ショートコードで判定条件を共有するためのスコープ。
     *
     * @param  array<string, mixed>  $chain
     */
    public function scopeOfChain(Builder $query, array $chain): Builder
    {
        $patterns = $chain['patterns'] ?? (isset($chain['pattern']) ? [$chain['pattern']] : []);

        return $query->where(function ($q) use ($patterns) {
            foreach ($patterns as $p) {
                if ($p !== '') {
                    $q->orWhere('name', 'like', '%'.$p.'%');
                }
            }
        });
    }

    /**
     * メーカー正規ディーラー判定。service_tags の「◯◯正規店」バッジ（Webike由来）から
     * メーカー名を抽出→正規化→ config('bike.maker_dealer_brands') の輸入ブランド allowlist に
     * 一致すれば「ブランドキー」を返す（例: harley）。map.js は返ったキーで色/ラベルを引く。
     * ★国産(HONDA/SUZUKI/YAMAHA/KAWASAKI)・「その他正規店」・未知は allowlist 非該当＝null（＝地図で「その他」）。
     * ★チェーン該当店は二重分類しない（$chainSlug!==null なら null）。"個人店"とは断定しない。
     */
    public static function makerDealer(mixed $serviceTags, ?string $chainSlug): ?string
    {
        if ($chainSlug !== null || ! is_array($serviceTags)) {
            return null;
        }
        $brands = config('bike.maker_dealer_brands', []);
        foreach ($serviceTags as $tag) {
            $tag = (string) $tag;
            if (! str_ends_with($tag, '正規店')) {
                continue;
            }
            // 「◯◯正規店」→ メーカー名を正規化。国産/「その他」/未知は allowlist 非該当＝スキップ。
            // マルチブランド店は次のタグを見て輸入ブランドを拾う（国産→スキップ→輸入拾い）。
            $maker = self::normalizeForMatch((string) preg_replace('/正規店$/u', '', $tag));
            foreach ($brands as $key => $aliases) {
                foreach ($aliases as $alias) {
                    if ($alias !== '' && str_contains($maker, self::normalizeForMatch($alias))) {
                        return $key; // 輸入ブランドキー
                    }
                }
            }
        }

        return null;
    }

    /** 店名/メーカー照合用の正規化: 全角英数→半角・半角カナ→全角カナ・濁点合成・小文字化・空白/中黒除去。 */
    private static function normalizeForMatch(string $s): string
    {
        return mb_strtolower(str_replace(['　', ' ', '・', '･'], '', mb_convert_kana($s, 'aKVs')));
    }

    /**
     * ユーザー投稿由来の「新規店」か。新規店は誤情報防止のため口コミコメントも
     * 承認へ回す（即反映しない）。既存スクレイパー店は常に false。
     * 基準日数は config('shop.new_user_shop_days')。
     */
    public function isNewUserShop(): bool
    {
        if ($this->source !== self::SOURCE_USER) {
            return false;
        }

        $days = (int) config('shop.new_user_shop_days', 14);
        if ($days <= 0 || $this->created_at === null) {
            return false;
        }

        return $this->created_at->greaterThanOrEqualTo(now()->subDays($days));
    }

    /**
     * name 保存時に name_normalized を自動セット（Eloquent全経路で共通）。
     * user投稿・承認フロー・管理画面からの変更は常にこれで最新化される。
     * ※ スクレイパーはSQLAlchemy(Eloquent非経由)のため NULL で入り、
     *   shops:normalize-names コマンド（日次）でバックフィルする。
     */
    protected function name(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => [
                'name' => $value,
                'name_normalized' => ShopNameNormalizer::normalize($value),
            ],
        );
    }

    /**
     * city 正規化: 半角・全角スペースを除去して保存（Eloquent全経路で共通）。
     * /shops/repair/{pref}/{city} のバケットキーの表記揺れ（「横浜市 都筑区」等）を構造的に防ぐ。
     * ※ スクレイパーはSQLAlchemy(Eloquent非経由)のため影響なし。
     */
    protected function city(): Attribute
    {
        return Attribute::make(
            set: fn (?string $value) => $value === null ? null : preg_replace('/[\s\x{3000}]+/u', '', $value),
        );
    }

    /**
     * 表示用の画像URLを取得するアクセサ (モダン記法)
     * 呼び出し方: $shop->display_image_url
     */
    protected function displayImageUrl(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                // 0. 掲載停止店舗（webike-cdn 由来）は画像なし扱い。1・2 を塞げば結果的に null だが、
                //    意図を明示するため冒頭で early return する。
                if (self::imageSourceIsSuppressed($attributes['image_url'] ?? null)) {
                    return null;
                }

                // 1. ローカル保存された画像がある場合
                if (! empty($attributes['local_image_path'])) {
                    // storage/shops/... の形式にして返す
                    return asset('storage/'.ltrim($attributes['local_image_path'], '/'));
                }

                // 2. 外部URLがある場合
                if (! empty($attributes['image_url'])) {
                    return $attributes['image_url'];
                }

                // 3. 画像がない場合
                return null;
            },
        );
    }

    /**
     * image_url（外部CDN直リンク）。掲載停止ホスト由来なら null を返し、直リンク表示・直リクエストを止める。
     * ※ 判定・返却とも $attributes['image_url']（生値）を使い、$this->image_url を参照しない（無限再帰防止）。
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $raw = $attributes['image_url'] ?? null;

                return self::imageSourceIsSuppressed($raw) ? null : $raw;
            },
        );
    }

    /**
     * local_image_path（ローカル保存画像の相対パス）。掲載停止店舗なら null を返す。
     * ただし 'shop-user/' 配下はユーザー投稿画像で先方由来ではないため抑止しない
     *（webike-cdn 店舗にユーザー投稿画像は付かない設計だが、巻き込み事故を防ぐ保険）。
     * ※ 物理ファイル削除など生値が要る経路は getRawOriginal('local_image_path') を使うこと。
     */
    protected function localImagePath(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                $raw = $attributes['local_image_path'] ?? null;

                if ($raw !== null && str_starts_with((string) $raw, self::USER_IMAGE_PATH_PREFIX)) {
                    return $raw;
                }

                return self::imageSourceIsSuppressed($attributes['image_url'] ?? null) ? null : $raw;
            },
        );
    }

    /**
     * この店舗が出品している車両を取得
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * 各サイト別の店舗識別番号を取得
     */
    public function identifiers(): HasMany
    {
        return $this->hasMany(ShopIdentifier::class);
    }
}
