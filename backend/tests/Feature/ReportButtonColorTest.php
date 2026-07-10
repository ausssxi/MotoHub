<?php

use App\Models\BikeNews;
use App\Models\NewsComment;
use App\Models\Shop;
use App\Models\ShopAcceptanceReport;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * 通報ボタンの色をパージ済み bg-rose-500 から存在クラス bg-red-500 へ統一した回帰防止。
 * ・全 UGC 面の報告UI blade に bg-red-500 があり bg-rose-500 が無い（静的ガード＝描画不要）。
 * ・代表面（/shops/reviews・/news/{id}）で実際に赤ボタンが描画される。
 */

// ─────────── 静的ガード: 4面の報告UIがパージ済みクラスへ逆戻りしていない ───────────

it('uses build-present red (not purged rose) in every report-button surface', function () {
    $files = [
        'shops/reviews_index',
        'shops/show',
        'parking/show',
        'news/show',
    ];

    foreach ($files as $f) {
        $blade = file_get_contents(resource_path("views/{$f}.blade.php"));
        expect($blade)->toContain('bg-red-500')       // 存在クラスで塗る
            ->not->toContain('bg-rose-500')            // パージ済み solid ボタンへ逆戻りしない
            ->not->toContain('hover:text-rose-500')    // フラグの hover 色も red へ
            ->not->toContain('accent-rose-500');       // ラジオの accent も red へ
    }
});

// ─────────── 実描画: 代表面で赤ボタンが出る ───────────

it('renders a red report submit button on the shop reviews feed', function () {
    $shop = Shop::create([
        'name' => 'テスト店', 'prefecture' => '東京都', 'city' => '世田谷区',
        'address' => 'rb-'.uniqid(), 'shop_type' => 'dealer', 'source' => Shop::SOURCE_SCRAPER,
    ]);
    ShopAcceptanceReport::create([
        'shop_id' => $shop->id, 'comment' => '赤ボタン確認用コメント',
        'is_approved' => false, 'comment_approved' => true,
    ]);

    $this->get('/shops/reviews')->assertOk()
        ->assertSee('bg-red-500', false)
        ->assertDontSee('bg-rose-500', false);
});

it('renders a red report submit button on a news article', function () {
    $n = BikeNews::create([
        'title' => 'テストニュース', 'url' => 'https://motohub.jp/news/'.uniqid(),
        'source' => BikeNews::SOURCE_ORIGINAL, 'published_at' => now(),
    ]);
    NewsComment::create(['news_id' => $n->id, 'nickname' => '名無し', 'body' => '赤ボタン確認', 'is_approved' => true]);

    $this->get("/news/{$n->id}")->assertOk()
        ->assertSee('bg-red-500', false)
        ->assertDontSee('bg-rose-500', false);
});
