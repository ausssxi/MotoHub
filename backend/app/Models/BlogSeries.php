<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BlogSeries extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'eyecatch_image',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (BlogSeries $series) {
            if (empty($series->slug)) {
                $series->slug = Str::slug($series->title) ?: Str::lower(Str::random(14));
            }
        });
    }

    public function posts(): HasMany
    {
        return $this->hasMany(BlogPost::class, 'series_id')->orderBy('series_order');
    }

    public function publishedPosts(): HasMany
    {
        return $this->posts()->where('status', 'published')->where('published_at', '<=', now());
    }
}
