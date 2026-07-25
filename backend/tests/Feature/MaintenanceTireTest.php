<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Support\TireMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function tireModel(?string $front, ?string $rear = null, string $name = 'テスト車'): BikeModel
{
    $m = Manufacturer::where('slug', 'honda-tire')->first();
    if (! $m) {
        $m = new Manufacturer;
        $m->forceFill(['name' => 'ホンダ', 'slug' => 'honda-tire'])->save();
    }
    $uniq = uniqid();
    $bm = new BikeModel;
    $bm->forceFill([
        // name も slug と同様にユニーク化（bike_models.name の UNIQUE 制約対策。1テスト内で複数台作るため）。
        'manufacturer_id' => $m->id, 'name' => $name.'-'.$uniq, 'slug' => 'tire-'.$uniq,
        'tire_size_front' => $front, 'tire_size_rear' => $rear,
    ])->save();

    return $bm;
}

// ─────────── extractSize（実データの全表記パターン） ───────────

it('extracts and normalizes every real tire-size format', function (string $raw, ?string $expected) {
    expect(TireMaintenance::extractSize($raw))->toBe($expected);
})->with([
    ['120/80-12 55J', '120/80-12'],           // バイアス＋LI
    ['110/80-14M/C 53P', '110/80-14'],         // M/C密着
    ['120/70ZR17', '120/70 ZR17'],             // ラジアル密着→スペース挿入
    ['120/70ZR17M/C?58W?', '120/70 ZR17'],     // 文字化け"?"→混入しない
    ['120/70ZR17M/C (58W)', '120/70 ZR17'],    // 半角カッコ
    ['110/70R17', '110/70 R17'],               // R密着→スペース
    ['130/80 B17 M/C 65H', '130/80 B17'],      // B構造＋空白区切り
    ['100/90 19', '100/90-19'],                // 空白区切り→ハイフン
    ['3.00-18', '3.00-18'],                    // 旧インチ
    ['2.75-21 45P', '2.75-21'],                // 旧インチ＋LI
    ['100/90-10 56J TL', '100/90-10'],         // チューブレス表記
    ['?', null],                               // 壊れデータ→非表示
    ['', null],
    ['不明', null],                             // 抽出不能→非表示
]);

it('never leaks a garbled ? into the extracted size', function () {
    expect(TireMaintenance::extractSize('120/70ZR17M/C?58W?'))->not->toContain('?');
});

// ─────────── forModel ───────────

it('marks front==rear as same and builds one keyword', function () {
    $data = TireMaintenance::forModel(tireModel('90/90-10 50J', '90/90-10 50J'));

    expect($data['mode'])->toBe('rich')
        ->and($data['same'])->toBeTrue()
        ->and($data['front'])->toBe('90/90-10')
        ->and($data['front_keyword'])->toBe('バイク タイヤ 90/90-10');
});

it('keeps front/rear separate when sizes differ', function () {
    $data = TireMaintenance::forModel(tireModel('120/70ZR17', '180/55ZR17'));

    expect($data['same'])->toBeFalse()
        ->and($data['front'])->toBe('120/70 ZR17')
        ->and($data['rear'])->toBe('180/55 ZR17')
        ->and($data['front_keyword'])->toBe('バイク タイヤ 120/70 ZR17')
        ->and($data['rear_keyword'])->toBe('バイク タイヤ 180/55 ZR17');
});

it('returns mode=none when both sizes are missing or broken (no display, no fake)', function () {
    expect(TireMaintenance::forModel(tireModel(null, null))['mode'])->toBe('none')
        ->and(TireMaintenance::forModel(tireModel('?', ''))['mode'])->toBe('none')
        ->and(TireMaintenance::forModel(tireModel('不明', '-'))['mode'])->toBe('none');
});

// ─────────── partial 描画 ───────────

it('renders normalized sizes and the 交換目安, never the garbled raw value', function () {
    cache()->flush();
    Http::fake(['*' => Http::response([], 200)]); // 商品APIは空＝chrome のみ検証

    $html = view('bikes.partials.maintenance-tire', ['model' => tireModel('120/70ZR17M/C?58W?', '120/70ZR17M/C?58W?')])->render();

    expect($html)->toContain('タイヤサイズ')
        ->toContain('120/70 ZR17')        // 正規化コア
        ->toContain('前後共通')
        ->toContain('残溝1.6mm')           // 交換目安
        ->toContain('現車のタイヤ側面')     // 注記
        // ★文字化け"?58W?"や生の M/C・LI/速度記号を表示に漏らさない（URLの?クエリと区別するため生断片で判定）。
        ->not->toContain('58W')
        ->not->toContain('M/C')
        ->not->toContain('ZR17M');
});

it('renders nothing when there is no usable tire data', function () {
    $html = view('bikes.partials.maintenance-tire', ['model' => tireModel(null, null)])->render();

    expect(trim($html))->toBe('');
});

it('shows front/rear labeled groups when sizes differ', function () {
    config(['services.rakuten.app_id' => 'x', 'services.rakuten.access_key' => 'y']);
    cache()->flush();
    Http::fake([
        'openapi.rakuten.co.jp/*' => Http::response(['Items' => [['Item' => [
            'itemCode' => 'shop:1', 'itemName' => 'テスト用タイヤ',
            'mediumImageUrls' => [['imageUrl' => 'https://img.example/t.jpg']],
            'itemPrice' => 9800, 'itemUrl' => 'https://item.rakuten.co.jp/shop/tire/',
        ]]]], 200),
        'shopping.yahooapis.jp/*' => Http::response(['hits' => []], 200),
    ]);

    $html = view('bikes.partials.maintenance-tire', ['model' => tireModel('120/70ZR17', '180/55ZR17')])->render();

    expect($html)->toContain('フロントタイヤ')->toContain('120/70 ZR17')
        ->toContain('リアタイヤ')->toContain('180/55 ZR17')
        ->toContain('rel="nofollow sponsored noopener"')
        ->toContain('PR・広告');
});

it('falls back to a /parts/compare search link (HTML, not JSON API) when no products', function () {
    cache()->flush();
    Http::fake(['*' => Http::response([], 200)]);

    $html = view('bikes.partials.maintenance-tire', ['model' => tireModel('120/70ZR17', '180/55ZR17')])->render();

    expect($html)->toContain('タイヤを探す')
        ->toContain('/parts/compare')
        ->not->toContain('/parts/search');
});
