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

it('cleans dangling link phrases in all text fields for an akippa parking (200, phrases rewritten)', function () {
    config()->set('parking.affiliate.akippa.a8mat', '');

    $parking = BikeParking::create([
        'name' => 'akippa予約駐車場2',
        'address' => 'テスト住所10-11-12',
        'latitude' => 35.6595,
        'longitude' => 139.7005,
        'prefecture' => '東京都',
        'city' => '渋谷区',
        'parking_type' => 'bike_only',
        'management_company' => 'akippa株式会社',
        'notes' => 'ご予約は https://www.akippa.com/parking/f5eb4aa1 から。',
        'closed_days' => 'なし（詳細は備考のURLのリンク先をご確認ください）',
        'vehicle_restriction' => '排気量による制限はありません。詳細はリンク先をご確認ください。',
    ]);

    $this->get("/parking/{$parking->id}")
        ->assertOk()
        // URLを持たない欄（定休日・排気量制限）も整形される。
        ->assertSee('なし（詳細は akippa の予約ページでご確認ください）')
        ->assertSee('排気量による制限はありません。詳細は akippa の予約ページでご確認ください。')
        // 宙に浮いた誘導文は残らない。
        ->assertDontSee('リンク先をご確認ください')
        ->assertDontSee('備考のURL');
});

it('does not touch dangling phrases for a non-akippa parking', function () {
    $parking = BikeParking::create([
        'name' => '一般コインパーキング',
        'address' => 'テスト住所13-14-15',
        'latitude' => 35.6595,
        'longitude' => 139.7005,
        'prefecture' => '東京都',
        'city' => '渋谷区',
        'parking_type' => 'bike_only',
        'management_company' => 'パラカ株式会社',
        'closed_days' => 'なし（詳細は備考のURLのリンク先をご確認ください）',
    ]);

    $this->get("/parking/{$parking->id}")
        ->assertOk()
        // akippa以外は原文のまま。
        ->assertSee('なし（詳細は備考のURLのリンク先をご確認ください）')
        ->assertDontSee('akippa の予約ページで');
});
