<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\GenerateMotoHubAnswer;
use App\Models\DiscussionReply;
use App\Models\DiscussionThread;

/**
 * 質問スレ作成時に MotoHub必答（公式AI回答）を即時に付ける＝返信0を構造的にゼロにする。
 * まず「準備中」の公式プレースホルダを置き、生成は dispatchAfterResponse（ワーカー非依存）で行う。
 * 種火移行（DB直挿入）は Eloquent create を通らないため発火せず、既存Q&Aには必答を付けない。
 */
final class DiscussionThreadObserver
{
    public function created(DiscussionThread $thread): void
    {
        if ($thread->type !== 'question' || $thread->status !== 'published') {
            return;
        }
        if ($thread->officialReply()->exists()) {
            return; // 冪等
        }

        // 「回答準備中」プレースホルダ（この時点で返信0にはならない）
        $reply = DiscussionReply::create([
            'discussion_thread_id' => $thread->id,
            'is_official' => true,
            'source' => 'ai',
            'status' => 'published',
            'body' => 'MotoHubが回答を準備しています…（まもなく表示されます）',
            'answer_generated_at' => null,
        ]);

        // レスポンス送出後に生成（キューワーカー非常駐のため）。失敗してもスレは成立。
        GenerateMotoHubAnswer::dispatchAfterResponse($reply->id);
    }
}
