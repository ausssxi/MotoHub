<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PurgesReportsOnDelete;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * スレッドへの返信。is_official=MotoHub公式（AI必答含む・人を装わず明示ラベル）。
 * answer_generated_at=null かつ is_official は「回答準備中」プレースホルダ（返信0の見た目にしない）。
 */
final class DiscussionReply extends Model
{
    use PurgesReportsOnDelete;

    protected $fillable = [
        'discussion_thread_id', 'user_id', 'nickname', 'body', 'submitter_ip_hash',
        'is_official', 'source', 'status', 'answer_generated_at', 'helpful_count',
    ];

    protected $casts = [
        'is_official' => 'boolean',
        'helpful_count' => 'integer',
        'answer_generated_at' => 'datetime',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(DiscussionThread::class, 'discussion_thread_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ThreadReplyVote::class);
    }

    /** MotoHub必答の生成待ち（プレースホルダ）か。 */
    public function isPending(): bool
    {
        return $this->is_official && $this->answer_generated_at === null;
    }

    /** 公開表示名。公式は「MotoHub」、本名は出さない。 */
    public function getDisplayNameAttribute(): string
    {
        if ($this->is_official) {
            return 'MotoHub';
        }
        if ($this->user_id) {
            return $this->user?->review_display_name ?? '名無しライダー';
        }

        return $this->nickname ?: '名無しライダー';
    }
}
