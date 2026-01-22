<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Listing extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'image_urls' => 'array',
        'local_image_paths' => 'array',
        'has_repair_history' => 'boolean',
        'is_sold_out' => 'boolean',
    ];

    // --- Relations ---

    public function bikeModel(): BelongsTo { return $this->belongsTo(BikeModel::class); }
    public function shop(): BelongsTo { return $this->belongsTo(Shop::class); }
    public function site(): BelongsTo { return $this->belongsTo(Site::class); }

    // --- Query Scopes (現状のロジックをそのまま移行) ---

    /**
     * 販売中の車両のみ
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_sold_out', false);
    }

    /**
     * キーワード検索（車種ID指定時は衝突を避ける仕様を維持）
     */
    public function scopeWithKeyword(Builder $query, ?string $keyword, bool $excludeModelSearch = false): Builder
    {
        if (!$keyword) return $query;

        return $query->where(function (Builder $q) use ($keyword, $excludeModelSearch) {
            $q->where('title', 'like', "%{$keyword}%");

            if (!$excludeModelSearch) {
                $q->orWhereHas('bikeModel', function (Builder $bq) use ($keyword) {
                    $bq->where('name', 'like', "%{$keyword}%")
                      ->orWhereHas('manufacturer', function (Builder $mq) use ($keyword) {
                          $mq->where('name', 'like', "%{$keyword}%");
                      });
                });
            }
        });
    }

    /**
     * 都道府県検索
     */
    public function scopeByPrefecture(Builder $query, ?string $prefecture): Builder
    {
        if (!$prefecture) return $query;
        return $query->whereHas('shop', fn($sq) => $sq->where('prefecture', 'like', "{$prefecture}%"));
    }

    /**
     * メーカー・車種IDでの絞り込み
     */
    public function scopeByModel(Builder $query, ?int $manufacturerId, ?int $bikeModelId): Builder
    {
        if ($bikeModelId) {
            $query->where('bike_model_id', $bikeModelId);
        } elseif ($manufacturerId) {
            $query->whereHas('bikeModel', fn($q) => $q->where('manufacturer_id', $manufacturerId));
        }
        return $query;
    }

    /**
     * コンディション（is_new パラメータを condition カラムに読み替え）
     */
    public function scopeByCondition(Builder $query, $isNew): Builder
    {
        if ($isNew === null || $isNew === '') return $query;
        $value = ($isNew === '1' || $isNew === true) ? '新車' : '中古車';
        return $query->where('condition', $value);
    }

    /**
     * 修復歴
     */
    public function scopeByRepairHistory(Builder $query, $hasRepair): Builder
    {
        if ($hasRepair === null || $hasRepair === '') return $query;
        return $query->where('has_repair_history', (bool)$hasRepair);
    }

    /**
     * 価格範囲
     */
    public function scopePriceBetween(Builder $query, ?int $min, ?int $max, int $uiMaxLimit): Builder
    {
        if ($min > 0) $query->where('total_price', '>=', $min * 10000);
        if ($max && $max < $uiMaxLimit) $query->where('total_price', '<=', $max * 10000);
        return $query;
    }

    /**
     * 走行距離範囲
     */
    public function scopeMileageBetween(Builder $query, ?int $min, ?int $max, int $uiMaxLimit): Builder
    {
        if ($min > 0) $query->where('mileage', '>=', $min);
        if ($max && $max < $uiMaxLimit) $query->where('mileage', '<=', $max);
        return $query;
    }

    /**
     * 年式範囲
     */
    public function scopeYearBetween(Builder $query, ?int $min, ?int $max): Builder
    {
        if ($min) $query->where('model_year', '>=', $min);
        if ($max) $query->where('model_year', '<=', $max);
        return $query;
    }
}