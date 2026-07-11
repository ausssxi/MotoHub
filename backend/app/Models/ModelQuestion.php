<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 車種Q&A の質問（購入検討の疑問・相談）。レビュー（乗った感想）とは役割が異なる。
 */
final class ModelQuestion extends Model
{
    protected $fillable = [
        'bike_model_id', 'user_id', 'nickname', 'title', 'body', 'submitter_ip_hash', 'is_approved',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
    ];

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('is_approved', true);
    }

    public function bikeModel(): BelongsTo
    {
        return $this->belongsTo(BikeModel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(ModelAnswer::class);
    }

    /** 公開回答のみ（キルスイッチ考慮）。 */
    public function approvedAnswers(): HasMany
    {
        return $this->hasMany(ModelAnswer::class)->where('is_approved', true);
    }

    /** 「回答が付いたら通知」購読（ログイン不要・匿名はendpoint_hashで識別）。 */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushQuestionSubscription::class);
    }

    /**
     * 公開表示名。★本名(user->name)は絶対に出さない。
     * ログインは公開ハンドル、ゲストは入力 nickname、未設定は「名無しライダー」。
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->user_id) {
            return $this->user?->review_display_name ?? '名無しライダー';
        }

        return $this->nickname ?: '名無しライダー';
    }
}
