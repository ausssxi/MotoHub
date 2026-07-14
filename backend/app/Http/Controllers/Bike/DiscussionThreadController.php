<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Http\Requests\Bike\StoreDiscussionReplyRequest;
use App\Http\Requests\Bike\StoreDiscussionThreadRequest;
use App\Models\BikeModel;
use App\Models\DiscussionReply;
use App\Models\DiscussionThread;
use App\Models\ThreadPushSubscription;
use App\Models\ThreadReplyVote;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * 車種ページの統合スレッド型クチコミ。質問(type=question)には MotoHub必答（公式AI）が
 * DiscussionThreadObserver 経由で即時に付く＝返信0を構造的にゼロにする。
 * 安全弁・ゲスト識別は既存Q&A（ModelQaController）と同型。
 */
final class DiscussionThreadController extends Controller
{
    /** スレッド詳細（OP＋返信＋MotoHub必答・FAQPage/Breadcrumbの受け皿）。 */
    public function show(string $mfrSlug, string $modelSlug, int $id): View
    {
        $thread = DiscussionThread::published()
            ->with(['bikeModel.manufacturer', 'user'])
            ->findOrFail($id);

        // 公式（MotoHub必答）を先頭、その後は新着順
        $replies = $thread->publishedReplies()
            ->with('user')
            ->orderByDesc('is_official')
            ->orderBy('created_at')
            ->get();

        return view('bikes.thread', [
            'thread' => $thread,
            'model' => $thread->bikeModel,
            'replies' => $replies,
        ]);
    }

    /** スレッド作成（車種ページから）。type=question は必答が走る。 */
    public function store(StoreDiscussionThreadRequest $request, int $modelId): RedirectResponse
    {
        abort_unless((bool) config('ugc.thread_create_open', true), 403);

        $model = BikeModel::findOrFail($modelId);

        $thread = DiscussionThread::create([
            'bike_model_id' => $model->id,
            'user_id' => $request->user()?->id,
            // 既定は casual(chat)。質問を選んだ時だけ必答が走る（未指定で question 事故発火を防ぐ）。
            'type' => $request->input('type') ?: 'chat',
            'nickname' => $this->guestNickname($request),
            'title' => $request->input('title') ?: null,
            'body' => $request->input('body') ?: null,
            'status' => 'published', // 即反映・通報でキルスイッチ（status=hidden）
            'submitter_ip_hash' => $this->ipHash($request),
        ]);

        // 「返信が付いたら通知」に許可した場合のみ、この新スレへ購読を紐付ける（断られても投稿は正常完了）
        $this->maybeSubscribeToReplies($request, $thread);

        return redirect($this->threadUrl($thread))->with('ugc_success', 'thread');
    }

    /** 返信投稿（開放フラグで制御）。 */
    public function storeReply(StoreDiscussionReplyRequest $request, int $threadId): RedirectResponse
    {
        abort_unless((bool) config('ugc.replies_open', true), 403);

        $thread = DiscussionThread::published()->with('bikeModel.manufacturer')->findOrFail($threadId);

        DiscussionReply::create([
            'discussion_thread_id' => $thread->id,
            'user_id' => $request->user()?->id,
            'nickname' => $this->guestNickname($request),
            'body' => $request->input('body'),
            'is_official' => false,
            'source' => 'human',
            'status' => 'published',
            'submitter_ip_hash' => $this->ipHash($request),
        ]);

        return redirect($this->threadUrl($thread).'#replies')->with('ugc_success', 'reply');
    }

    /** 返信への「ナイス」（重複防止つき）。 */
    public function vote(Request $request, int $replyId): JsonResponse
    {
        $reply = DiscussionReply::published()->findOrFail($replyId);

        $voterHash = $request->user()
            ? hash('sha256', 'user:'.$request->user()->id)
            : $this->ipHash($request);

        $vote = ThreadReplyVote::firstOrCreate(
            ['discussion_reply_id' => $reply->id, 'voter_hash' => $voterHash],
            ['user_id' => $request->user()?->id],
        );

        if ($vote->wasRecentlyCreated) {
            $reply->increment('helpful_count');
        }

        return response()->json(['helpful_count' => $reply->fresh()->helpful_count]);
    }

    /** ゲスト表示名（ログイン時は null＝display_name がハンドルを使う。本名は使わない）。 */
    private function guestNickname(Request $request): ?string
    {
        if ($request->user()) {
            return null;
        }

        return trim(strip_tags((string) $request->input('nickname'))) ?: '名無しライダー';
    }

    private function ipHash(Request $request): string
    {
        return hash('sha256', $request->ip().'|'.config('app.key'));
    }

    /**
     * スレッドへの「返信通知」購読を保存（任意）。endpoint等が揃っている時だけ紐付ける。
     * 匿名識別は endpoint_hash を流用（新規PIIは保存しない）。
     */
    private function maybeSubscribeToReplies(Request $request, DiscussionThread $thread): void
    {
        $endpoint = trim((string) $request->input('push_endpoint'));
        $p256dh = trim((string) $request->input('push_p256dh'));
        $auth = trim((string) $request->input('push_auth'));

        if ($endpoint === '' || $p256dh === '' || $auth === '') {
            return; // 未許可 or 非対応環境 → 通知なしで正常完了
        }

        ThreadPushSubscription::updateOrCreate(
            [
                'endpoint_hash' => hash('sha256', $endpoint),
                'discussion_thread_id' => $thread->id,
            ],
            [
                'endpoint' => $endpoint,
                'p256dh' => $p256dh,
                'auth' => $auth,
                'user_id' => $request->user()?->id,
            ],
        );
    }

    private function threadUrl(DiscussionThread $thread): string
    {
        $model = $thread->bikeModel;

        return route('bikes.thread', [
            'mfrSlug' => $model->manufacturer->slug ?? $model->manufacturer_id,
            'modelSlug' => $model->slug ?? $model->id,
            'id' => $thread->id,
        ]);
    }
}
