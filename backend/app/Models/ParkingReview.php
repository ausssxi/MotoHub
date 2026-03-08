<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ParkingReview extends Model
{
    protected $fillable = [
        'bike_parking_id',
        'user_id',
        'nickname',
        'rating',
        'body',
        'visited_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'visited_at' => 'date',
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
