<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PushSubscription extends Model
{
    protected $fillable = [
        'user_id',
        'bike_model_id',
        'endpoint',
        'endpoint_hash',
        'p256dh',
        'auth',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $sub) {
            $sub->endpoint_hash = hash('sha256', $sub->endpoint);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function bikeModel(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class);
    }
}
