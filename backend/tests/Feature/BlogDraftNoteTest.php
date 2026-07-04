<?php

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function draftPost(array $overrides = []): BlogPost
{
    $author = User::factory()->create(['role' => 'admin']);

    return BlogPost::create(array_merge([
        'author_id' => $author->id,
        'title' => 'テスト記事',
        'slug' => 'draft-note-test-'.uniqid(),
        'body' => "## 見出し\n本文です。",
        'status' => 'draft',
        'draft_note' => 'AI下書き（要監修） 生成:2026-07-05 10:00 / symptom:x / keyword:y',
    ], $overrides));
}

// ─────────── 管理画面: 表示のみのバナー ───────────

it('shows the draft_note banner on the admin edit screen', function () {
    $post = draftPost();
    $admin = User::where('role', 'admin')->first();

    $res = $this->actingAs($admin)->get(route('admin.blog.posts.edit', $post->id))->assertOk();

    $res->assertSee('この記事はAI生成の下書きです。監修のうえ公開してください。');
    $res->assertSee($post->draft_note);
    // 編集フォームの入力欄ではない（name="draft_note" の input を持たない）
    $res->assertDontSee('name="draft_note"', false);
});

it('does not show the banner when draft_note is empty', function () {
    $post = draftPost(['draft_note' => null]);
    $admin = User::where('role', 'admin')->first();

    $this->actingAs($admin)->get(route('admin.blog.posts.edit', $post->id))
        ->assertOk()
        ->assertDontSee('この記事はAI生成の下書きです');
});

// ─────────── 一般表示: 絶対に出さない ───────────

it('never exposes draft_note on the public article page', function () {
    // 公開済みだが draft_note が残っている記事を直接作る（漏洩しないことの確認）
    $post = draftPost([
        'status' => 'published',
        'published_at' => now()->subDay(),
        'draft_note' => 'AI下書き（要監修） 生成:2026-07-05 10:00 / symptom:秘密 / keyword:秘密',
    ]);

    $this->get('/blog/'.$post->slug)
        ->assertOk()
        ->assertDontSee('AI下書き')
        ->assertDontSee('秘密');
});

// ─────────── 公開でフラグ自動クリア ───────────

it('clears draft_note when the post is published via admin update', function () {
    $post = draftPost();
    $admin = User::where('role', 'admin')->first();

    $this->actingAs($admin)->put(route('admin.blog.posts.update', $post->id), [
        'title' => $post->title,
        'body' => $post->body,
        'status' => 'published',
    ])->assertRedirect();

    expect($post->fresh()->draft_note)->toBeNull();
});
