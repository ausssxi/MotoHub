<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 車種マスタモデル
 */
final class BikeModel extends Model
{
    /**
     * 複数代入可能な属性
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'local_image_path',
        'displacement',
        'manufacturer_id',
        'category_id',
        'slug',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'displacement' => 'integer',
        'local_image_path' => 'array',
    ];

    /**
     * 画像のフルURLを $bike->image_url で取得できるようにする
     * 実際の掲載車両（Listing）の画像を優先し、なければ local_image_path にフォールバック
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                // 1. 代表Listingの画像を優先（実際の掲載車両なので正確）
                $listing = $this->relationLoaded('representativeListing')
                    ? $this->representativeListing
                    : $this->representativeListing()->first();

                if ($listing) {
                    $images = $listing->images;
                    if (!empty($images)) {
                        return $images[0];
                    }
                }

                // 2. BikeModel自身の画像にフォールバック
                if (is_array($this->local_image_path) && !empty($this->local_image_path)) {
                    $path = ltrim($this->local_image_path[0], '/');
                    return asset('storage/' . $path);
                }

                return null;
            },
        );
    }

    /**
     * 代表となる1件のアクティブなListingを取得（画像フォールバック用）
     * ofMany で効率的なサブクエリJOINを生成（全Listingをロードしない）
     */
    public function representativeListing(): HasOne
    {
        return $this->hasOne(Listing::class)->ofMany(
            ['id' => 'max'],
            fn(Builder $q) => $q->where('is_sold_out', false)
        );
    }

    /**
     * 所属するカテゴリを取得
     */
    public function categoryData(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * 所属するメーカーを取得
     */
    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    /**
     * この車種に関連する出品情報を取得
     */
    public function listings(): HasMany
    {
        return $this->hasMany(Listing::class);
    }

    /**
     * 各サイト別の識別番号を取得
     */
    public function identifiers(): HasMany
    {
        return $this->hasMany(BikeModelIdentifier::class);
    }

    /**
     * レビューとのリレーション
     * 承認済み(is_approved=true)のレビューのみを新しい順で取得
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class)
                    ->where('is_approved', true)
                    ->orderBy('created_at', 'desc');
    }

    public function marketStats(): HasOne
    {
        return $this->hasOne(BikeModelMarketStat::class);
    }

    /**
     * 価格推移ログとのリレーション
     */
    public function priceLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MarketPriceLog::class)->orderBy('recorded_at', 'asc');
    }

    /**
     * SEOフレンドリーなURLを生成
     * スラッグがあれば /bikes/honda/cb400sf
     * なければ /bikes/honda/123 (IDフォールバック)
     */
    public function getSeoUrlAttribute(): string
    {
        $mfrSlug = $this->manufacturer?->slug;
        $modelSlug = $this->slug ?? $this->id;

        if ($mfrSlug) {
            return "/bikes/{$mfrSlug}/{$modelSlug}";
        }

        // メーカースラッグもない場合（レアケース）
        return "/bikes/model/{$this->id}";
    }
}