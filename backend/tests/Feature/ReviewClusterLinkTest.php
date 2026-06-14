<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\Review;
use Illuminate\Support\Facades\DB;

it('listing detail page links to the reviews hub (detail -> hub)', function () {
    $mfr = Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx', 'displacement' => 125]);
    Review::create(['bike_model_id' => $model->id, 'nickname' => 'たろう', 'title' => '良い', 'body' => 'b', 'rating' => 5, 'is_approved' => true]);

    $siteId = DB::table('sites')->insertGetId(['name' => 'TestSite', 'created_at' => now(), 'updated_at' => now()]);
    $shopId = DB::table('shops')->insertGetId(['name' => 'テスト店', 'address' => '東京都テスト1-2-3', 'prefecture' => '東京都', 'created_at' => now(), 'updated_at' => now()]);
    $listingId = DB::table('listings')->insertGetId([
        'site_id' => $siteId, 'shop_id' => $shopId, 'bike_model_id' => $model->id, 'manufacturer_id' => $mfr->id,
        'total_price' => 500000, 'is_sold_out' => false, 'condition' => '中古',
        'source_url' => 'https://e.test/x', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->get("/bikes/{$listingId}")
        ->assertOk()
        ->assertSee('href="'.route('bikes.reviews_index').'"', false)
        ->assertSee('みんなのレビュー一覧を見る');
});

// 注: 車種詳細(model_detail)の「他の車種のレビューを見る」リンクは、model_detail ページ全体の
// HTTP描画が既存の resale 集計の MySQL専用関数(DATEDIFF)で sqlite では落ちるため自動描画テスト不可。
// ローカル実機 curl で /bikes/honda/pcx に /bikes/reviews への href があることを確認済み。
