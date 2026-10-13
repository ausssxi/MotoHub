<?php

declare(strict_types=1);

use App\Models\BikeParking;

/**
 * 回帰防止: prefecture が null（city はあり）の駐車場でも parking/show が 200 を返す。
 *
 * 旧バグ: show.blade.php のパンくずが @if($parking->city) のみで prefecture を
 * チェックせず route('parking.area.city', [null, $city]) を生成 → view描画中に
 * "Missing required parameter for [Route: parking.area.city]" 例外でページ全体が500。
 */
it('renders parking show with 200 when prefecture is null but city is set', function () {
    $parking = BikeParking::create([
        'name' => 'テスト駐輪場',
        'address' => 'テスト住所1-2-3',
        'latitude' => 35.6595,
        'longitude' => 139.7005,
        'prefecture' => null,
        'city' => '渋谷区',
        'parking_type' => 'bike_only',
    ]);

    $this->get("/parking/{$parking->id}")->assertOk();
});

it('renders parking show with 200 when both prefecture and city are set', function () {
    $parking = BikeParking::create([
        'name' => 'テスト駐輪場2',
        'address' => 'テスト住所4-5-6',
        'latitude' => 35.6595,
        'longitude' => 139.7005,
        'prefecture' => '東京都',
        'city' => '渋谷区',
        'parking_type' => 'bike_only',
    ]);

    $this->get("/parking/{$parking->id}")
        ->assertOk()
        ->assertSee('渋谷区');
});

it('renders akippa CTA with GA4 outbound tracking data attributes', function () {
    config()->set('parking.affiliate.akippa.a8mat', '');

    $parking = BikeParking::create([
        'name' => 'akippa予約駐車場',
        'address' => 'テスト住所7-8-9',
        'latitude' => 35.6595,
        'longitude' => 139.7005,
        'prefecture' => '東京都',
        'city' => '渋谷区',
        'parking_type' => 'bike_only',
        'management_company' => 'akippa株式会社',
        'notes' => '予約はこちら https://www.akippa.com/park/12345 からどうぞ',
    ]);

    $this->get("/parking/{$parking->id}")
        ->assertOk()
        ->assertSee('data-outbound="parking"', false)
        ->assertSee('data-partner="akippa"', false)
        ->assertSee('data-position="detail_cta"', false)
        ->assertSee('data-parking-id="'.$parking->id.'"', false)
        ->assertSee('data-prefecture="東京都"', false)
        ->assertSee('outbound_parking_click', false);
});
