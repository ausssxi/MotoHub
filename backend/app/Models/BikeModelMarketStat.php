<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BikeModelMarketStat extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'distribution_data' => 'array',
        'avg_price' => 'integer',
        'min_price' => 'integer',
        'max_price' => 'integer',
        'listing_count' => 'integer',
    ];

    public function bikeModel(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class);
    }
}