<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class NewsComment extends Model
{
    protected $fillable = [
        'news_id',
        'user_id',
        'nickname',
        'submitter_ip_hash',
        'body',
        'is_approved',
        'likes_count',
    ];

    protected $casts = [
        'likes_count' => 'integer',
        'is_approved' => 'boolean',
    ];

    /** 公開状態（即反映＝true・通報対応のキルスイッチで false 化）。 */
    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    /**
     * 公開表示名。★本名(user->name)は絶対に出さない。
     * ログイン投稿は公開ハンドル(review_display_name)、ゲストは入力 nickname、未設定は「名無しライダー」。
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->user_id) {
            return $this->user?->review_display_name ?? '名無しライダー';
        }

        return $this->nickname ?: '名無しライダー';
    }

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
