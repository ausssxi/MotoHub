<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BikeParkingImage extends Model
{
    protected $fillable = [
        'bike_parking_id',
        'image_path',
        'user_id',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function bikeParking(): BelongsTo
    {
        return $this->belongsTo(BikeParking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
