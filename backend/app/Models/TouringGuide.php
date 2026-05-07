<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class TouringGuide extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'author_id',
        'title',
        'slug',
        'body',
        'excerpt',
        'latitude',
        'longitude',
        'zoom_level',
        'prefecture',
        'difficulty',
        'distance_km',
        'duration_text',
        'best_season',
        'status',
        'reading_time_minutes',
        'published_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'reading_time_minutes' => 'integer',
        'distance_km' => 'integer',
        'zoom_level' => 'integer',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    protected static function booted(): void
    {
        static::creating(function (TouringGuide $guide) {
            if (empty($guide->slug)) {
                $guide->slug = Str::lower(Str::random(14));
            }

            $guide->reading_time_minutes = max(1, (int) ceil(mb_strlen(strip_tags($guide->body)) / 500));

            if (empty($guide->excerpt) && !empty($guide->body)) {
                $guide->excerpt = Str::limit(BlogPost::stripMarkdown($guide->body), 150);
            }
        });

        static::updating(function (TouringGuide $guide) {
            if ($guide->isDirty('body')) {
                $guide->reading_time_minutes = max(1, (int) ceil(mb_strlen(strip_tags($guide->body)) / 500));

                if (empty($guide->excerpt)) {
                    $guide->excerpt = Str::limit(BlogPost::stripMarkdown($guide->body), 150);
                }
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
