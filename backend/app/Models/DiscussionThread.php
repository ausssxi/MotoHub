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

    /** 質問以外(chat/custom/maintenance)は casual＝必答なし・「返信0件」の過疎表示を出さない。 */
    public function getIsCasualAttribute(): bool
    {
        return $this->type !== 'question';
    }

    /** 種別バッジのラベル。casual は「ひとこと」で質問バッジと視覚的に区別する。 */
    public function getTypeBadgeLabelAttribute(): string
    {
        return match ($this->type) {
            'question' => '質問',
            'custom' => 'カスタム',
            'maintenance' => '整備',
            default => 'ひとこと', // chat
        };
    }

    /** 一覧・見出し用のタイトル。title 無し(casual)は本文先頭で見せ、空H1/空リンクを避ける。 */
    public function getDisplayTitleAttribute(): string
    {
        if (filled($this->title)) {
            return $this->title;
        }

        return \Illuminate\Support\Str::limit((string) $this->body, 40) ?: 'ひとこと';
    }
}
