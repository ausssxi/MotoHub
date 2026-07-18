<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\ShopNameNormalizer;
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
