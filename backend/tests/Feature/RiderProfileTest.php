<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;

function profUser(string $realName = '本名タロウ', ?string $handle = 'rider_x', string $email = 'taro@example.com'): User
{
    return User::factory()->create(['name' => $realName, 'review_display_name' => $handle, 'email' => $email]);
}

function profModel(string $slug = 'pcx', string $name = 'PCX'): BikeModel
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);

    return BikeModel::where('slug', $slug)->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => $name, 'slug' => $slug]);
}

function profBike(User $user, bool $public, ?string $name = 'マイPCX'): MyBike
{
    return MyBike::create([
        'user_id' => $user->id, 'bike_model_id' => profModel()->id, 'name' => $name,
        'is_public' => $public, 'initial_odometer' => 0, 'current_odometer' => 12345,
    ]);
}

it('shows the public profile with the handle and the user public garages', function () {
    $user = profUser();
    $token = $user->ensurePublicToken();
    profBike($user, true, 'CB号');

    $this->get(route('riders.profile', $token))
        ->assertOk()
        ->assertSee('rider_x')
        ->assertSee('CB号')
        ->assertSee(route('garage.public.show', $user->myBikes()->first()->id), false);
});

it('never leaks the real name, email, or internal user id on the profile', function () {
    $user = profUser('漏れ太郎', 'rider_x', 'leak@example.com');
    $token = $user->ensurePublicToken();
    profBike($user, true);

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();

    expect($html)->not->toContain('漏れ太郎')
        ->not->toContain('leak@example.com')
        ->not->toContain('/riders/'.$user->id) // 連番idがURLに出ない
        ->and($token)->not->toBe((string) $user->id);
});

it('does not show private garages on the profile', function () {
    $user = profUser();
    $token = $user->ensurePublicToken();
    profBike($user, true, '公開号');
    profBike($user, false, 'ヒミツ号');

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();
    expect($html)->toContain('公開号')->not->toContain('ヒミツ号');
});

it('does not show another users garages on the profile', function () {
    $a = profUser('Aさん', 'rider_a');
    $tokenA = $a->ensurePublicToken();
    profBike($a, true, 'Aの愛車');

    $b = profUser('Bさん', 'rider_b', 'b@example.com');
    $b->ensurePublicToken();
    profBike($b, true, 'Bの愛車');

    $html = $this->get(route('riders.profile', $tokenA))->assertOk()->getContent();
    expect($html)->toContain('Aの愛車')->not->toContain('Bの愛車')->not->toContain('rider_b');
});

it('404s for an unknown token', function () {
    $this->get(route('riders.profile', 'doesnotexist123'))->assertNotFound();
});

it('404s when the user has a token but no public garages (graceful)', function () {
    $user = profUser();
    $token = $user->ensurePublicToken();
    profBike($user, false); // 非公開のみ

    $this->get(route('riders.profile', $token))->assertNotFound();
});

it('uses 名無しライダー when the owner has no handle (no real name)', function () {
    $user = profUser('名前バレ太郎', null);
    $token = $user->ensurePublicToken();
    profBike($user, true);

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();
    expect($html)->toContain('名無しライダー')->not->toContain('名前バレ太郎');
});

// ---- リンク差し込み点 ----

it('public garage page links the owner handle to the profile', function () {
    $user = profUser();
    $token = $user->ensurePublicToken();
    $bike = profBike($user, true);

    $this->get(route('garage.public.show', $bike->id))
        ->assertOk()
        ->assertSee(route('riders.profile', $token), false);
});

it('public garage index links the owner handle to the profile', function () {
    $user = profUser();
    $token = $user->ensurePublicToken();
    profBike($user, true);

    $this->get(route('garage.public.index'))
        ->assertOk()
        ->assertSee(route('riders.profile', $token), false);
});

// ---- トークン発番 ----

it('ensurePublicToken is idempotent and unique, not the sequential id', function () {
    $user = profUser();
    $t1 = $user->ensurePublicToken();
    $t2 = $user->fresh()->ensurePublicToken();

    expect($t1)->toBe($t2)                       // 冪等
        ->and(strlen($t1))->toBe(16)
        ->and($t1)->not->toBe((string) $user->id); // 連番idではない
});

it('publishing a garage assigns the owner a public token', function () {
    $user = profUser($real = '本名', null); // ハンドル未設定
    $bike = profBike($user, false);

    expect($user->public_token)->toBeNull();

    $this->actingAs($user)->post(route('mybikes.visibility', $bike->id), [
        'is_public' => 1, 'review_handle' => 'new_rider',
    ])->assertRedirect();

    expect($user->fresh()->public_token)->not->toBeNull();
});

// ---- 第1段集約: 車種レビュー ----

use App\Models\BikeNews;
use App\Models\NewsComment;
use App\Models\Review;

it('aggregates the users approved reviews with handle + link, hides unapproved', function () {
    $user = profUser('本名タロウ', 'rider_x');
    $token = $user->ensurePublicToken();
    profBike($user, true); // プロフィール公開条件（公開ガレージ1台）
    $model = profModel();

    Review::create(['bike_model_id' => $model->id, 'user_id' => $user->id, 'nickname' => '旧ニック', 'title' => '良いバイク', 'body' => '最高でした', 'rating' => 5, 'is_approved' => true]);
    Review::create(['bike_model_id' => $model->id, 'user_id' => $user->id, 'nickname' => '旧ニック', 'title' => '未承認レビュー', 'body' => 'pending', 'rating' => 3, 'is_approved' => false]);

    $html = $this->get(route('riders.profile', $token))->assertOk()
        ->assertSee('良いバイク')
        ->assertSee($model->seo_url, false)   // 車種ページへリンク
        ->getContent();

    // 未承認は出ない／保存済み nickname でなく現ハンドルを表示／本名は出ない
    expect($html)->not->toContain('未承認レビュー')
        ->not->toContain('旧ニック')
        ->not->toContain('本名タロウ')
        ->toContain('rider_x');
});

it('does not show another users reviews on the profile', function () {
    $a = profUser('Aさん', 'rider_a');
    $token = $a->ensurePublicToken();
    profBike($a, true);
    $b = profUser('Bさん', 'rider_b', 'b@example.com');
    $model = profModel();
    Review::create(['bike_model_id' => $model->id, 'user_id' => $b->id, 'nickname' => 'x', 'title' => 'Bのレビュー', 'body' => 'b', 'rating' => 4, 'is_approved' => true]);

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();
    expect($html)->not->toContain('Bのレビュー');
});

// ---- 第1段集約: ニュースコメント ----

function profNews(string $title): BikeNews
{
    return BikeNews::create(['title' => $title, 'url' => 'https://example.com/'.uniqid(), 'source' => 'test', 'published_at' => now()]);
}

it('aggregates the users news comments with handle + link', function () {
    $user = profUser('本名タロウ', 'rider_x');
    $token = $user->ensurePublicToken();
    profBike($user, true);
    $news = profNews('新型発表');
    NewsComment::create(['news_id' => $news->id, 'user_id' => $user->id, 'body' => 'かっこいい']);

    $html = $this->get(route('riders.profile', $token))->assertOk()
        ->assertSee('かっこいい')
        ->assertSee('新型発表')
        ->assertSee(route('news.show', $news->id), false)
        ->getContent();
    expect($html)->not->toContain('本名タロウ')->toContain('rider_x');
});

it('does not show another users news comments', function () {
    $a = profUser('Aさん', 'rider_a');
    $token = $a->ensurePublicToken();
    profBike($a, true);
    $b = profUser('Bさん', 'rider_b', 'b2@example.com');
    $news = profNews('ニュース');
    NewsComment::create(['news_id' => $news->id, 'user_id' => $b->id, 'body' => 'Bのコメント']);

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();
    expect($html)->not->toContain('Bのコメント');
});

it('paginates news comments (20 per page) and exposes page 2', function () {
    $user = profUser();
    $token = $user->ensurePublicToken();
    profBike($user, true);
    $news = profNews('まとめ記事');
    foreach (range(1, 25) as $i) {
        NewsComment::create(['news_id' => $news->id, 'user_id' => $user->id, 'body' => "コメント番号{$i}"]);
    }

    // 1ページ目: 20件。2ページ目: 残り5件。（同一timestampで順序は不定なので件数で検証）
    $p1 = $this->get(route('riders.profile', $token))->assertOk()->getContent();
    expect(substr_count($p1, 'コメント番号'))->toBe(20)
        ->and($p1)->toContain('comments=2'); // 次ページへのページネーションリンク
    $p2 = $this->get(route('riders.profile', $token).'?comments=2')->assertOk()->getContent();
    expect(substr_count($p2, 'コメント番号'))->toBe(5);
});

it('profile renders gracefully with zero reviews and zero comments (sections hidden)', function () {
    $user = profUser();
    $token = $user->ensurePublicToken();
    profBike($user, true);

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();
    expect($html)->not->toContain('のレビュー')      // セクション見出し非表示
        ->not->toContain('のニュースコメント');
});
