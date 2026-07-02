<?php

declare(strict_types=1);

namespace App\Models;

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
