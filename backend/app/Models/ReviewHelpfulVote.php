<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * レビューへの「参考になった」投票。(review_id, voter_hash) ユニークで重複防止。
 */
final class ReviewHelpfulVote extends Model
{
    protected $fillable = [
        'review_id', 'user_id', 'voter_hash',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(Review::class);
    }
}
