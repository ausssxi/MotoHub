<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 車種Q&A「回答が付いたら通知」購読（購読 × 質問）。
 * endpoint_hash は保存時に自動計算（既存 PushSubscription と同じ作法）。
 */
final class PushQuestionSubscription extends Model
{
    protected $fillable = [
        'model_question_id', 'user_id', 'endpoint', 'endpoint_hash', 'p256dh', 'auth',
    ];

    protected static function booted(): void
    {
        self::saving(function (self $sub): void {
            $sub->endpoint_hash = hash('sha256', (string) $sub->endpoint);
        });
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ModelQuestion::class, 'model_question_id');
    }
}
