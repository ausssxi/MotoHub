<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\SendQaAnswerNotification;
use App\Models\ModelAnswer;
use App\Models\ModelQuestion;

/**
 * 回答が公開（is_approved=true）になった契機で、その質問の購読者へ通知を1通投入する。
 * どの経路（即反映の投稿 / 通報後のキル解除）でも同じ扱い。cache:clear や同期送信はしない。
 */
final class ModelAnswerObserver
{
    /** 即反映で is_approved=true 作成された直後。 */
    public function created(ModelAnswer $answer): void
    {
        $this->maybeNotify($answer);
    }

    /** キル→復活など is_approved の変化を含む更新。 */
    public function updated(ModelAnswer $answer): void
    {
        $this->maybeNotify($answer);
    }

    private function maybeNotify(ModelAnswer $answer): void
    {
        // 送信済み（キューへ投入済み）なら何もしない＝同じ回答で複数回送らない
        if ($answer->answer_pushed_at !== null) {
            return;
        }

        // キル/非承認は送らない（既存 approvedAnswers ガードに準拠）
        if (! $answer->is_approved) {
            return;
        }

        $question = $answer->question()->first();
        if ($question === null || ! $question->is_approved) {
            return;
        }

        // 自問自答など無意味な通知は抑制（以後の再評価も止めるためフラグは立てる）
        if ($this->isSelfAnswer($answer, $question)) {
            $this->markPushed($answer);

            return;
        }

        // 購読者が1人もいなければ送信不要（フラグを立てて再評価を止める）
        if (! $question->pushSubscriptions()->exists()) {
            $this->markPushed($answer);

            return;
        }

        // 先にフラグを立ててから投入（重複dispatch防止の冪等キー）
        $this->markPushed($answer);
        SendQaAnswerNotification::dispatch($answer->id);
    }

    private function isSelfAnswer(ModelAnswer $answer, ModelQuestion $question): bool
    {
        if ($answer->user_id !== null && $answer->user_id === $question->user_id) {
            return true;
        }

        if ($answer->submitter_ip_hash !== null
            && $answer->submitter_ip_hash === $question->submitter_ip_hash) {
            return true;
        }

        return false;
    }

    /** 再帰（observer再発火）を避けて送信済み時刻を刻む。 */
    private function markPushed(ModelAnswer $answer): void
    {
        $answer->answer_pushed_at = now();
        $answer->saveQuietly();
    }
}
