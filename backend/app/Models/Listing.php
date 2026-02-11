<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Listing extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'image_urls' => 'array',
        'local_image_paths' => 'array',
        'has_repair_history' => 'boolean',
        'is_sold_out' => 'boolean',
    ];

    /**
     * 画像URLリストを取得するアクセサ
     * ローカルに保存された画像があればそれを優先し、なければ外部URLを返します。
     */
    protected function images(): Attribute
    {
        return Attribute::make(
            get: function (mixed $value, array $attributes) {
                // 1. ローカル保存パスをチェック
                if (!empty($attributes['local_image_paths'])) {
                    $localPaths = json_decode($attributes['local_image_paths'], true);
                    if (is_array($localPaths) && !empty($localPaths)) {
                        return array_map(fn($path) => asset('storage/' . ltrim($path, '/')), $localPaths);
                    }
                }
                // 2. 外部URLをチェック
                if (!empty($attributes['image_urls'])) {
                    $remoteUrls = json_decode($attributes['image_urls'], true);
                    return is_array($remoteUrls) ? $remoteUrls : [];
                }
                return [];
            },
        );
    }

    /**
     * お買い得度を判定するスコア（0-100）
     * 事前に集計された市場統計テーブル(bike_model_market_stats)を使用して高速に計算します。
     */
    public function getBargainScoreAttribute(): float
    {
        $stats = $this->bikeModel?->marketStats;
        if (!$stats || $stats->avg_price <= 0 || !$this->total_price) {
            return 0;
        }
        // 平均価格より安ければプラスのスコアを返す
        $score = (($stats->avg_price - $this->total_price) / $stats->avg_price) * 100;
        return round(max(0, $score), 1);
    }

    // --- Relations ---

    public function bikeModel(): BelongsTo { return $this->belongsTo(BikeModel::class); }
    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function site(): BelongsTo { return $this->belongsTo(Site::class); }

    // --- Query Scopes (高速化版) ---

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('listings.is_sold_out', false);
    }

    /**
     * キーワード検索 (JOIN方式で高速化)
     * whereHas を廃止し、テーブル結合を利用してインデックスを効かせます。
     */
    public function scopeWithKeyword(Builder $query, ?string $keyword): Builder
    {
        if (!$keyword) return $query;

        // すでに結合されているかチェックして多重結合を防ぐ
        $joins = collect($query->getQuery()->joins)->pluck('table');
        if (!$joins->contains('bike_models')) {
            $query->leftJoin('bike_models', 'listings.bike_model_id', '=', 'bike_models.id');
        }
        if (!$joins->contains('manufacturers')) {
            $query->leftJoin('manufacturers', 'bike_models.manufacturer_id', '=', 'manufacturers.id');
        }

        return $query->where(function (Builder $q) use ($keyword) {
            $q->where('listings.title', 'like', "%{$keyword}%")
              ->orWhere('bike_models.name', 'like', "%{$keyword}%")
              ->orWhere('manufacturers.name', 'like', "%{$keyword}%");
        });
    }

    /**
     * 都道府県検索 (JOIN方式)
     */
    public function scopeByPrefecture(Builder $query, ?string $prefecture): Builder
    {
        if (!$prefecture) return $query;
        
        if (!collect($query->getQuery()->joins)->pluck('table')->contains('shops')) {
            $query->join('shops', 'listings.shop_id', '=', 'shops.id');
        }
        
        return $query->where('shops.prefecture', 'like', "{$prefecture}%");
    }

    /**
     * メーカー・車種IDでの絞り込み ( denormalized column 使用)
     */
    public function scopeByModel(Builder $query, ?int $manufacturerId, ?int $bikeModelId): Builder
    {
        if ($bikeModelId) {
            $query->where('listings.bike_model_id', $bikeModelId);
        } elseif ($manufacturerId) {
            $query->where('listings.manufacturer_id', $manufacturerId);
        }
        return $query;
    }

    /**
     * カテゴリーIDでの絞り込み ( denormalized column 使用)
     */
    public function scopeByCategory(Builder $query, ?int $categoryId): Builder
    {
        if (!$categoryId) return $query;
        return $query->where('listings.category_id', $categoryId);
    }

    public function scopeByCondition(Builder $query, $isNew): Builder
    {
        if ($isNew === null || $isNew === '') return $query;
        $value = ($isNew === '1' || $isNew === true) ? '新車' : '中古車';
        return $query->where('listings.condition', $value);
    }

    public function scopeByRepairHistory(Builder $query, $hasRepair): Builder
    {
        if ($hasRepair === null || $hasRepair === '') return $query;
        return $query->where('listings.has_repair_history', (bool)$hasRepair);
    }

    public function scopePriceBetween(Builder $query, ?int $min, ?int $max, ?int $uiMaxLimit = null): Builder
    {
        if ($min > 0) $query->where('listings.total_price', '>=', $min * 10000);
        if ($max && ($uiMaxLimit === null || $max < $uiMaxLimit)) {
            $query->where('listings.total_price', '<=', $max * 10000);
        }
        return $query;
    }

    public function scopeMileageBetween(Builder $query, ?int $min, ?int $max, ?int $uiMaxLimit = null): Builder
    {
        if ($min > 0) $query->where('listings.mileage', '>=', $min);
        if ($max && ($uiMaxLimit === null || $max < $uiMaxLimit)) {
            $query->where('listings.mileage', '<=', $max);
        }
        return $query;
    }

    public function scopeYearBetween(Builder $query, ?int $min, ?int $max): Builder
    {
        if ($min) $query->where('listings.model_year', '>=', $min);
        if ($max) $query->where('listings.model_year', '<=', $max);
        return $query;
    }

    /**
     * 排気量範囲 ( denormalized column 使用)
     */
    public function scopeDisplacementBetween(Builder $query, ?int $min, ?int $max): Builder
    {
        if (!$min && !$max) return $query;

        if ($min) $query->where('listings.displacement', '>=', $min);
        if ($max) $query->where('listings.displacement', '<=', $max);
        
        return $query;
    }
}