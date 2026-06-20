<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\BikeNews;
use App\Models\BikeParking;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\NewsComment;
use App\Models\ParkingReview;
use App\Models\TouringGuide;
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

// ---- 駐車場レビュー（位置に紐づく＝本名露出は特に危険） ----

function piiParking(): BikeParking
{
    return BikeParking::create([
        'name' => 'テスト駐車場', 'address' => '東京都港区1-1', 'prefecture' => '東京都', 'city' => '港区',
        'latitude' => 35.66, 'longitude' => 139.75, 'is_active' => true,
    ]);
}

it('parking review page shows the logged-in reviewer handle, not the real name', function () {
    $reviewer = piiUser('駐車場本名', 'parking_rider');
    $parking = piiParking();
    // 既存データ相当: nickname に本名が保存されていても表示側で出さない
    ParkingReview::create(['bike_parking_id' => $parking->id, 'user_id' => $reviewer->id, 'nickname' => '駐車場本名', 'rating' => 5, 'body' => '停めやすい']);

    $res = $this->get(route('parking.show', $parking->id))->assertOk()
        ->assertSee('parking_rider')   // ハンドル
        ->assertSee('停めやすい');
    expect($res->getContent())->not->toContain('駐車場本名'); // 本名(nickname)は出さない
});

it('parking review falls back to 名無しライダー for a logged-in user without a handle', function () {
    $reviewer = piiUser('ハンドル無し駐車場', null);
    $parking = piiParking();
    ParkingReview::create(['bike_parking_id' => $parking->id, 'user_id' => $reviewer->id, 'nickname' => 'ハンドル無し駐車場', 'rating' => 4, 'body' => 'まあまあ']);

    $res = $this->get(route('parking.show', $parking->id))->assertOk()->assertSee('名無しライダー');
    expect($res->getContent())->not->toContain('ハンドル無し駐車場');
});

it('parking review still shows a guest free-text nickname (no user_id)', function () {
    $parking = piiParking();
    ParkingReview::create(['bike_parking_id' => $parking->id, 'user_id' => null, 'nickname' => 'ゲスト太郎', 'rating' => 3, 'body' => 'ふつう']);

    $this->get(route('parking.show', $parking->id))->assertOk()->assertSee('ゲスト太郎');
});

it('parking review form does not prefill the real name into the nickname field', function () {
    $user = piiUser('フォーム本名', 'form_rider');
    $parking = piiParking();

    $html = $this->actingAs($user)->get(route('parking.show', $parking->id))->assertOk()->getContent();
    // nickname の value に本名が入っていない（ハンドルのみ prefill）
    expect($html)->not->toContain('value="フォーム本名"');
});

// ---- ライダースマップ ガイド（編集記事の author byline） ----

function piiGuide(?User $author): TouringGuide
{
    return TouringGuide::create([
        'author_id' => $author?->id,
        'title' => 'テストツーリング', 'slug' => 'test-touring-'.uniqid(), 'body' => '本文',
        'latitude' => 36.2, 'longitude' => 138.0, 'zoom_level' => 10, 'difficulty' => '初級',
        'prefecture' => '長野県', 'status' => 'published', 'published_at' => now(), 'reading_time_minutes' => 3,
    ]);
}

it('touring guide page shows the author handle (byline), not the real name', function () {
    $author = piiUser('ガイド本名', 'guide_writer');
    $guide = piiGuide($author);

    $res = $this->get(route('touring.show', $guide->slug))->assertOk()->assertSee('guide_writer');
    expect($res->getContent())->not->toContain('ガイド本名');
});

it('touring guide JSON-LD author is not the real name', function () {
    $author = piiUser('JSONLD本名', 'jsonld_writer');
    $guide = piiGuide($author);

    $html = $this->get(route('touring.show', $guide->slug))->assertOk()->getContent();
    // 構造化データの author に本名が出ない
    expect($html)->not->toContain('JSONLD本名')->toContain('jsonld_writer');
});

it('touring guide falls back to the MotoHub brand byline when author has no handle', function () {
    $author = piiUser('ノーハンドル著者', null);
    $guide = piiGuide($author);

    $res = $this->get(route('touring.show', $guide->slug))->assertOk()->assertSee('MotoHub');
    expect($res->getContent())->not->toContain('ノーハンドル著者');
});
