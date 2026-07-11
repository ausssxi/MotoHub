<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PurgesReportsOnDelete;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ParkingReview extends Model
{
    use PurgesReportsOnDelete;

    protected $fillable = [
        'bike_parking_id',
        'user_id',
        'nickname',
        'submitter_ip_hash',
        'is_approved',
        'rating',
        'body',
        'visited_at',
    ];

    protected $casts = [
        'rating' => 'integer',
        'visited_at' => 'date',
        'is_approved' => 'boolean',
    ];

    /** 公開状態（即反映＝true・通報対応や新規ガードで false 化）。 */
    public function scopeApproved(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('is_approved', true);
    }

    public function bikeParking(): BelongsTo
    {
        return $this->belongsTo(BikeParking::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 公開表示名。★本名(user->name)は絶対に出さない。
     * ログイン投稿(user_id 有り)は公開ハンドル(review_display_name)を優先（保存済み nickname は本名が
     * 混入しうるため使わない）。ゲスト投稿は自由入力 nickname を表示。未設定は「名無しライダー」。
     * ※既存データを書き換えずに表示側で本名露出を止めるフォールバック。
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->user_id) {
            return $this->user?->review_display_name ?? '名無しライダー';
        }

        return $this->nickname ?: '名無しライダー';
    }
}
