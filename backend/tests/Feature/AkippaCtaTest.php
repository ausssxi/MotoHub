<?php

declare(strict_types=1);

use App\Models\BikeParking;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function akippaParking(): BikeParking
{
    return BikeParking::create([
        'name' => 'テスト駐車場', 'address' => '東京都渋谷区1-2-3',
        'latitude' => 35.6595, 'longitude' => 139.7005,
        'prefecture' => '東京都', 'city' => '渋谷区', 'parking_type' => 'bike_only',
        'is_active' => true,
    ]);
}

it('shows the akippa CTA (link/rel/PR) on the detail page when AKIPPA_AFFILIATE_URL is set', function () {
    config(['parking.affiliate.akippa.url' => 'https://px.a8.net/svt/ejp?a_id=akippaTEST']);
    $p = akippaParking();

    $html = $this->get(route('parking.show', $p))->assertOk()->getContent();

    expect($html)->toContain('https://px.a8.net/svt/ejp?a_id=akippaTEST')  // A8クリックURL
        ->toContain('rel="nofollow sponsored noopener"')                    // アフィリrel
        ->toContain('PR・広告')                                             // 景表法PR表記
        ->toContain('予約できる駐車場を探す')                               // ボタン
        ->toContain('満車が心配なら');                                     // 見出し
});

it('hides the akippa CTA entirely when the url is not set (no fake button)', function () {
    config(['parking.affiliate.akippa.url' => '']);
    $p = akippaParking();

    $html = $this->get(route('parking.show', $p))->assertOk()->getContent();

    expect($html)->not->toContain('予約できる駐車場を探す')
        ->not->toContain('rel="nofollow sponsored');
});
