<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\PurgesReportsOnDelete;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 公開ガレージへの社交コメント（会員限定）。通報対象（polymorphic）＝削除時に reports を purge。
 */
final class GarageComment extends Model
{
    use PurgesReportsOnDelete;

    protected $fillable = [
        'my_bike_id', 'user_id', 'body', 'status',
    ];

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function myBike(): BelongsTo
    {
        return $this->belongsTo(MyBike::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 公開表示名。本名(user->name)は出さない。 */
    public function getDisplayNameAttribute(): string
    {
        return $this->user?->review_display_name ?? '名無しライダー';
    }
}
