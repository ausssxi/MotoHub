<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PurgesReportsOnDelete;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 車種Q&A の回答（質問にぶら下がる）。
 */
final class ModelAnswer extends Model
{
    use PurgesReportsOnDelete;

    protected $fillable = [
        'model_question_id', 'user_id', 'nickname', 'body', 'submitter_ip_hash', 'is_approved', 'helpful_count',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'helpful_count' => 'integer',
        'answer_pushed_at' => 'datetime',
    ];

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ModelQuestion::class, 'model_question_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 公開表示名。本名は出さない。 */
    public function getDisplayNameAttribute(): string
    {
        if ($this->user_id) {
            return $this->user?->review_display_name ?? '名無しライダー';
        }

        return $this->nickname ?: '名無しライダー';
    }
}
