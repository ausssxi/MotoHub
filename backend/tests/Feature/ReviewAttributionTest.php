<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Http;

function attrModel(): BikeModel
{
    $mfr = Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);

    return BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx', 'displacement' => 125]);
}

function postReview(int $modelId, array $extra = []): \Illuminate\Testing\TestResponse
{
    return test()->post("/bikes/models/{$modelId}/reviews", array_merge([
        'rating' => 5, 'title' => '良いバイク', 'body' => '乗りやすい。', 'recaptcha_token' => 'x',
    ], $extra));
}

it('guest review: nickname saved, user_id null, no badge', function () {
    Http::fake(['www.google.com/*' => Http::response(['success' => true, 'score' => 0.9])]);
    $model = attrModel();

    postReview($model->id, ['nickname' => 'ゲスト太郎']);

    $review = Review::first();
    expect($review->nickname)->toBe('ゲスト太郎');
    expect($review->user_id)->toBeNull();
});

it('logged-in without handle: sets review_display_name, attributes user_id, nickname=handle', function () {
    $user = User::factory()->create(['name' => '本名太郎', 'review_display_name' => null]);
    $model = attrModel();

    $this->actingAs($user);
    postReview($model->id, ['review_handle' => 'rider_x']);

    $review = Review::first();
    expect($review->user_id)->toBe($user->id);
    expect($review->nickname)->toBe('rider_x');
    expect($user->fresh()->review_display_name)->toBe('rider_x');
});

it('logged-in with existing handle: reuses it, no handle input needed', function () {
    $user = User::factory()->create(['name' => '本名太郎', 'review_display_name' => 'rider_x']);
    $model = attrModel();

    $this->actingAs($user);
    postReview($model->id); // review_handle を送らない

    $review = Review::first();
    expect($review->user_id)->toBe($user->id);
    expect($review->nickname)->toBe('rider_x');
});

it('LEAK GUARD: User->name never appears in stored review, form, or public hub output', function () {
    $user = User::factory()->create(['name' => '本名太郎', 'review_display_name' => 'rider_x']);
    $model = attrModel();

    // 1) storeReview 経由でも保存値に本名が入らない（ハンドルのスナップショット）
    $this->actingAs($user);
    postReview($model->id);
    $review = Review::first();
    expect($review->nickname)->toBe('rider_x');
    expect($review->nickname)->not->toBe('本名太郎');

    // 2) 投稿フォーム partial（ログイン状態）に本名が出ない（ハンドルのみ）
    $form = view('bikes.partials.review_author_field')->render();
    expect($form)->toContain('rider_x');
    expect($form)->not->toContain('本名太郎');

    // 3) 公開ハブ（第三者＝ゲスト視点）の出力に本名が出ない。ハブは users を join しない。
    //    ※ログイン中の自分のヘッダー名表示はレビュー漏洩ではないため、ゲストで検証する。
    auth()->logout();
    $this->get('/bikes/reviews')
        ->assertOk()
        ->assertSee('rider_x')
        ->assertDontSee('本名太郎')
        ->assertSee('ログイン'); // ログインユーザーバッジ
});

it('handle未設定ゲスト用フォームは name=nickname、未設定ログインは review_handle（本名prefillなし）', function () {
    // ゲスト
    $guestForm = view('bikes.partials.review_author_field')->render();
    expect($guestForm)->toContain('name="nickname"');

    // ログイン・ハンドル未設定
    $user = User::factory()->create(['name' => '本名太郎', 'review_display_name' => null]);
    $this->actingAs($user);
    $loginForm = view('bikes.partials.review_author_field')->render();
    expect($loginForm)->toContain('name="review_handle"');
    expect($loginForm)->not->toContain('本名太郎'); // 本名で prefill しない
    expect($loginForm)->not->toContain('value="本名太郎"');
});

it('existing anonymous reviews (user_id null) render unchanged with no badge', function () {
    $model = attrModel();
    Review::create(['bike_model_id' => $model->id, 'user_id' => null, 'nickname' => '匿名ライダー', 'title' => 't', 'body' => 'b', 'rating' => 4, 'is_approved' => true]);

    $this->get('/bikes/reviews')
        ->assertOk()
        ->assertSee('匿名ライダー')
        ->assertDontSee('ログインユーザー'); // バッジ無し
});
