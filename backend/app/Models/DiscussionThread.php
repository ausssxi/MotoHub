<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PurgesReportsOnDelete;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 車種ページの統合スレッド型クチコミ（質問/雑談/カスタム/整備）。
 * 質問(type=question)には MotoHub必答（公式AI回答）が即時に付く＝返信0を構造的にゼロにする。
 */
final class DiscussionThread extends Model
{
    use PurgesReportsOnDelete;

    public const TYPES = ['question', 'chat', 'custom', 'maintenance'];

    protected $fillable = [
        'bike_model_id', 'user_id', 'type', 'nickname', 'title', 'body',
        'submitter_ip_hash', 'status', 'is_seed',
    ];

    protected $casts = [
        'is_seed' => 'boolean',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function bikeModel(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replies(): HasMany
    {
        return $this->hasMany(DiscussionReply::class);
    }

    /** 公開返信のみ（キルスイッチ考慮）。 */
    public function publishedReplies(): HasMany
    {
        return $this->hasMany(DiscussionReply::class)->where('status', 'published');
    }

    /** MotoHub必答（公式・1スレ1件想定）。 */
    public function officialReply(): HasMany
    {
        return $this->hasMany(DiscussionReply::class)->where('is_official', true);
    }

    /** 「返信が付いたら通知」購読（匿名はendpoint_hashで識別）。 */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(ThreadPushSubscription::class);
    }

    /** 公開表示名。本名(user->name)は出さない。 */
    public function getDisplayNameAttribute(): string
    {
        if ($this->user_id) {
            return $this->user?->review_display_name ?? '名無しライダー';
        }

        return $this->nickname ?: '名無しライダー';
    }
}
