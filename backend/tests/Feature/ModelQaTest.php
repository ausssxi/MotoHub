<?php

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\ModelAnswer;
use App\Models\ModelQuestion;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => config()->set('ng_words.words', []));

function qaModel(): BikeModel
{
    $mfr = new Manufacturer(['slug' => 'honda']);
    $mfr->name = 'ホンダ';
    $mfr->save();

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'レブル250', 'slug' => 'rebel-250']);
}

function qaQuestion(BikeModel $m, string $title, bool $approved = true): ModelQuestion
{
    return ModelQuestion::create(['bike_model_id' => $m->id, 'nickname' => '名無し', 'title' => $title, 'is_approved' => $approved]);
}

function qUrl(ModelQuestion $q): string
{
    $m = $q->bikeModel;

    return route('bikes.model_question', ['mfrSlug' => $m->manufacturer->slug, 'modelSlug' => $m->slug, 'id' => $q->id]);
}

// ─────────── 質問・回答の投稿（ログイン不要・即反映） ───────────

it('lets a guest post a question and shows it on its detail page immediately', function () {
    $m = qaModel();

    $this->post("/bikes/models/{$m->id}/questions", ['title' => '足つきどうですか？', 'nickname' => 'とおりすがり'])
        ->assertRedirect($m->seo_url.'#questions');

    $q = ModelQuestion::first();
    expect($q->user_id)->toBeNull()->and($q->nickname)->toBe('とおりすがり')
        ->and($q->is_approved)->toBeTrue()->and(strlen($q->submitter_ip_hash))->toBe(64);

    $this->get(qUrl($q))->assertOk()->assertSee('足つきどうですか？')->assertSee('とおりすがり');
});

it('lets a guest answer a question, shown under it', function () {
    $m = qaModel();
    $q = qaQuestion($m, '通勤に使えますか？');

    $this->post("/bikes/questions/{$q->id}/answers", ['body' => '毎日通勤で使ってます', 'nickname' => 'オーナーA'])
        ->assertRedirect();

    expect(ModelAnswer::where('body', '毎日通勤で使ってます')->exists())->toBeTrue();
    $this->get(qUrl($q))->assertOk()->assertSee('毎日通勤で使ってます')->assertSee('回答（1件）');
});

it('defaults a nameless guest to 名無しライダー and never leaks the real name', function () {
    $m = qaModel();

    // ゲスト（名前なし）→ 名無しライダー
    $this->post("/bikes/models/{$m->id}/questions", ['title' => '名無し質問']);
    expect(ModelQuestion::where('title', '名無し質問')->first()->display_name)->toBe('名無しライダー');

    // ログイン → 公開ハンドル（本名 user->name は出さない）
    $user = User::factory()->create(['name' => '本名太郎', 'review_display_name' => 'ハンドルX']);
    $this->actingAs($user)->post("/bikes/models/{$m->id}/questions", ['title' => 'ログイン質問']);
    expect(ModelQuestion::where('title', 'ログイン質問')->first()->display_name)->toBe('ハンドルX');
});

// ─────────── 安全弁 ───────────

it('blocks ng words in questions and answers but lets criticism through', function () {
    config()->set('ng_words.words', ['死ね']);
    $m = qaModel();

    $this->post("/bikes/models/{$m->id}/questions", ['title' => 'こんな車は死ね'])->assertSessionHasErrors('title');
    $this->post("/bikes/models/{$m->id}/questions", ['title' => 'ここが不満だけど検討中'])->assertRedirect(); // 批判は通す
    expect(ModelQuestion::count())->toBe(1);

    $q = ModelQuestion::first();
    $this->post("/bikes/questions/{$q->id}/answers", ['body' => 'お前は死ね'])->assertSessionHasErrors('body');
    expect(ModelAnswer::count())->toBe(0);
});

it('rejects honeypot-filled question posts', function () {
    $m = qaModel();
    $this->post("/bikes/models/{$m->id}/questions", ['title' => 'ok', 'website' => 'http://spam'])
        ->assertSessionHasErrors('website');
    expect(ModelQuestion::count())->toBe(0);
});

it('throttles rapid question posting (429)', function () {
    $m = qaModel();
    for ($i = 0; $i < 3; $i++) {
        $this->post("/bikes/models/{$m->id}/questions", ['title' => "q{$i}"])->assertRedirect();
    }
    $this->post("/bikes/models/{$m->id}/questions", ['title' => 'over'])->assertStatus(429);
});

// ─────────── 通報（polymorphic・両対象） ───────────

it('reports questions and answers via the shared polymorphic endpoint', function () {
    $m = qaModel();
    $q = qaQuestion($m, '通報対象の質問');
    $a = ModelAnswer::create(['model_question_id' => $q->id, 'nickname' => 'x', 'body' => '通報対象の回答', 'is_approved' => true]);

    expect(Report::REPORTABLE_TYPES)->toHaveKeys(['model_question', 'model_answer']);

    $this->post('/reports', ['type' => 'model_question', 'id' => $q->id, 'reason' => 'spam'])->assertRedirect();
    $this->post('/reports', ['type' => 'model_answer', 'id' => $a->id, 'reason' => 'spam'])->assertRedirect();

    expect(Report::where('reportable_type', ModelQuestion::class)->exists())->toBeTrue()
        ->and(Report::where('reportable_type', ModelAnswer::class)->exists())->toBeTrue();
});

// ─────────── キルスイッチ ───────────

it('hides an unapproved question (404 detail) and its answers', function () {
    $m = qaModel();
    $q = qaQuestion($m, '非公開質問', approved: false);
    ModelAnswer::create(['model_question_id' => $q->id, 'nickname' => 'x', 'body' => 'ぶら下がる回答', 'is_approved' => true]);

    $this->get(qUrl($q))->assertNotFound();                        // 質問非公開＝詳細404
    expect(ModelQuestion::approved()->where('bike_model_id', $m->id)->count())->toBe(0); // 一覧に出ない
});

it('hides only the unapproved answer, keeping the rest', function () {
    $m = qaModel();
    $q = qaQuestion($m, '質問');
    ModelAnswer::create(['model_question_id' => $q->id, 'nickname' => 'x', 'body' => '公開回答', 'is_approved' => true]);
    ModelAnswer::create(['model_question_id' => $q->id, 'nickname' => 'x', 'body' => '非公開回答', 'is_approved' => false]);

    $this->get(qUrl($q))->assertOk()->assertSee('公開回答')->assertDontSee('非公開回答');
});

// ─────────── 参考になった ───────────

it('increments helpful_count on an answer', function () {
    $m = qaModel();
    $q = qaQuestion($m, '質問');
    $a = ModelAnswer::create(['model_question_id' => $q->id, 'nickname' => 'x', 'body' => '回答', 'is_approved' => true]);

    $this->post("/bikes/answers/{$a->id}/helpful")->assertOk()->assertJson(['helpful_count' => 1]);
    expect($a->fresh()->helpful_count)->toBe(1);
});

// ─────────── 車種ページの構成（レビューと別枠・空状態・SQLite描画不可の静的ガード） ───────────

it('keeps review and Q&A as separate sections in the model_detail blade', function () {
    $blade = file_get_contents(resource_path('views/bikes/model_detail.blade.php'));
    expect($blade)->toContain('id="reviews"')                 // レビュー枠
        ->toContain('id="questions"')                         // Q&A 枠（別）
        ->toContain('の質問・相談')                           // Q&A 見出し
        ->toContain('この車種への質問はまだありません')       // 0件歓迎トーン
        ->toContain('回答募集中')                             // 未回答バッジ
        ->toContain('$modelQuestions');                       // 出し分けガード
});

// ─────────── デフォルト展開（価格コム式・回答本文を初期DOMに出す） ───────────

it('eager-loads approved answers on the questions injection so answer bodies can render inline', function () {
    $m = qaModel();
    $q = qaQuestion($m, '回答が展開される質問');
    ModelAnswer::create(['model_question_id' => $q->id, 'nickname' => 'x', 'body' => '公開回答本文', 'is_approved' => true]);
    ModelAnswer::create(['model_question_id' => $q->id, 'nickname' => 'x', 'body' => '非公開回答', 'is_approved' => false]);

    // 車種ページ注入と同じ eager load（回答本文を初期DOMに出すため）
    $injected = ModelQuestion::approved()->where('bike_model_id', $m->id)
        ->withCount('approvedAnswers')
        ->with(['approvedAnswers' => fn ($x) => $x->orderByDesc('created_at')])
        ->orderByDesc('created_at')->get();

    $first = $injected->first();
    expect($first->relationLoaded('approvedAnswers'))->toBeTrue()          // 遅延取得でなく初期ロード
        ->and($first->approvedAnswers->pluck('body')->all())->toBe(['公開回答本文']); // 公開のみ（キルスイッチ）
});

it('model_detail blade expands first-N answers inline and links to すべて見る', function () {
    $blade = file_get_contents(resource_path('views/bikes/model_detail.blade.php'));
    expect($blade)->toContain('$qaExpandCount')                    // 先頭N件だけ展開
        ->toContain('approvedAnswers->take(2)')                    // 回答本文を初期DOMに出力
        ->toContain('$ans->body')                                 // 回答本文（JS遅延でない）
        ->toContain("route('bikes.model_questions'")              // すべて見る導線
        ->toContain('すべての質問を見る');
});

it('renders the per-model questions list page (すべて見る target)', function () {
    $m = qaModel();
    qaQuestion($m, 'リストに出る質問');

    $this->get(route('bikes.model_questions', ['mfrSlug' => $m->manufacturer->slug, 'modelSlug' => $m->slug]))
        ->assertOk()
        ->assertSee('リストに出る質問')
        ->assertSee('の質問・相談');
});

it('exposes the QA expand/list-limit class constants (not config)', function () {
    expect(\App\Http\Controllers\Bike\BikeController::QA_EXPANDED_COUNT)->toBe(3)
        ->and(\App\Http\Controllers\Bike\BikeController::QA_LIST_LIMIT)->toBe(5);
});

it('injects modelQuestions scoped to the model, newest-first with answer counts', function () {
    $m = qaModel();
    qaQuestion($m, '古い質問')->update(['created_at' => now()->subDay()]);
    $newer = qaQuestion($m, '新しい質問');
    ModelAnswer::create(['model_question_id' => $newer->id, 'nickname' => 'x', 'body' => 'a', 'is_approved' => true]);

    // 車種ページが注入する query と同じ
    $qs = ModelQuestion::approved()->where('bike_model_id', $m->id)->withCount('approvedAnswers')->orderByDesc('created_at')->get();
    expect($qs->first()->title)->toBe('新しい質問')            // 新着順
        ->and($qs->first()->approved_answers_count)->toBe(1); // 回答数
});
