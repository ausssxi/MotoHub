<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class NewsComment extends Model
{
    protected $fillable = [
        'news_id',
        'user_id',
        'body',
        'likes_count',
    ];

    protected $casts = [
        'likes_count' => 'integer',
    ];

    public function news(): BelongsTo
    {
        return $this->belongsTo(BikeNews::class, 'news_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likes(): HasMany
    {
        return $this->hasMany(NewsCommentLike::class, 'comment_id');
    }
}
