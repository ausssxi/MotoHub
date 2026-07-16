<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\Review;
use App\Models\Shop;
use App\Models\ShopAcceptanceReport;
use App\Models\User;
use App\Repositories\Bike\ReviewRepository;
use App\Services\Shop\ShopAcceptanceService;
use Illuminate\Support\Facades\Storage;

/**
 * コメント/レビューの著者名の横にアバターを表示する（残surface）。
 * ★user_id が解決できる投稿のみ本人アバター、ゲスト(user_id null)は既定アイコン。
 *   ログイン投稿の表示名はハンドルのスナップショット＝匿名性は壊れない。N+1回避も検証。
 */
function avatarReviewUser(string $handle, string $path = 'avatars/9/pic.jpg'): User
{
    return User::factory()->create([
        'name' => '本名タロウ', 'review_display_name' => $handle, 'avatar_path' => $path,
    ]);
}

function avatarReviewModel(): BikeModel
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);

    return BikeModel::where('slug', 'cb400')->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'CB400', 'slug' => 'cb400']);
}

// ---- N+1 回避（eager load） ----

it('eager loads the review author user in the feed/latest queries (no N+1)', function () {
    $model = avatarReviewModel();
    $u = avatarReviewUser('rider_x');
    Review::create(['bike_model_id' => $model->id, 'user_id' => $u->id, 'nickname' => 'rider_x', 'title' => '良い', 'body' => 'b', 'rating' => 5, 'is_approved' => true]);

    $repo = app(ReviewRepository::class);

    // user が eager load 済み＝一覧描画で行ごとに追加クエリを撃たない
    expect($repo->getFeed()->first()->relationLoaded('user'))->toBeTrue()
        ->and($repo->getLatest(6)->first()->relationLoaded('user'))->toBeTrue()
        ->and($repo->getLatestByModelId($model->id, 3)->first()->relationLoaded('user'))->toBeTrue();
});

// ---- レビュー一覧ページ：本人アバター表示／ゲストは既定 ----

it('shows the author avatar on the reviews index for a logged-in review, default for guest', function () {
    Storage::fake('public');
    $model = avatarReviewModel();
    $u = avatarReviewUser('rider_x', 'avatars/9/pic.jpg');

    Review::create(['bike_model_id' => $model->id, 'user_id' => $u->id, 'nickname' => 'rider_x', 'title' => 'ログイン投稿', 'body' => 'x', 'rating' => 5, 'is_approved' => true]);
    Review::create(['bike_model_id' => $model->id, 'user_id' => null, 'nickname' => 'ゲスト太郎', 'title' => 'ゲスト投稿', 'body' => 'y', 'rating' => 4, 'is_approved' => true]);

    $html = $this->get(route('bikes.reviews_index'))->assertOk()->getContent();

    // 本人アバターのURL（avatar_path）が出る／本名は出ない
    expect($html)->toContain('avatars/9/pic.jpg')
        ->toContain('rider_x')
        ->toContain('ゲスト太郎')      // ゲストは名前のみ（アバターはイニシャルにフォールバック）
        ->not->toContain('本名タロウ');
    // ゲスト投稿に他人のアバターURLが誤って付かない（avatar_path は1回だけ＝ログイン投稿のみ）
    expect(substr_count($html, 'avatars/9/pic.jpg'))->toBe(1);
});

// ---- ショップ集計コメント：avatar_url を配列に含む（#7） ----

it('includes the author avatar_url in the shop acceptance summary comments', function () {
    Storage::fake('public');
    $shop = Shop::create(['name' => 'テスト整備店', 'prefecture' => '東京都', 'city' => '世田谷区', 'address' => 'sh-'.uniqid(), 'shop_type' => 'dealer', 'source' => Shop::SOURCE_SCRAPER]);
    $u = avatarReviewUser('rider_x', 'avatars/9/shop.jpg');

    ShopAcceptanceReport::create(['shop_id' => $shop->id, 'user_id' => $u->id, 'submitter_name' => 'rider_x', 'comment' => 'ログイン投稿コメ', 'is_approved' => true, 'comment_approved' => true]);
    ShopAcceptanceReport::create(['shop_id' => $shop->id, 'user_id' => null, 'submitter_name' => 'ゲスト', 'comment' => '匿名コメ', 'is_approved' => true, 'comment_approved' => true]);

    $summary = app(ShopAcceptanceService::class)->getApprovedSummary($shop->id);
    $byName = collect($summary['comments'])->keyBy('name');

    expect($byName['rider_x']['avatar_url'])->toContain('avatars/9/shop.jpg') // 本人アバター
        ->and($byName['ゲスト']['avatar_url'])->toBeNull();                    // ゲストは null＝既定アイコン
});

it('eager loads the user in the cross-shop recent comments feed', function () {
    $shop = Shop::create(['name' => '店', 'prefecture' => '東京都', 'city' => '港区', 'address' => 'sh-'.uniqid(), 'shop_type' => 'dealer', 'source' => Shop::SOURCE_SCRAPER]);
    $u = avatarReviewUser('rider_x');
    ShopAcceptanceReport::create(['shop_id' => $shop->id, 'user_id' => $u->id, 'submitter_name' => 'rider_x', 'comment' => 'コメ', 'is_approved' => true, 'comment_approved' => true]);

    $feed = app(ShopAcceptanceService::class)->getRecentCommentsFeed(20);
    expect($feed->first()->relationLoaded('user'))->toBeTrue();
});
