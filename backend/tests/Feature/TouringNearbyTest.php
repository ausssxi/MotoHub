<?php

declare(strict_types=1);

use App\Models\Poi;
use App\Models\RoadsideStation;
use App\Models\TouringGuide;
use App\Models\TouringSpot;
use App\Models\User;
use App\Support\TouringNearby;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(fn () => Cache::flush());

// 中心 (35.00, 139.00)。緯度0.01≒1.1km。
const CX = 35.00;
const CY = 139.00;

function touringAuthorId(): int
{
    $u = User::query()->first();
    if (! $u) {
        $u = new User;
        $u->forceFill(['name' => 'Author', 'email' => 'author@example.com', 'password' => bcrypt('secret')]);
        $u->save();
    }

    return (int) $u->id;
}

function guideAt(array $attrs = []): TouringGuide
{
    $g = (new TouringGuide)->forceFill(array_merge([
        'author_id' => touringAuthorId(),
        'title' => 'テストルート',
        'body' => '本文です。',
        'difficulty' => '初級',
        'status' => 'published',
        'latitude' => CX,
        'longitude' => CY,
        'distance_km' => 80, // radius 16km
        'prefecture' => '東京都',
        'published_at' => now(),
    ], $attrs));
    $g->save();

    return $g;
}

function spotAt(float $dLatKm, float $dLngKm, array $attrs = []): TouringSpot
{
    $s = (new TouringSpot)->forceFill(array_merge([
        'name' => 'スポット',
        'prefecture' => '東京都',
        'slug' => 'spot-'.uniqid(),
        'lat' => CX + $dLatKm / 111.0,
        'lng' => CY + $dLngKm / 111.0,
    ], $attrs));
    $s->save();

    return $s;
}

function stationAt(float $dLatKm, array $attrs = []): RoadsideStation
{
    static $code = 1;
    $s = (new RoadsideStation)->forceFill(array_merge([
        'station_code' => 'S'.($code++),
        'name' => '道の駅テスト',
        'prefecture' => '東京都',
        'city' => '八王子市',
        'latitude' => CX + $dLatKm / 111.0,
        'longitude' => CY,
    ], $attrs));
    $s->save();

    return $s;
}

function gsAt(float $dLatKm, array $attrs = []): Poi
{
    static $osm = 5000;
    $p = (new Poi)->forceFill(array_merge([
        'osm_id' => $osm++,
        'type' => 'gas_station',
        'brand' => 'エネオス',
        'prefecture' => '東京都',
        'city' => '八王子市',
        'address' => '東京都八王子市東町',
        'latitude' => CX + $dLatKm / 111.0,
        'longitude' => CY,
    ], $attrs));
    $p->save();

    return $p;
}

it('collects nearby spots/stations/gas/carwash within the radius', function () {
    $guide = guideAt();
    spotAt(5, 0);                 // ~5km 圏内
    spotAt(50, 0);                // ~50km 圏外（radius16）→ 除外
    stationAt(3);
    gsAt(2);
    gsAt(4);
    gsAt(1, ['type' => 'car_wash', 'brand' => null, 'name' => null, 'self_service' => 'yes', 'automated' => 'yes']);

    $n = (new TouringNearby)->forGuide($guide);

    expect($n['spots'])->toHaveCount(1);
    expect($n['stations'])->toHaveCount(1);
    expect($n['gas']['count'])->toBe(2);
    expect($n['car_wash'])->toHaveCount(1);
    // 洗車場の表示名は PoiDisplayResolver 経由（コイン洗車場（町名））。
    expect($n['car_wash'][0]['display'])->toContain('洗車場');
});

it('returns empty arrays and does not throw when nothing is nearby', function () {
    $guide = guideAt();

    $n = (new TouringNearby)->forGuide($guide);

    expect($n['spots'])->toBe([]);
    expect($n['stations'])->toBe([]);
    expect($n['gas']['count'])->toBe(0);
    expect($n['gas']['items'])->toBe([]);
    expect($n['car_wash'])->toBe([]);
});

it('orders same-prefecture facilities first', function () {
    $guide = guideAt(['prefecture' => '東京都']);
    spotAt(3, 0, ['prefecture' => '神奈川県', 'name' => '県外スポット']); // 近い(3km)が県外
    spotAt(10, 0, ['prefecture' => '東京都', 'name' => '都内スポット']);  // 遠い(10km)が同県

    $n = (new TouringNearby)->forGuide($guide);

    expect($n['spots'][0]['name'])->toBe('都内スポット'); // 同県が先
});

it('flags GS as sparse below the config threshold and lists them', function () {
    config()->set('touring.gas_sparse_threshold', 40);
    $guide = guideAt();
    foreach (range(1, 5) as $i) {
        gsAt($i * 0.5);
    }

    $n = (new TouringNearby)->forGuide($guide);

    expect($n['gas']['count'])->toBe(5);
    expect($n['gas']['sparse'])->toBeTrue();
    expect(count($n['gas']['items']))->toBeGreaterThan(0);
    // 表示名は resolver 経由（ENEOS（東町））。
    expect($n['gas']['items'][0]['display'])->toContain('ENEOS');
});

it('marks GS as dense at/above the threshold and omits the list', function () {
    config()->set('touring.gas_sparse_threshold', 40);
    $guide = guideAt();
    foreach (range(1, 45) as $i) {
        gsAt(0.05 * $i); // すべて radius16 圏内
    }

    $n = (new TouringNearby)->forGuide($guide);

    expect($n['gas']['count'])->toBe(45);
    expect($n['gas']['sparse'])->toBeFalse();
    expect($n['gas']['items'])->toBe([]); // 多いのでリストは出さない
});

it('runs at most 4 queries and hits cache on the second call', function () {
    $guide = guideAt();
    spotAt(3, 0);
    gsAt(2);

    DB::flushQueryLog();
    DB::enableQueryLog();
    (new TouringNearby)->forGuide($guide);
    $first = count(DB::getQueryLog());

    DB::flushQueryLog();
    (new TouringNearby)->forGuide($guide); // 2回目はキャッシュ
    $second = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($first)->toBeLessThanOrEqual(4);
    expect($second)->toBe(0);
});

// ─────────── ガイド詳細ページの表示（ブロックの出し分け） ───────────

it('shows spots + sparse GS count + carwash, and hides the 道の駅 block when none', function () {
    $guide = guideAt(['slug' => 'sparse-route']);
    spotAt(3, 0, ['name' => '絶景スポット']);
    gsAt(1);
    gsAt(2);
    gsAt(3); // 3軒（sparse）
    gsAt(1, ['type' => 'car_wash', 'brand' => null, 'name' => null, 'self_service' => 'yes', 'automated' => 'yes']);

    $res = $this->get('/touring/sparse-route')->assertOk();

    $res->assertSee('このルート周辺のツーリングスポット');
    $res->assertDontSee('ルート沿いの道の駅');            // 道の駅0件 → 見出しごと非表示
    $res->assertSee('このルート周辺のガソリンスタンド（3軒）'); // 件数表記
    $res->assertSee('出発前の給油をおすすめします');
    $res->assertSee('帰りに寄れる洗車場');
});

it('shows GS count only (no list) when dense, and hides the 洗車場 block when none', function () {
    $guide = guideAt(['slug' => 'dense-route']);
    foreach (range(1, 45) as $i) {
        gsAt(0.05 * $i);
    }

    $res = $this->get('/touring/dense-route')->assertOk();

    $res->assertSee('45軒のガソリンスタンドがあります'); // 件数のみの一文
    $res->assertSee('地図でガソリンスタンドを探す');       // リストの代わりに地図リンク
    $res->assertDontSee('出発前の給油をおすすめします');   // sparse用の文言は出ない
    $res->assertDontSee('帰りに寄れる洗車場');            // 洗車場0件 → 非表示
});

it('renders the touring show page (200) even with nothing nearby', function () {
    $guide = guideAt(['slug' => 'empty-route', 'latitude' => 43.5, 'longitude' => 143.0]);

    $this->get('/touring/empty-route')->assertOk()->assertDontSee('このルートの周辺情報');
});

// ─────────── フッター ───────────

it('adds ツーリングガイド to the footer without dropping existing links', function () {
    $res = $this->get('/')->assertOk();

    $res->assertSee(route('touring.index'), false);
    $res->assertSee('ツーリングガイド');
    // 既存リンクが減っていないこと（同じ列の代表的リンク）。
    $res->assertSee('ライダーズマップ');
    $res->assertSee('道の駅を探す');
    $res->assertSee('洗車場を探す');
});
