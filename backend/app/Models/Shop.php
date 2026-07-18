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
     * 店名からチェーンslugを解決する（config/bike.php の pattern＝チェーン横断ページと同一判定）。
     * 非チェーン店は null。マップのチェーン別ピン・チェーン横断導線で共用。
     */
    public static function chainSlug(?string $name): ?string
    {
        if ($name === null || $name === '') {
            return null;
        }
        foreach (config('bike.chains', []) as $slug => $chain) {
            if (! empty($chain['pattern']) && str_contains($name, $chain['pattern'])) {
                return $slug;
            }
        }

        return null;
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
