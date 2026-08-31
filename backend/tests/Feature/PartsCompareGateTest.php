<?php

declare(strict_types=1);

use App\Support\RakutenRateGate;
use App\Support\YahooRateGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

/**
 * PartsController::compare() の楽天呼び出しが RakutenRateGate を通ることの回帰テスト。
 * この pool 経路だけがゲートを迂回して429を招き、ルール遵守側（BikePartsService）を
 * ブレーカーで巻き添えにしていた不具合の再発防止。
 */
beforeEach(function () {
    Cache::flush();
    config([
        'services.rakuten.app_id' => 'test-app-id',
        'services.rakuten.access_key' => 'test-access-key',
        'services.rakuten.item_search_url' => 'https://app.rakuten.co.jp/services/api/IchibaItem/Search/20220601',
        'services.yahoo_shopping.client_id' => 'test-yahoo-id',
    ]);
});

it('ブレーカー休止中は楽天を叩かず Yahoo だけ取得する', function () {
    Http::fake([
        '*rakuten*' => Http::response(['Items' => []], 200),
        '*yahooapis*' => Http::response(['hits' => []], 200),
    ]);

    // 楽天のブレーカーを立てる（他経路の429を受けた状態を再現）。
    app(RakutenRateGate::class)->pause('foo', 'test');
    expect(app(RakutenRateGate::class)->isPaused())->toBeTrue();

    $this->get('/parts/compare?keyword='.urlencode('バイク テスト'))->assertOk();

    // 迂回せず、休止中は楽天を叩かない。Yahoo はゲート対象外なので叩く。
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'rakuten'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'yahooapis'));
});

it('pool 経路で楽天が429を返したらブレーカーを立てる', function () {
    Http::fake([
        '*rakuten*' => Http::response('Rate limit is exceeded.', 429),
        '*yahooapis*' => Http::response(['hits' => []], 200),
    ]);

    expect(app(RakutenRateGate::class)->isPaused())->toBeFalse();

    $this->get('/parts/compare?keyword='.urlencode('バイク テスト'))->assertOk();

    // fetchRakuten() と同じく 429 で pause() が呼ばれ、ブレーカーが作動していること。
    expect(app(RakutenRateGate::class)->isPaused())->toBeTrue();
});

it('Yahoo のブレーカー休止中は Yahoo を叩かず 楽天だけ取得する', function () {
    Http::fake([
        '*rakuten*' => Http::response(['Items' => []], 200),
        '*yahooapis*' => Http::response(['hits' => []], 200),
    ]);

    // Yahoo のブレーカーを立てる（ProductSearchService が Yahoo を休止させた状態を再現）。
    app(YahooRateGate::class)->pause('foo', 'test');
    expect(app(YahooRateGate::class)->isPaused())->toBeTrue();

    $this->get('/parts/compare?keyword='.urlencode('バイク テスト'))->assertOk();

    // 迂回せず、休止中は Yahoo を叩かない。楽天は休止していないので叩く。
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'yahooapis'));
    Http::assertSent(fn ($request) => str_contains($request->url(), 'rakuten'));
});

it('pool 経路で Yahoo が429を返したらブレーカーを立てる', function () {
    Http::fake([
        '*rakuten*' => Http::response(['Items' => []], 200),
        '*yahooapis*' => Http::response('Rate limit is exceeded.', 429),
    ]);

    expect(app(YahooRateGate::class)->isPaused())->toBeFalse();

    $this->get('/parts/compare?keyword='.urlencode('バイク テスト'))->assertOk();

    // 429 で pause() が呼ばれ、YahooRateGate のブレーカーが作動していること
    // （ProductSearchService と休止状態を共有する）。
    expect(app(YahooRateGate::class)->isPaused())->toBeTrue();
});
