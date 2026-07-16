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

// ---- C: 駐車場レビュー集約＋オプトアウト ----

use App\Models\BikeParking;
use App\Models\ParkingReview;
use App\Models\TouringGuide;

function profParking(): BikeParking
{
    return BikeParking::create([
        'name' => 'テスト駐車場', 'address' => '東京都港区1-1', 'prefecture' => '東京都', 'city' => '港区',
        'latitude' => 35.66, 'longitude' => 139.75, 'is_active' => true,
    ]);
}

it('aggregates the users parking reviews (default ON) with display_name + link, no real name', function () {
    $user = profUser('本名タロウ', 'rider_x');
    $token = $user->ensurePublicToken();
    profBike($user, true);
    $parking = profParking();
    // 既存データ相当: nickname に本名が入っていても display_name で出さない
    ParkingReview::create(['bike_parking_id' => $parking->id, 'user_id' => $user->id, 'nickname' => '本名タロウ', 'rating' => 5, 'body' => '停めやすい駐車場']);

    expect($user->profile_show_parking_reviews)->toBeTrue(); // 既定ON
    $html = $this->get(route('riders.profile', $token))->assertOk()
        ->assertSee('駐車場レビュー')
        ->assertSee('停めやすい駐車場')
        ->assertSee('テスト駐車場')
        ->assertSee(route('parking.show', $parking->id), false)
        ->getContent();
    expect($html)->not->toContain('本名タロウ')->toContain('rider_x');
});

it('hides the parking section entirely when the user opts out', function () {
    $user = profUser();
    $user->update(['profile_show_parking_reviews' => false]);
    $token = $user->ensurePublicToken();
    profBike($user, true);
    $parking = profParking();
    ParkingReview::create(['bike_parking_id' => $parking->id, 'user_id' => $user->id, 'nickname' => 'x', 'rating' => 4, 'body' => '秘密の駐車場メモ']);

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();
    expect($html)->not->toContain('駐車場レビュー')   // セクション見出し非表示
        ->not->toContain('秘密の駐車場メモ');
});

it('does not show another users parking reviews', function () {
    $a = profUser('Aさん', 'rider_a');
    $token = $a->ensurePublicToken();
    profBike($a, true);
    $b = profUser('Bさん', 'rider_b', 'pb@example.com');
    $parking = profParking();
    ParkingReview::create(['bike_parking_id' => $parking->id, 'user_id' => $b->id, 'nickname' => 'x', 'rating' => 3, 'body' => 'Bの駐車場レビュー']);

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();
    expect($html)->not->toContain('Bの駐車場レビュー');
});

it('parking section is hidden when there are zero reviews (graceful)', function () {
    $user = profUser();
    $token = $user->ensurePublicToken();
    profBike($user, true);

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();
    expect($html)->not->toContain('駐車場レビュー');
});

// ---- C: 表示設定の保存（オプトアウトUI） ----

it('user can toggle off the parking reviews visibility setting', function () {
    $user = profUser();
    expect($user->profile_show_parking_reviews)->toBeTrue();

    // チェックボックス未送信＝OFF
    $this->actingAs($user)->patch(route('profile.visibility'), [])->assertRedirect();
    expect($user->fresh()->profile_show_parking_reviews)->toBeFalse();

    // 再度ON
    $this->actingAs($user)->patch(route('profile.visibility'), ['profile_show_parking_reviews' => '1'])->assertRedirect();
    expect($user->fresh()->profile_show_parking_reviews)->toBeTrue();
});

it('profile settings page shows the visibility toggle reflecting current state', function () {
    $user = profUser();

    $this->actingAs($user)->get(route('profile.edit'))
        ->assertOk()
        ->assertSee('プロフィール表示設定')
        ->assertSee('駐車場レビューをプロフィールに表示する');
});

// ---- D: 執筆記事（writer のみ） ----

function profGuide(?User $author, string $status = 'published', string $title = 'テストガイド'): TouringGuide
{
    return TouringGuide::create([
        'author_id' => $author?->id, 'title' => $title, 'slug' => 'g-'.uniqid(), 'body' => '本文',
        'latitude' => 36.2, 'longitude' => 138.0, 'zoom_level' => 10, 'difficulty' => '初級',
        'prefecture' => '長野県', 'status' => $status, 'published_at' => now(), 'reading_time_minutes' => 3,
    ]);
}

it('shows the writing section for an author with published guides, linking to each guide', function () {
    $user = profUser('本名タロウ', 'writer_x');
    $token = $user->ensurePublicToken();
    profBike($user, true);
    $guide = profGuide($user, 'published', '奥多摩ツーリング');
    profGuide($user, 'draft', '下書きガイド'); // 下書きは出さない

    $html = $this->get(route('riders.profile', $token))->assertOk()
        ->assertSee('執筆記事')
        ->assertSee('奥多摩ツーリング')
        ->assertSee(route('touring.show', $guide->slug), false)
        ->getContent();
    expect($html)->not->toContain('下書きガイド')->not->toContain('本名タロウ');
});

it('does not show the writing section for a normal user (not an author)', function () {
    $user = profUser();           // 著者でない一般ユーザー
    $token = $user->ensurePublicToken();
    profBike($user, true);
    // 別ユーザーが書いた記事は出ない
    $other = profUser('Wさん', 'writer_other', 'w@example.com');
    profGuide($other, 'published', '他人の記事');

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();
    expect($html)->not->toContain('執筆記事')->not->toContain('他人の記事');
});

// ---- E: ショップレビュー（店ページで公開済みコメントのみ・comment_approved） ----

use App\Models\Shop;
use App\Models\ShopAcceptanceReport;

function profShop(string $name = 'テスト整備店'): Shop
{
    return Shop::create([
        'name' => $name, 'prefecture' => '東京都', 'city' => '世田谷区',
        'address' => 'sh-'.uniqid(), 'shop_type' => 'dealer', 'source' => Shop::SOURCE_SCRAPER,
    ]);
}

it('aggregates the users approved shop reviews with handle + shop link, hides comment-unapproved', function () {
    $user = profUser('本名タロウ', 'rider_x');
    $token = $user->ensurePublicToken();
    profBike($user, true);
    $shop = profShop('公開整備店');

    // comment_approved=true＝店ページで公開済み。is_approved(事実系)とは独立。
    ShopAcceptanceReport::create([
        'shop_id' => $shop->id, 'user_id' => $user->id, 'comment' => '対応が丁寧でした',
        'submitter_name' => '旧サブミッタ名', 'is_approved' => false, 'comment_approved' => true,
    ]);
    // comment_approved=false＝店ページでも非公開（承認待ち/運営非表示）。プロフィールにも出さない。
    ShopAcceptanceReport::create([
        'shop_id' => $shop->id, 'user_id' => $user->id, 'comment' => '承認待ちコメント',
        'is_approved' => false, 'comment_approved' => false,
    ]);

    $html = $this->get(route('riders.profile', $token))->assertOk()
        ->assertSee('ショップレビュー')
        ->assertSee('対応が丁寧でした')
        ->assertSee('公開整備店')
        ->assertSee(route('shops.show', $shop->id), false)   // 店ページへリンク
        ->getContent();

    // 非公開コメントは出ない／保存済み submitter_name でなく現ハンドルを表示／本名は出ない
    expect($html)->not->toContain('承認待ちコメント')
        ->not->toContain('旧サブミッタ名')
        ->not->toContain('本名タロウ')
        ->toContain('rider_x');
});

it('does not show another users shop reviews on the profile', function () {
    $a = profUser('Aさん', 'rider_a');
    $token = $a->ensurePublicToken();
    profBike($a, true);
    $b = profUser('Bさん', 'rider_b', 'sb@example.com');
    $shop = profShop();
    ShopAcceptanceReport::create([
        'shop_id' => $shop->id, 'user_id' => $b->id, 'comment' => 'Bのショップレビュー',
        'is_approved' => true, 'comment_approved' => true,
    ]);

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();
    expect($html)->not->toContain('Bのショップレビュー');
});

it('does not show anonymous (no user_id) shop reviews on the profile', function () {
    $user = profUser();
    $token = $user->ensurePublicToken();
    profBike($user, true);
    $shop = profShop();
    ShopAcceptanceReport::create([
        'shop_id' => $shop->id, 'user_id' => null, 'comment' => '匿名のコメント',
        'is_approved' => true, 'comment_approved' => true,
    ]);

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();
    expect($html)->not->toContain('匿名のコメント');
});

it('shop review section is hidden when there are zero approved comments (graceful)', function () {
    $user = profUser();
    $token = $user->ensurePublicToken();
    profBike($user, true);
    $shop = profShop();
    // コメント無し（フラグのみ）は集約対象外
    ShopAcceptanceReport::create([
        'shop_id' => $shop->id, 'user_id' => $user->id, 'comment' => null,
        'accepts_other_store' => true, 'is_approved' => true, 'comment_approved' => false,
    ]);

    $html = $this->get(route('riders.profile', $token))->assertOk()->getContent();
    expect($html)->not->toContain('ショップレビュー');
});
