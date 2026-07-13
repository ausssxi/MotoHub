<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\SendThreadReplyNotification;
use App\Models\DiscussionReply;

/**
 * 人間の返信が付いた時だけ、スレ購読者（質問者）へ通知を撃つ。
 * 公式/AI（MotoHub必答）では撃たない＝自分が作ったスレの自動回答で通知が飛ぶ冗長さを避ける。
 * 自問自答（質問者本人の返信）も抑制。ワーカー非常駐のため dispatchAfterResponse。
 */
final class DiscussionReplyObserver
{
    public function created(DiscussionReply $reply): void
    {
        if ($reply->is_official || $reply->source === 'ai' || $reply->status !== 'published') {
            return; // 必答・AIは通知しない
        }

        $thread = $reply->thread()->first();
        if ($thread === null || $thread->status !== 'published') {
            return;
        }
        if ($this->isSelfReply($reply, $thread)) {
            return; // 質問者本人の返信は通知不要
        }
        if (! $thread->pushSubscriptions()->exists()) {
            return;
        }

        SendThreadReplyNotification::dispatchAfterResponse($reply->id);
    }

    private function isSelfReply(DiscussionReply $reply, \App\Models\DiscussionThread $thread): bool
    {
        if ($reply->user_id !== null && $reply->user_id === $thread->user_id) {
            return true;
        }

        return $reply->submitter_ip_hash !== null && $reply->submitter_ip_hash === $thread->submitter_ip_hash;
    }
}
