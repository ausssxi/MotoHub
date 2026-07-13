<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 統合スレッド「返信が付いたら通知」購読（購読 × スレッド）。
 * endpoint_hash は保存時に自動計算（既存 PushQuestionSubscription と同じ作法）。
 */
final class ThreadPushSubscription extends Model
{
    protected $fillable = [
        'discussion_thread_id', 'user_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $sub): void {
            $sub->endpoint_hash = hash('sha256', (string) $sub->endpoint);
        });
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(DiscussionThread::class, 'discussion_thread_id');
    }
}
