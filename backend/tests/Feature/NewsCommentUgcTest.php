<?php

use App\Models\BikeNews;
use App\Models\NewsComment;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('ng_words.words', []);
});

function newsItem(): BikeNews
{
    return BikeNews::create([
        'title' => 'テストニュース', 'url' => 'https://motohub.jp/news/'.uniqid(),
        'source' => BikeNews::SOURCE_ORIGINAL, 'published_at' => now(),
        // bike_model_id を持たせない＝関連在庫/パーツ取得をスキップ（外部依存回避）
    ]);
}

// ─────────── ログイン不要化 ───────────

it('lets a guest post a comment (no login) and reflects it immediately', function () {
    $n = newsItem();

    $this->postJson("/news/{$n->id}/comment", ['body' => 'ゲストの初コメント', 'nickname' => 'とおりすがり'])
        ->assertOk()->assertJson(['success' => true]);

    $c = NewsComment::first();
    expect($c->user_id)->toBeNull()
        ->and($c->nickname)->toBe('とおりすがり')
        ->and($c->is_approved)->toBeTrue()
        ->and(strlen($c->submitter_ip_hash))->toBe(64)
        ->and($c->submitter_ip_hash)->not->toContain('127.0.0.1');

    $this->get("/news/{$n->id}")->assertOk()->assertSee('ゲストの初コメント')->assertSee('とおりすがり');
});

it('defaults a nameless guest to 名無しライダー', function () {
    $n = newsItem();
    $this->postJson("/news/{$n->id}/comment", ['body' => '名前なし'])->assertOk();
    expect(NewsComment::first()->display_name)->toBe('名無しライダー');
});

it('still lets a logged-in user comment with their public handle (regression)', function () {
    $n = newsItem();
    $user = User::factory()->create(['review_display_name' => 'ハンドル太郎']);

    $this->actingAs($user)->postJson("/news/{$n->id}/comment", ['body' => 'ログイン投稿'])->assertOk();

    $c = NewsComment::first();
    expect($c->user_id)->toBe($user->id)
        ->and($c->display_name)->toBe('ハンドル太郎'); // 本名ではなく公開ハンドル
});

// ─────────── 安全弁 ───────────

it('blocks a comment containing an ng word (not saved)', function () {
    config()->set('ng_words.words', ['死ね']);
    $n = newsItem();

    $this->postJson("/news/{$n->id}/comment", ['body' => 'こんな記事書いた奴は死ね'])
        ->assertStatus(422);
    expect(NewsComment::count())->toBe(0);
});

it('lets legitimate criticism through', function () {
    config()->set('ng_words.words', ['死ね', '殺す']);
    $n = newsItem();

    $this->postJson("/news/{$n->id}/comment", ['body' => 'この新型は高すぎると思う'])->assertOk();
    expect(NewsComment::where('body', 'この新型は高すぎると思う')->exists())->toBeTrue();
});

it('rejects a comment when the honeypot is filled', function () {
    $n = newsItem();
    $this->postJson("/news/{$n->id}/comment", ['body' => 'ok', 'website' => 'http://spam'])
        ->assertStatus(422);
    expect(NewsComment::count())->toBe(0);
});

it('throttles rapid comment posting (429)', function () {
    $n = newsItem();
    for ($i = 0; $i < 3; $i++) {
        $this->postJson("/news/{$n->id}/comment", ['body' => "c{$i}"])->assertOk();
    }
    $this->postJson("/news/{$n->id}/comment", ['body' => 'over'])->assertStatus(429);
});

// ─────────── 通報（polymorphic 流用・ログイン不要） ───────────

it('accepts a guest report of a news comment via the shared reports endpoint', function () {
    $n = newsItem();
    $c = NewsComment::create(['news_id' => $n->id, 'nickname' => '名無し', 'body' => '通報対象', 'is_approved' => true]);

    expect(Report::REPORTABLE_TYPES)->toHaveKey('news_comment');

    $this->post('/reports', ['type' => 'news_comment', 'id' => $c->id, 'reason' => 'spam'])
        ->assertRedirect()->assertSessionHas('report_success');

    $report = Report::first();
    expect($report->reportable_type)->toBe(NewsComment::class)
        ->and($report->reportable_id)->toBe($c->id);
});

// ─────────── キルスイッチ ───────────

it('hides a comment from the article when is_approved is false (kill switch)', function () {
    $n = newsItem();
    NewsComment::create(['news_id' => $n->id, 'nickname' => '名無し', 'body' => '公開コメント', 'is_approved' => true]);
    NewsComment::create(['news_id' => $n->id, 'nickname' => '名無し', 'body' => '非公開コメント', 'is_approved' => false]);

    $this->get("/news/{$n->id}")->assertOk()
        ->assertSee('公開コメント')
        ->assertDontSee('非公開コメント'); // キルスイッチで記事から消える
});
