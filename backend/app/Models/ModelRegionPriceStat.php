<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 地域別中古相場（中央値）の事前計算行。
 * stats:regional-prices が投入し、モデルページの「地域別中古相場」表が読む。
 */
final class ModelRegionPriceStat extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'bike_model_id',
        'region_block',
        'median_price',
        'p10',
        'p90',
        'listing_count',
        'computed_at',
    ];

    protected $casts = [
        'median_price' => 'integer',
        'p10' => 'integer',
        'p90' => 'integer',
        'listing_count' => 'integer',
        'computed_at' => 'datetime',
    ];

    public function bikeModel(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class);
    }
}
