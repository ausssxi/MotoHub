<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketPriceLog extends Model
{
    protected $guarded = ['id'];
    
    protected $casts = [
        'recorded_at' => 'date',
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