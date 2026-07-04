<?php

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'services.anthropic.api_key' => 'test-key',
        'services.anthropic.model' => 'claude-test-model',
    ]);
    User::factory()->create(['role' => 'admin']);
});

function fakeArticleJson(array $overrides = []): string
{
    return json_encode(array_merge([
        'title' => '【原付】エンジンがかからない時の原因と直し方',
        'slug' => 'gentsuki-engine-wont-start-draft',
        'meta_description' => '原付のエンジンがかからない原因を切り分ける記事です。',
        'tags' => ['トラブル対処', 'エンジン'],
        'body_markdown' => "## リード\n本文です。[診断](/trouble?symptom=engine-wont-start) [店](/shops/repair)",
    ], $overrides), JSON_UNESCAPED_UNICODE);
}

function fakeApiOk(string $json): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response(['content' => [['text' => $json]]], 200),
    ]);
}

// ─────────── 正常系: 下書きが作られる（絶対に公開されない）───────────

it('creates a DRAFT post (never published) with title, slug, tags and body', function () {
    fakeApiOk(fakeArticleJson());

    $this->artisan('blog:draft-trouble', ['--symptom' => '原付のエンジンがかからない', '--keyword' => '原付 エンジン かからない'])
        ->assertSuccessful();

    $post = BlogPost::sole();
    expect($post->status)->toBe('draft')          // ← 公開されない
        ->and($post->published_at)->toBeNull()     // ← いかなる経路でも公開状態にしない
        ->and($post->isPublished())->toBeFalse()
        ->and($post->title)->toContain('エンジンがかからない')
        ->and($post->slug)->toBe('gentsuki-engine-wont-start-draft')
        ->and($post->body)->toContain('/trouble?symptom=engine-wont-start')
        ->and($post->body)->toContain('/shops/repair')
        // マーカーは専用カラムに。body は生成本文だけで汚さない。
        ->and($post->body)->not->toContain('AI下書き')
        ->and($post->body)->not->toContain('<!--')
        ->and($post->draft_note)->toContain('AI下書き（要監修）')
        ->and($post->draft_note)->toContain('symptom:原付のエンジンがかからない')
        ->and($post->draft_note)->toContain('keyword:原付 エンジン かからない');

    expect($post->tags->pluck('name')->all())->toContain('トラブル対処', 'エンジン');
});

it('uses the anthropic model id from config (no hardcode)', function () {
    fakeApiOk(fakeArticleJson());

    $this->artisan('blog:draft-trouble', ['--symptom' => 'x', '--keyword' => 'y'])->assertSuccessful();

    Http::assertSent(fn ($request) => $request['model'] === 'claude-test-model');
});

// ─────────── 異常系 ───────────

it('errors and creates no draft when JSON parsing fails (retry then give up)', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response(['content' => [['text' => 'これはJSONではありません']]], 200),
    ]);

    $this->artisan('blog:draft-trouble', ['--symptom' => 'x', '--keyword' => 'y'])
        ->assertFailed();

    expect(BlogPost::count())->toBe(0);
    Http::assertSentCount(2); // リトライ1回＝計2回
});

it('errors and creates no draft on API 500', function () {
    Http::fake(['api.anthropic.com/*' => Http::response('server error', 500)]);

    $this->artisan('blog:draft-trouble', ['--symptom' => 'x', '--keyword' => 'y'])->assertFailed();
    expect(BlogPost::count())->toBe(0);
});

it('errors and creates no draft on API 404', function () {
    Http::fake(['api.anthropic.com/*' => Http::response('not found', 404)]);

    $this->artisan('blog:draft-trouble', ['--symptom' => 'x', '--keyword' => 'y'])->assertFailed();
    expect(BlogPost::count())->toBe(0);
});

it('requires --symptom and --keyword', function () {
    $this->artisan('blog:draft-trouble', ['--symptom' => '原付'])->assertFailed();
    $this->artisan('blog:draft-trouble', ['--keyword' => 'x'])->assertFailed();
    expect(BlogPost::count())->toBe(0);
});

// ─────────── スラッグ重複 → 連番 ───────────

it('appends a numeric suffix on slug collision', function () {
    $author = User::first();
    BlogPost::create([
        'author_id' => $author->id, 'title' => '既存', 'slug' => 'gentsuki-engine-wont-start-draft',
        'body' => 'x', 'status' => 'draft',
    ]);
    fakeApiOk(fakeArticleJson()); // 同じ slug を返す

    $this->artisan('blog:draft-trouble', ['--symptom' => 'x', '--keyword' => 'y'])->assertSuccessful();

    expect(BlogPost::where('slug', 'gentsuki-engine-wont-start-draft-2')->exists())->toBeTrue();
});

// ─────────── 自動公開・スケジュール未登録（この機能の存在理由）───────────

it('is NOT registered in the Kernel schedule', function () {
    $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
    $commands = collect($schedule->events())->map(fn ($e) => (string) $e->command);

    expect($commands->contains(fn ($c) => str_contains($c, 'blog:draft-trouble')))->toBeFalse();
});
