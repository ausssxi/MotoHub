<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\BikeNews;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\NewsComment;
use App\Models\User;

// 公開（ログイン不要）コンテキストで本名(user->name)が漏れないことを固定する横展開privacyテスト。
// 公開表示は公開ハンドル(review_display_name・未設定は「名無しライダー」)のみ。

function piiUser(string $realName, ?string $handle): User
{
    return User::factory()->create(['name' => $realName, 'review_display_name' => $handle]);
}

function piiNews(string $title): BikeNews
{
    return BikeNews::create([
        'title' => $title,
        'url' => 'https://example.com/news/'.uniqid(),
        'source' => 'test',
        'published_at' => now(),
    ]);
}

// ---- 公開ガレージ一覧（みんなのガレージ） ----

it('public garage index never leaks the real name (handle only)', function () {
    $user = piiUser('本名タロウ', 'rider_x');
    $mfr = Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx']);
    MyBike::create(['user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイPCX', 'is_public' => true, 'current_odometer' => 1000]);

    $res = $this->get(route('garage.public.index'))->assertOk()->assertSee('rider_x');
    expect($res->getContent())->not->toContain('本名タロウ');
});

// ---- ニュース詳細（公開） ----

it('news show page renders the comment author handle, not the real name', function () {
    $author = piiUser('ニュース本名', 'news_rider');
    $news = piiNews('テストニュース');
    NewsComment::create(['news_id' => $news->id, 'user_id' => $author->id, 'body' => 'コメント本文']);

    $res = $this->get(route('news.show', $news->id))->assertOk()
        ->assertSee('news_rider')
        ->assertSee('コメント本文');
    expect($res->getContent())->not->toContain('ニュース本名');
});

it('news show uses 名無しライダー when the commenter has no handle (no real name)', function () {
    $author = piiUser('ハンドル無し本名', null);
    $news = piiNews('テスト2');
    NewsComment::create(['news_id' => $news->id, 'user_id' => $author->id, 'body' => '本文2']);

    $res = $this->get(route('news.show', $news->id))->assertOk()->assertSee('名無しライダー');
    expect($res->getContent())->not->toContain('ハンドル無し本名');
});

// ---- ニュースコメント投稿のJSON（フロントがそのまま公開描画する） ----

it('news comment POST JSON returns the handle, not the real name', function () {
    $author = piiUser('JSON本名', 'json_rider');
    $news = piiNews('テスト3');

    $res = $this->actingAs($author)->postJson(route('news.comment', $news->id), ['body' => 'やあ'])
        ->assertOk()
        ->assertJsonPath('comment.user.name', 'json_rider');
    expect($res->getContent())->not->toContain('JSON本名');
});

it('news comment POST JSON falls back to 名無しライダー without a handle', function () {
    $author = piiUser('JSON本名2', null);
    $news = piiNews('テスト4');

    $this->actingAs($author)->postJson(route('news.comment', $news->id), ['body' => 'やあ'])
        ->assertOk()
        ->assertJsonPath('comment.user.name', '名無しライダー');
});
