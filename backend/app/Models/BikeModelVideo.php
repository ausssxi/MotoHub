<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class BikeModelVideo extends Model
{
    protected $fillable = [
        'bike_model_id',
        'video_id',
        'title',
        'thumbnail_url',
        'channel_name',
        'published_at',
        'sort_order',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'sort_order' => 'integer',
    ];

    public function bikeModel(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class);
    }
}
