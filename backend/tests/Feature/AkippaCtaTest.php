<?php

declare(strict_types=1);

use App\Models\BikeParking;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function akippaParking(array $attrs = []): BikeParking
{
    return BikeParking::create(array_merge([
        'name' => 'テスト駐車場', 'address' => '東京都渋谷区1-2-3',
        'latitude' => 35.6595, 'longitude' => 139.7005,
        'prefecture' => '東京都', 'city' => '渋谷区', 'parking_type' => 'bike_only', 'is_active' => true,
        'management_company' => 'akippa株式会社',
        'source_url' => 'https://www.akippa.com/parking/hash1?utm_source=jmpsa',
    ], $attrs));
}

it('shows a per-parking deeplink CTA on an akippa parking (A8MAT set) and removes the raw akippa direct link', function () {
    config(['parking.affiliate.akippa.a8mat' => 'ABC123', 'parking.affiliate.akippa.url' => '']);
    $p = akippaParking();

    $html = $this->get(route('parking.show', $p))->assertOk()->getContent();

    expect($html)->toContain('px.a8.net/svt/ejp?a8mat=ABC123')   // A8ディープリンク
        ->toContain('a8ejpredirect=')                            // 飛び先を指定
        ->toContain('rel="nofollow sponsored noopener"')
        ->toContain('PR・広告')
        ->toContain('この駐車場を予約')                          // ディープリンク時コピー
        ->not->toContain('href="https://www.akippa.com');        // 生の直リンク（出典/CTA）が消えている＝成果漏れ防止
});

it('shows the generic fallback CTA on an akippa parking when A8MAT is unset but a generic url is set', function () {
    config(['parking.affiliate.akippa.a8mat' => '', 'parking.affiliate.akippa.url' => 'https://generic.example/akippa']);
    $p = akippaParking();

    $html = $this->get(route('parking.show', $p))->assertOk()->getContent();

    expect($html)->toContain('https://generic.example/akippa')
        ->toContain('予約できる駐車場を探す')                    // 汎用フォールバック時コピー
        ->toContain('rel="nofollow sponsored noopener"');
});

it('hides the CTA on a non-akippa parking (context mismatch) and keeps the 出典 link', function () {
    config(['parking.affiliate.akippa.a8mat' => 'ABC123', 'parking.affiliate.akippa.url' => 'https://generic.example/akippa']);
    $p = akippaParking(['management_company' => 'パラカ株式会社', 'source_url' => 'https://times-parking.example/lot/1']);

    $html = $this->get(route('parking.show', $p))->assertOk()->getContent();

    expect($html)->not->toContain('満車が心配なら')                       // CTA非表示
        ->not->toContain('rel="nofollow sponsored')
        ->toContain('href="https://times-parking.example/lot/1"');       // 非akippa出典リンクは維持
});
