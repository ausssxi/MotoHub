<?php

use App\Jobs\SendQaAnswerNotification;
use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\ModelAnswer;
use App\Models\ModelQuestion;
use App\Models\PushQuestionSubscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

beforeEach(fn () => config()->set('ng_words.words', []));

function pushModel(): BikeModel
{
    $mfr = new Manufacturer(['slug' => 'honda']);
    $mfr->name = 'ホンダ';
    $mfr->save();

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'レブル250', 'slug' => 'rebel-250']);
}

function pushQuestion(BikeModel $m, array $attrs = []): ModelQuestion
{
    return ModelQuestion::create(array_merge([
        'bike_model_id' => $m->id, 'nickname' => '名無し', 'title' => '質問', 'is_approved' => true,
    ], $attrs));
}

function subscribe(ModelQuestion $q, string $endpoint = 'https://push.example/aaa'): PushQuestionSubscription
{
    return PushQuestionSubscription::create([
        'model_question_id' => $q->id, 'endpoint' => $endpoint, 'p256dh' => 'p', 'auth' => 'a',
    ]);
}

// ─────────── 購読の紐付け（質問投稿時） ───────────

it('ties a push subscription to the created question when opt-in keys are posted', function () {
    $m = pushModel();

    $this->post("/bikes/models/{$m->id}/questions", [
        'title' => '足つきどう？',
        'push_endpoint' => 'https://push.example/xyz',
        'push_p256dh' => 'KEYP',
        'push_auth' => 'KEYA',
    ])->assertRedirect($m->seo_url.'#questions');

    $q = ModelQuestion::first();
    $sub = PushQuestionSubscription::first();

    expect($sub)->not->toBeNull()
        ->and($sub->model_question_id)->toBe($q->id)
        ->and($sub->endpoint_hash)->toBe(hash('sha256', 'https://push.example/xyz'))
        ->and($sub->user_id)->toBeNull();
});

it('posts a question normally without a subscription when opt-in is declined (no keys)', function () {
    $m = pushModel();

    $this->post("/bikes/models/{$m->id}/questions", ['title' => '通知いらない質問'])
        ->assertRedirect($m->seo_url.'#questions');

    expect(ModelQuestion::count())->toBe(1)
        ->and(PushQuestionSubscription::count())->toBe(0);
});

// ─────────── 送信（回答が承認された契機） ───────────

it('dispatches a notification to the question subscribers when an answer is posted', function () {
    Bus::fake();
    $m = pushModel();
    $q = pushQuestion($m);
    subscribe($q);

    $this->post("/bikes/questions/{$q->id}/answers", ['body' => '毎日通勤で使ってます', 'nickname' => 'オーナー'])
        ->assertRedirect();

    $answer = ModelAnswer::first();
    Bus::assertDispatched(SendQaAnswerNotification::class, fn ($job) => $job->answerId === $answer->id);
    expect($answer->fresh()->answer_pushed_at)->not->toBeNull();
});

it('does not dispatch when the question has no subscribers', function () {
    Bus::fake();
    $m = pushModel();
    $q = pushQuestion($m); // 購読者なし

    $this->post("/bikes/questions/{$q->id}/answers", ['body' => '回答', 'nickname' => 'x'])->assertRedirect();

    Bus::assertNotDispatched(SendQaAnswerNotification::class);
});

it('does not dispatch for an unapproved (killed) answer', function () {
    Bus::fake();
    $m = pushModel();
    $q = pushQuestion($m);
    subscribe($q);

    // キル状態で作成 → 送らない（answer_pushed_at は立てない＝後で復活時に送れる）
    $answer = ModelAnswer::create(['model_question_id' => $q->id, 'nickname' => 'x', 'body' => '非公開', 'is_approved' => false]);

    Bus::assertNotDispatched(SendQaAnswerNotification::class);
    expect($answer->fresh()->answer_pushed_at)->toBeNull();
});

it('dispatches once when a killed answer is later approved, and never re-sends', function () {
    Bus::fake();
    $m = pushModel();
    $q = pushQuestion($m);
    subscribe($q);

    $answer = ModelAnswer::create(['model_question_id' => $q->id, 'nickname' => 'x', 'body' => '回答', 'is_approved' => false]);
    $answer->update(['is_approved' => true]);   // 復活 → 1通
    $answer->update(['is_approved' => false]);  // 再キル
    $answer->update(['is_approved' => true]);   // 再復活 → 送らない

    Bus::assertDispatchedTimes(SendQaAnswerNotification::class, 1);
});

it('suppresses a self-answer (questioner answering their own question)', function () {
    Bus::fake();
    $m = pushModel();
    $user = User::factory()->create();
    $q = pushQuestion($m, ['user_id' => $user->id, 'nickname' => null]);
    subscribe($q);

    $this->actingAs($user)->post("/bikes/questions/{$q->id}/answers", ['body' => '自己解決しました'])
        ->assertRedirect();

    Bus::assertNotDispatched(SendQaAnswerNotification::class);
});

it('suppresses a self-answer detected by matching submitter ip hash', function () {
    Bus::fake();
    $m = pushModel();
    // 質問者と同じ端末（同一 ip_hash）で回答した場合
    $ipHash = hash('sha256', '127.0.0.1|'.config('app.key'));
    $q = pushQuestion($m, ['submitter_ip_hash' => $ipHash]);
    subscribe($q);

    $this->post("/bikes/questions/{$q->id}/answers", ['body' => '自分で答える', 'nickname' => 'x'])->assertRedirect();

    Bus::assertNotDispatched(SendQaAnswerNotification::class);
});

// ─────────── 回帰: 既存Q&A投稿は不変 ───────────

it('does not create question subscriptions for the existing new-stock push flow', function () {
    // 質問通知は push_question_subscriptions に閉じており、push_subscriptions(車種)は無関係
    $m = pushModel();
    $this->post("/bikes/models/{$m->id}/questions", ['title' => 'ふつうの質問']);

    expect(\App\Models\PushSubscription::count())->toBe(0);
});
