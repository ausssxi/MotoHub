<?php

use App\Models\BikeParking;
use App\Models\ParkingReview;
use App\Services\Parking\ParkingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function feedPark(array $attrs = []): BikeParking
{
    return BikeParking::create(array_merge([
        'name' => 'テスト駐輪場', 'address' => '東京都渋谷区1-2-3',
        'latitude' => 35.6595, 'longitude' => 139.7005,
        'prefecture' => '東京都', 'city' => '渋谷区', 'parking_type' => 'bike_only',
    ], $attrs));
}

function feedReview(BikeParking $p, string $body, bool $approved, int $rating = 4, ?string $at = null): ParkingReview
{
    $r = ParkingReview::create([
        'bike_parking_id' => $p->id, 'nickname' => '名無し', 'rating' => $rating,
        'body' => $body, 'is_approved' => $approved,
    ]);
    if ($at) {
        $r->created_at = $at;
        $r->save();
    }

    return $r;
}

// ─────────── 公開・新着順 ───────────

it('shows approved reviews newest-first and hides unapproved ones', function () {
    $p = feedPark();
    feedReview($p, '古い公開レビュー', true, 4, '2026-07-01 10:00:00');
    feedReview($p, '新しい公開レビュー', true, 5, '2026-07-09 10:00:00');
    feedReview($p, '承認待ちレビュー', false, 3, '2026-07-10 10:00:00');

    $res = $this->get('/parking/reviews')->assertOk();
    $res->assertSee('新しい公開レビュー')->assertSee('古い公開レビュー')
        ->assertDontSee('承認待ちレビュー'); // 非公開は出さない

    $c = $res->getContent();
    expect(strpos($c, '新しい公開レビュー'))->toBeLessThan(strpos($c, '古い公開レビュー')); // 新着順
});

it('excludes star-only reviews with no body', function () {
    $p = feedPark();
    feedReview($p, '', true, 5); // 本文なし（星のみ）

    expect(app(ParkingService::class)->getRecentReviewsFeed()->total())->toBe(0);
});

// ─────────── リンク・通報 ───────────

it('links the card to the parking page and the location to the area page', function () {
    $p = feedPark(['name' => 'リンク先駐輪場']);
    feedReview($p, 'リンクの口コミ', true);

    $this->get('/parking/reviews')->assertOk()
        ->assertSee('リンク先駐輪場')
        ->assertSee(route('parking.show', $p), false)
        ->assertSee(route('parking.area.prefecture', '東京都'), false);
});

it('wires the report button to the polymorphic reports endpoint (parking_review)', function () {
    $p = feedPark();
    feedReview($p, '通報対象の口コミ', true);

    $this->get('/parking/reviews')->assertOk()
        ->assertSee('parking_review', false)
        ->assertSee(route('reports.store'), false);
});

// ─────────── カニバリ維持 ───────────

it('does not leak price/stock data into the feed (cannibalization guard)', function () {
    $p = feedPark(['price_per_hour' => 777]);
    feedReview($p, '料金は出さない口コミ', true);

    $this->get('/parking/reviews')->assertOk()
        ->assertSee('料金は出さない口コミ')
        ->assertDontSee('777'); // 料金の実データはフィードに出さない
});

// ─────────── 空状態・ルート ───────────

it('renders an empty state when there are no reviews', function () {
    $this->get('/parking/reviews')->assertOk()->assertSee('まだ口コミがありません');
});

it('exposes the parking.reviews route', function () {
    expect(\Illuminate\Support\Facades\Route::has('parking.reviews'))->toBeTrue();
});
