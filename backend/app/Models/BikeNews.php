<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class BikeNews extends Model
{
    protected $table = 'bike_news';

    protected $fillable = [
        'title',
        'url',
        'source',
        'content',
        'thumbnail_url',
        'published_at',
        'bike_model_id',
        'manufacturer_id',
        'comments_count',
        'picks_count',
        'is_featured',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'comments_count' => 'integer',
        'picks_count' => 'integer',
        'is_featured' => 'boolean',
    ];

    public function bikeModel(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class);
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(NewsComment::class, 'news_id');
    }

    public function picks(): HasMany
    {
        return $this->hasMany(NewsPick::class, 'news_id');
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('published_at');
    }
}
