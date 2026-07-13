<?php

use App\Jobs\GenerateMotoHubAnswer;
use App\Jobs\SendThreadReplyNotification;
use App\Models\BikeModel;
use App\Models\DiscussionReply;
use App\Models\DiscussionThread;
use App\Models\Manufacturer;
use App\Models\ModelQuestion;
use App\Models\PushQuestionSubscription;
use App\Models\Report;
use App\Models\ThreadPushSubscription;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(fn () => config()->set('ng_words.words', []));

function dtModel(): BikeModel
{
    $mfr = new Manufacturer(['slug' => 'honda']);
    $mfr->name = 'ホンダ';
    $mfr->save();

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'レブル250', 'slug' => 'rebel-250', 'displacement' => 250]);
}

function threadPath(DiscussionThread $t): string
{
    $m = $t->bikeModel;

    return route('bikes.thread', ['mfrSlug' => $m->manufacturer->slug, 'modelSlug' => $m->slug, 'id' => $t->id], absolute: false);
}

// ─────────── MotoHub必答（過疎対策の核） ───────────

it('attaches a MotoHub official answer to a new question thread (never reply-0)', function () {
    $m = dtModel();

    $this->post("/bikes/models/{$m->id}/threads", ['type' => 'question', 'title' => '通勤の燃費は？', 'body' => '教えてください'])
        ->assertRedirect();

    $thread = DiscussionThread::first();
    $official = DiscussionReply::where('discussion_thread_id', $thread->id)->where('is_official', true)->first();

    expect($thread->type)->toBe('question')
        ->and($official)->not->toBeNull()
        ->and($official->source)->toBe('ai')
        ->and($official->display_name)->toBe('MotoHub')          // 人を装わず公式ラベル
        ->and($official->answer_generated_at)->not->toBeNull()   // dispatchAfterResponse で生成完了
        ->and($official->isPending())->toBeFalse()
        ->and($thread->replies()->count())->toBeGreaterThanOrEqual(1); // 返信0にならない
});

it('grounds the fallback answer in real data without fabrication (no API key)', function () {
    config()->set('services.anthropic.api_key', ''); // AI不使用 → 構造化フォールバック
    $m = dtModel();

    $this->post("/bikes/models/{$m->id}/threads", ['type' => 'question', 'title' => '維持費は？']);
    $official = DiscussionReply::where('is_official', true)->first();

    expect($official->body)->toContain('MotoHubのデータ')     // データ根拠のフレーミング
        ->toContain('250cc')                                  // 実スペックのみ
        ->not->toContain('準備しています');                    // プレースホルダは差し替え済み
});

it('uses the Claude answer when an API key is configured', function () {
    config()->set('services.anthropic.api_key', 'test-key');
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'MotoHubのデータではこの車種の燃費は良好です。']]], 200)]);
    $m = dtModel();

    $this->post("/bikes/models/{$m->id}/threads", ['type' => 'question', 'title' => '燃費は？']);

    expect(DiscussionReply::where('is_official', true)->first()->body)->toBe('MotoHubのデータではこの車種の燃費は良好です。');
});

it('caps MotoHub AI answers at 10/day per IP and falls back beyond the cap', function () {
    $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); // 秒間throttleを外し日次上限だけ検証
    config()->set('services.anthropic.api_key', 'test-key');
    Http::fake(['api.anthropic.com/*' => Http::response(['content' => [['type' => 'text', 'text' => 'AI回答テキスト。']]], 200)]);
    $m = dtModel();

    // 同一IPから11本の質問スレ。上限10までは Claude、11本目は生成せずフォールバック。
    for ($i = 1; $i <= 11; $i++) {
        $this->post("/bikes/models/{$m->id}/threads", ['type' => 'question', 'title' => "q{$i}"]);
    }

    $threads = DiscussionThread::orderBy('id')->get();
    $firstOfficial = DiscussionReply::where('discussion_thread_id', $threads[0]->id)->where('is_official', true)->first();
    $overCap = DiscussionReply::where('discussion_thread_id', $threads[10]->id)->where('is_official', true)->first();

    expect($firstOfficial->body)->toBe('AI回答テキスト。')          // 上限内はAI採用
        ->and($overCap->body)->toContain('MotoHubのデータ')        // 超過は構造化フォールバック
        ->and($overCap->answer_generated_at)->not->toBeNull();     // 超過でもスレは成立（準備中で放置しない）
});

it('dispatches the answer generation after response (worker-free)', function () {
    Bus::fake();
    $m = dtModel();

    $this->post("/bikes/models/{$m->id}/threads", ['type' => 'question', 'title' => 'q']);

    Bus::assertDispatchedAfterResponse(GenerateMotoHubAnswer::class);
});

it('does not attach an official answer to non-question threads', function () {
    Bus::fake();
    $m = dtModel();

    $this->post("/bikes/models/{$m->id}/threads", ['type' => 'chat', 'title' => '雑談スレ']);

    Bus::assertNotDispatchedAfterResponse(GenerateMotoHubAnswer::class);
    expect(DiscussionReply::where('is_official', true)->count())->toBe(0);
});

// ─────────── スレ詳細・スキーマ ───────────

it('renders the thread detail with breadcrumb, FAQPage and the MotoHub badge', function () {
    $m = dtModel();
    $this->post("/bikes/models/{$m->id}/threads", ['type' => 'question', 'title' => '足つきは？']);
    $thread = DiscussionThread::first();

    $this->get(threadPath($thread))
        ->assertOk()
        ->assertSee('BreadcrumbList')
        ->assertSee('FAQPage')          // 質問＋生成済み公式回答でFAQ出力
        ->assertSee('MotoHub')          // 公式ラベル
        ->assertSee('足つきは？');
});

// ─────────── 返信・投票・開放フラグ ───────────

it('lets a user reply and vote, with duplicate votes suppressed', function () {
    $m = dtModel();
    $thread = DiscussionThread::create(['bike_model_id' => $m->id, 'nickname' => 'x', 'title' => 't', 'type' => 'chat', 'status' => 'published']);

    $this->post("/bikes/threads/{$thread->id}/replies", ['body' => 'いいバイクです', 'nickname' => 'オーナー'])->assertRedirect();
    $reply = DiscussionReply::where('discussion_thread_id', $thread->id)->first();
    expect($reply->status)->toBe('published')->and($reply->source)->toBe('human');

    $this->post("/bikes/replies/{$reply->id}/vote")->assertOk()->assertJson(['helpful_count' => 1]);
    $this->post("/bikes/replies/{$reply->id}/vote")->assertOk()->assertJson(['helpful_count' => 1]); // 重複は増えない
    expect($reply->fresh()->helpful_count)->toBe(1);
});

it('honours the feature flags (create/reply closed => 403)', function () {
    $m = dtModel();
    $thread = DiscussionThread::create(['bike_model_id' => $m->id, 'nickname' => 'x', 'title' => 't', 'type' => 'chat', 'status' => 'published']);

    config()->set('ugc.thread_create_open', false);
    $this->post("/bikes/models/{$m->id}/threads", ['title' => 'x'])->assertForbidden();

    config()->set('ugc.replies_open', false);
    $this->post("/bikes/threads/{$thread->id}/replies", ['body' => 'x'])->assertForbidden();
});

// ─────────── モデレーション ───────────

it('registers discussion threads and replies as reportable', function () {
    expect(Report::REPORTABLE_TYPES)->toHaveKeys(['discussion_thread', 'discussion_reply'])
        ->and(Report::REPORTABLE_TYPES['discussion_thread'])->toBe(DiscussionThread::class);
});

it('purges reports and cascades replies when a thread is deleted', function () {
    $m = dtModel();
    $thread = DiscussionThread::create(['bike_model_id' => $m->id, 'nickname' => 'x', 'title' => 't', 'type' => 'chat', 'status' => 'published']);
    $reply = DiscussionReply::create(['discussion_thread_id' => $thread->id, 'nickname' => 'x', 'body' => 'b', 'status' => 'published']);
    Report::create(['reportable_type' => DiscussionThread::class, 'reportable_id' => $thread->id, 'reason' => 'spam', 'status' => 'open']);
    Report::create(['reportable_type' => DiscussionReply::class, 'reportable_id' => $reply->id, 'reason' => 'spam', 'status' => 'open']);

    $thread->delete();

    expect(DiscussionReply::count())->toBe(0)                                    // FKカスケード
        ->and(Report::where('reportable_type', DiscussionThread::class)->count())->toBe(0); // 通報purge
});

// ─────────── Q&A 種火移行（ロスなし・必答なし） ───────────

// ─────────── 返信通知の繋ぎ替え（旧Q&A push → threads） ───────────

it('ties a reply-notification subscription to the new thread on opt-in', function () {
    $m = dtModel();

    $this->post("/bikes/models/{$m->id}/threads", [
        'type' => 'chat', 'title' => '相談', 'push_endpoint' => 'https://push.example/z', 'push_p256dh' => 'P', 'push_auth' => 'A',
    ])->assertRedirect();

    $thread = DiscussionThread::first();
    $sub = ThreadPushSubscription::first();
    expect($sub)->not->toBeNull()
        ->and($sub->discussion_thread_id)->toBe($thread->id)
        ->and($sub->endpoint_hash)->toBe(hash('sha256', 'https://push.example/z'));
});

it('notifies on a human reply but not on the MotoHub official answer', function () {
    Bus::fake();
    $m = dtModel();
    $thread = DiscussionThread::create(['bike_model_id' => $m->id, 'nickname' => 'q', 'title' => 't', 'type' => 'question', 'status' => 'published', 'submitter_ip_hash' => 'QHASH']);
    ThreadPushSubscription::create(['discussion_thread_id' => $thread->id, 'endpoint' => 'https://push.example/a', 'p256dh' => 'p', 'auth' => 'a']);

    DiscussionReply::create(['discussion_thread_id' => $thread->id, 'is_official' => true, 'source' => 'ai', 'status' => 'published', 'body' => '公式', 'answer_generated_at' => now()]);
    Bus::assertNotDispatchedAfterResponse(SendThreadReplyNotification::class); // 必答では通知しない

    DiscussionReply::create(['discussion_thread_id' => $thread->id, 'nickname' => 'x', 'source' => 'human', 'status' => 'published', 'body' => '返信', 'submitter_ip_hash' => 'OTHER']);
    Bus::assertDispatchedAfterResponse(SendThreadReplyNotification::class); // 別人の人間返信で通知
});

it('suppresses reply notification for the questioner replying to their own thread', function () {
    Bus::fake();
    $m = dtModel();
    $thread = DiscussionThread::create(['bike_model_id' => $m->id, 'nickname' => 'q', 'title' => 't', 'type' => 'question', 'status' => 'published', 'submitter_ip_hash' => 'SAME']);
    ThreadPushSubscription::create(['discussion_thread_id' => $thread->id, 'endpoint' => 'https://push.example/a', 'p256dh' => 'p', 'auth' => 'a']);

    DiscussionReply::create(['discussion_thread_id' => $thread->id, 'nickname' => 'q', 'source' => 'human', 'status' => 'published', 'body' => '自己返信', 'submitter_ip_hash' => 'SAME']);
    Bus::assertNotDispatchedAfterResponse(SendThreadReplyNotification::class);
});

it('backfills existing Q&A push subscriptions to the migrated thread (subscribers not lost)', function () {
    $m = dtModel();
    $q = ModelQuestion::create(['bike_model_id' => $m->id, 'nickname' => 'x', 'title' => '旧質問', 'is_approved' => true]);
    // ③ Q&A→スレ移行（種火スレ生成）
    (require database_path('migrations/2026_07_13_100300_migrate_model_qa_into_threads.php'))->up();
    $thread = DiscussionThread::where('is_seed', true)->first();
    // 旧push購読（question紐付）
    PushQuestionSubscription::create(['model_question_id' => $q->id, 'endpoint' => 'https://push.example/legacy', 'p256dh' => 'p', 'auth' => 'a']);

    // 繋ぎ替えmigrationのバックフィルを実行（create は hasTable ガードでスキップ）
    (require database_path('migrations/2026_07_13_120000_create_thread_push_subscriptions_table.php'))->up();

    $sub = ThreadPushSubscription::where('discussion_thread_id', $thread->id)->first();
    expect($sub)->not->toBeNull()
        ->and($sub->endpoint_hash)->toBe(hash('sha256', 'https://push.example/legacy')); // 既存購読者が正しいスレへ
});

it('keeps the legacy question detail page alive, pointing posting to threads', function () {
    $m = dtModel();
    $q = ModelQuestion::create(['bike_model_id' => $m->id, 'nickname' => 'x', 'title' => '旧質問', 'is_approved' => true]);

    $this->get(route('bikes.model_question', ['mfrSlug' => $m->manufacturer->slug, 'modelSlug' => $m->slug, 'id' => $q->id]))
        ->assertOk()
        ->assertSee('クチコミ・相談')
        ->assertSee('#threads', false);
});

it('surfaces the thread section and create form on the model detail page', function () {
    $blade = file_get_contents(resource_path('views/bikes/model_detail.blade.php'));
    $ctrl = file_get_contents(app_path('Http/Controllers/Bike/BikeController.php'));

    expect($blade)->toContain('id="threads"')
        ->toContain("route('bikes.thread.store'")   // type選択つき投稿フォーム
        ->toContain('$modelThreads')
        ->and($ctrl)->toContain("\$viewData['modelThreads']"); // キャッシュ外注入（即時反映）
});

it('migrates existing Q&A into seed threads without loss and without triggering answers', function () {
    $m = dtModel();
    $qId = DB::table('model_questions')->insertGetId([
        'bike_model_id' => $m->id, 'nickname' => '名無し', 'title' => '旧質問', 'body' => '本文', 'is_approved' => true, 'created_at' => now(), 'updated_at' => now(),
    ]);
    DB::table('model_answers')->insert([
        'model_question_id' => $qId, 'nickname' => '回答者', 'body' => '旧回答', 'is_approved' => true, 'helpful_count' => 3, 'created_at' => now(), 'updated_at' => now(),
    ]);

    // データ移行マイグレーションを実行（RefreshDatabase時は空で走行済み・冪等スキップされているため明示実行）
    $migration = require database_path('migrations/2026_07_13_100300_migrate_model_qa_into_threads.php');
    $migration->up();

    $thread = DiscussionThread::where('is_seed', true)->first();
    expect($thread)->not->toBeNull()
        ->and($thread->title)->toBe('旧質問')
        ->and($thread->type)->toBe('question')
        ->and($thread->status)->toBe('published');

    $reply = $thread->replies()->first();
    expect($reply->body)->toBe('旧回答')
        ->and($reply->helpful_count)->toBe(3)
        ->and($reply->source)->toBe('human')
        ->and(DiscussionReply::where('is_official', true)->count())->toBe(0); // 種火に必答は付かない
});
