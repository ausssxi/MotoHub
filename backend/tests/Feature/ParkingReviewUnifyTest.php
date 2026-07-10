<?php

use App\Models\BikeParking;
use App\Models\ParkingReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

function unifyParking(): BikeParking
{
    return BikeParking::create([
        'name' => 'テスト駐輪場', 'address' => 'テスト住所1-2-3',
        'latitude' => 35.6595, 'longitude' => 139.7005,
        'prefecture' => '東京都', 'city' => '渋谷区', 'parking_type' => 'bike_only',
    ]);
}

// ─────────── A: 投稿導線をレビュー1つに統合 ───────────

it('removes the used-counter dead-end (button and route gone)', function () {
    $p = unifyParking();

    $this->get("/parking/{$p->id}")->assertOk()
        ->assertDontSee('使った！')
        ->assertDontSee('人が「使った」と回答');

    expect(Route::has('parking.used'))->toBeFalse(); // 押して終わりの導線ルートを撤去
});

it('keeps a single review input where tapping a star opens the comment field', function () {
    $p = unifyParking();

    $html = $this->get("/parking/{$p->id}")->assertOk()->getContent();

    // 星タップ→レビューフォームが開く配線（setRating が review-detail-form を開く）
    expect($html)->toContain('この駐車場のレビューを書く')
        ->toContain('setRating(')
        ->toContain('id="review-detail-form"')
        ->toContain('name="body"')                       // 一言コメント欄
        ->toContain(route('parking.review', $p));        // 唯一の投稿先
});

// ─────────── B: 口コミを上に上げる（料金シミュレーターより前） ───────────

it('surfaces the review summary above the price simulator', function () {
    $p = unifyParking();
    $p->update(['price_per_hour' => 200]); // 料金シミュレーターを描画させる
    ParkingReview::create(['bike_parking_id' => $p->id, 'nickname' => '名無し', 'rating' => 4, 'body' => '停めやすい', 'is_approved' => true]);
    $p->update(['reviews_count' => 1, 'avg_rating' => 4]);

    $html = $this->get("/parking/{$p->id}")->assertOk()->getContent();

    $summaryPos = strpos($html, '利用者の口コミ');
    $simulatorPos = strpos($html, '料金シミュレーター');
    expect($summaryPos)->not->toBeFalse()
        ->and($simulatorPos)->not->toBeFalse()
        ->and($summaryPos)->toBeLessThan($simulatorPos); // 口コミサマリーが料金計算より上
});

it('summary anchors to the review section (#reviews)', function () {
    $p = unifyParking();
    ParkingReview::create(['bike_parking_id' => $p->id, 'nickname' => '名無し', 'rating' => 5, 'body' => '広い', 'is_approved' => true]);
    $p->update(['reviews_count' => 1, 'avg_rating' => 5]);

    $this->get("/parking/{$p->id}")->assertOk()
        ->assertSee('href="#reviews"', false)
        ->assertSee('id="reviews"', false);
});

// ─────────── 集計・0件・回帰 ───────────

it('keeps avg_rating / reviews_count correct after posting (unchanged aggregation)', function () {
    $p = unifyParking();

    $this->post("/parking/{$p->id}/review", ['rating' => 4, 'body' => 'a']);
    $this->post("/parking/{$p->id}/review", ['rating' => 2, 'body' => 'b']);

    $p->refresh();
    expect($p->reviews_count)->toBe(2)->and((float) $p->avg_rating)->toBe(3.0);

    // 即反映で記事に出る（回帰）
    $this->get("/parking/{$p->id}")->assertOk()->assertSee('a')->assertSee('b');
});

it('keeps the zero-review welcome message', function () {
    $p = unifyParking();

    $this->get("/parking/{$p->id}")->assertOk()
        ->assertSee('最初の一人になって、停めやすさを教えてください。');
});

it('keeps review safety valves intact (ng word still blocks)', function () {
    config()->set('ng_words.words', ['死ね']);
    $p = unifyParking();

    $this->post("/parking/{$p->id}/review", ['rating' => 1, 'body' => '管理人は死ね'])
        ->assertSessionHasErrors('body');
    expect(ParkingReview::count())->toBe(0);
});
