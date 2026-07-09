<?php

use App\Models\BikeModel;
use App\Models\BikeParking;
use App\Models\Manufacturer;
use App\Models\ParkingReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function nudgeParking(): BikeParking
{
    return BikeParking::create([
        'name' => 'テスト駐輪場', 'address' => 'テスト住所1-2-3',
        'latitude' => 35.6595, 'longitude' => 139.7005,
        'prefecture' => '東京都', 'city' => '渋谷区', 'parking_type' => 'bike_only',
    ]);
}

// ─────────── 駐車場: 空状態の呼び水化（0件のみ） ───────────

it('shows the welcoming empty state on a parking with zero reviews', function () {
    $p = nudgeParking();

    $this->get("/parking/{$p->id}")->assertOk()
        ->assertSee('最初の一人になって、停めやすさを教えてください。')
        ->assertDontSee('最初のレビューを投稿しましょう'); // 旧・事実通知トーンは消えた
});

it('does not show the welcome nudge once a parking has a review', function () {
    $p = nudgeParking();
    ParkingReview::create(['bike_parking_id' => $p->id, 'nickname' => '名無し', 'rating' => 4, 'body' => '停めやすい', 'is_approved' => true]);
    $p->update(['reviews_count' => 1, 'avg_rating' => 4]);

    $this->get("/parking/{$p->id}")->assertOk()
        ->assertDontSee('最初の一人になって')  // 1件以上では歓迎文を出さない
        ->assertSee('停めやすい');              // 通常表示
});

// ─────────── 駐車場: 投稿直後の「見えた」体験 ───────────

it('reflects a new parking review immediately and wires the highlight/scroll', function () {
    $p = nudgeParking();

    $res = $this->followingRedirects()
        ->post("/parking/{$p->id}/review", ['rating' => 5, 'body' => '投稿直後の実感テスト'])
        ->assertOk();

    $res->assertSee('投稿直後の実感テスト')       // 即反映（自分の投稿が見える）
        ->assertSee('id="parking-review-list"', false) // ハイライト対象コンテナ
        ->assertSee('scrollIntoView', false);          // スクロール＋ハイライトの配線
});

// ─────────── 車両: 空状態の呼び水化（静的ガード・model_detailはSQLite描画不可） ───────────

it('has the welcoming empty state and highlight wiring in the model_detail blade', function () {
    $blade = file_get_contents(resource_path('views/bikes/model_detail.blade.php'));

    expect($blade)->toContain('まだこの車種のレビューはありません。')
        ->toContain('最初のオーナーレビューは目立ちます')
        ->toContain('id="model-review-list"')   // ハイライト対象
        ->toContain('scrollIntoView')           // 投稿直後スクロール
        ->and($blade)->not->toContain('最初のレビューを投稿してみませんか？'); // 旧文言は消えた
});

// ─────────── 車両: 投稿直後の「見えた」合図（review_posted フラッシュ） ───────────

it('flashes review_posted after a vehicle review so the reload can scroll/highlight', function () {
    $mfr = new Manufacturer(['slug' => 'honda']);
    $mfr->name = 'ホンダ';
    $mfr->save();
    $model = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'テスト車', 'slug' => 'test-bike']);

    $user = User::factory()->create(['review_display_name' => 'テスター']); // reCAPTCHA不要・初回ハンドル済み

    // 非JSON（通常フォーム）投稿 → リダイレクトに review_posted 合図
    $this->actingAs($user)
        ->post("/bikes/models/{$model->id}/reviews", ['rating' => 5, 'title' => 'よい', 'body' => '乗りやすい'])
        ->assertRedirect()
        ->assertSessionHas('review_posted', 1);
});
