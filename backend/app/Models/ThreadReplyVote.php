<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 返信への「ナイス」投票。(reply, voter_hash) ユニークで重複防止。
 */
final class ThreadReplyVote extends Model
{
    protected $fillable = [
        'discussion_reply_id', 'user_id', 'voter_hash',
    ];

    public function reply(): BelongsTo
    {
        return $this->belongsTo(DiscussionReply::class, 'discussion_reply_id');
    }
}
