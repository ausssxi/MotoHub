<?php

declare(strict_types=1);

use App\Models\BikeParking;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// id=8306 相当：akippa物件だが source_url は掲載元(jmpsa)。予約URLは notes に埋め込み。
function akippaParking(array $attrs = []): BikeParking
{
    return BikeParking::create(array_merge([
        'name' => 'テスト駐車場', 'address' => '東京都渋谷区1-2-3',
        'latitude' => 35.6595, 'longitude' => 139.7005,
        'prefecture' => '東京都', 'city' => '渋谷区', 'parking_type' => 'bike_only', 'is_active' => true,
        'management_company' => 'akippa株式会社',
        'source_url' => 'https://www.jmpsa.or.jp/parking/123',
        'notes' => '▼ご利用の際は駐車場予約サービス「akippa」のサイトよりご予約ください。https://www.akippa.com/parking/hash8306?utm_source=jmpsa&utm_medium=referral&utm_campaign=jmpsa」',
    ], $attrs));
}

it('shows a per-parking deeplink CTA from notes and removes the raw akippa url from display (A8MAT set)', function () {
    config(['parking.affiliate.akippa.a8mat' => 'ABC123', 'parking.affiliate.akippa.url' => '']);
    $p = akippaParking();

    $html = $this->get(route('parking.show', $p))->assertOk()->getContent();

    expect($html)->toContain('px.a8.net/svt/ejp?a8mat=ABC123')      // A8ディープリンク
        ->toContain('a8ejpredirect=')
        ->toContain('この駐車場を予約')                            // ディープリンク時コピー
        ->toContain('ご予約ください')                              // notes 文言は残る
        ->not->toContain('href="https://www.akippa.com')           // 生の直リンク無し
        ->not->toContain('www.akippa.com/parking/hash8306');       // notes内の生URL文字列が消えている（成果漏れ防止）
});

it('shows a raw non-affiliate direct link to the parking page when A8MAT is unset (akippa deaffiliated)', function () {
    config(['parking.affiliate.akippa.a8mat' => '']);
    $p = akippaParking();

    $html = $this->get(route('parking.show', $p))->assertOk()->getContent();

    // その駐車場のakippaページへ素で飛ぶ（A8トラッキング無し・PR表記無し・rel は nofollow noopener）。
    expect($html)->toContain('href="https://www.akippa.com/parking/hash8306')  // 個別ページへの直リンク
        ->toContain('この駐車場を予約')                              // 個別ページ導線のコピー
        ->toContain('rel="nofollow noopener"')                       // sponsored は付かない
        ->not->toContain('px.a8.net')                                // アフィリリダイレクト無し
        ->not->toContain('rel="nofollow sponsored noopener"')        // sponsored 無し
        ->not->toContain('PR・広告');                                // PR表記無し
});

it('hides the CTA on a non-akippa parking (context mismatch) and keeps the source 出典 link', function () {
    config(['parking.affiliate.akippa.a8mat' => 'ABC123', 'parking.affiliate.akippa.url' => 'https://generic.example/akippa']);
    $p = akippaParking(['management_company' => 'パラカ株式会社', 'notes' => '普通の備考', 'source_url' => 'https://times-parking.example/lot/1']);

    $html = $this->get(route('parking.show', $p))->assertOk()->getContent();

    expect($html)->not->toContain('満車が心配なら')                        // CTA非表示
        ->not->toContain('rel="nofollow sponsored')
        ->toContain('href="https://times-parking.example/lot/1"');        // 非akippa出典リンクは維持
});
