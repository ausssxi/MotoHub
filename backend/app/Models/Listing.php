<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Listing extends Model
{
    protected $casts = [
        'image_urls' => 'array',
        'price' => 'decimal:0',
        'total_price' => 'decimal:0',
        'is_sold_out' => 'boolean',
    ];

    public function bikeModel(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}