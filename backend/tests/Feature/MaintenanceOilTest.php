<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\ModelFitment;
use App\Support\OilMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function oilModel(int $displacement = 250, string $name = 'テスト車'): BikeModel
{
    $m = Manufacturer::where('slug', 'honda-oil')->first();
    if (! $m) {
        $m = new Manufacturer;
        $m->forceFill(['name' => 'ホンダ', 'slug' => 'honda-oil'])->save();
    }
    $bm = new BikeModel;
    $bm->forceFill(['manufacturer_id' => $m->id, 'name' => $name, 'slug' => 'oil-'.uniqid(), 'displacement' => $displacement])->save();

    return $bm;
}

function oilFitment(BikeModel $m, array $spec = [], string $viscosity = '10W-30'): ModelFitment
{
    return ModelFitment::forceCreate([
        'bike_model_id' => $m->id, 'task' => 'oil', 'frame_code' => '', 'year_range' => '',
        'recommended_part_no' => $viscosity,
        'spec' => array_merge(['capacity_change' => '1.5L', 'capacity_filter' => '1.6L', 'jaso' => 'MA2', 'interval' => '5000km'], $spec),
        'source_1_name' => 'メーカー公式', 'source_1_url' => 'https://example.com/oil',
        'verified_at' => now(),
    ]);
}

// ─────────── OilMaintenance ヘルパー ───────────

it('resolves rich oil data from a verified oil fitment row', function () {
    $m = oilModel(400);
    oilFitment($m, ['capacity_change' => '2.7L', 'interval' => '6000km'], '10W-40');

    $data = OilMaintenance::forModel($m);

    expect($data['mode'])->toBe('rich')
        ->and($data['rich']['viscosity'])->toBe('10W-40')
        ->and($data['rich']['capacity_change'])->toBe('2.7L')
        ->and($data['oil_keyword'])->toBe('バイク エンジンオイル 10W-40');
});

it('falls back to the displacement-band general guide when no verified oil row exists', function () {
    $data = OilMaintenance::forModel(oilModel(250));

    expect($data['mode'])->toBe('general')
        ->and($data['general']['label'])->toBe('126〜250cc')
        ->and($data['general']['capacity'])->toBe('約1.0〜2.0L')
        // 一般モードの用品keywordは排気量サフィックスを付けず全帯共通（Yahoo誤マッチ回避・キャッシュ収束）。
        ->and($data['oil_keyword'])->toBe('バイク エンジンオイル');
});

it('ignores non-verified oil rows (no fake data)', function () {
    $m = oilModel(400);
    ModelFitment::forceCreate([
        'bike_model_id' => $m->id, 'task' => 'oil', 'frame_code' => '', 'year_range' => '',
        'recommended_part_no' => '5W-40', 'spec' => ['capacity_change' => '3L'],
        'verified_at' => null, // 未検証 → 公開しない
    ]);

    expect(OilMaintenance::forModel($m)['mode'])->toBe('general'); // rich にならない
});

// ─────────── partial 描画（model_detail はSQLite描画不可のため partial 単体で検証） ───────────

it('renders the rich block with DB values, sources and 「この車種の推奨」', function () {
    $m = oilModel(400);
    oilFitment($m, ['jaso' => 'MA2', 'interval' => '6000km'], '10W-40');

    $html = view('bikes.partials.maintenance-oil', ['model' => $m])->render();

    expect($html)->toContain('この車種の推奨')
        ->toContain('10W-40')->toContain('1.6L')->toContain('MA2')->toContain('6000km')
        ->toContain('メーカー公式')                         // 出典
        ->toContain('オイルフィルターを探す')              // フィルタ検索リンク
        ->toContain('/parts/compare')                       // HTML価格比較ページへ（JSON API /parts/search ではない）
        ->not->toContain('/parts/search');
});

it('renders the general fallback block with band values, disclaimer and source', function () {
    $html = view('bikes.partials.maintenance-oil', ['model' => oilModel(250)])->render();

    expect($html)->toContain('※一般的な目安')
        ->toContain('126〜250cc')->toContain('約1.0〜2.0L')
        ->toContain('取扱説明書や整備解説でご確認ください') // 注記
        ->toContain('オイルフィルターを探す');
});

it('renders affiliate oil product cards (rel=nofollow sponsored + PR) when products exist', function () {
    config(['services.rakuten.app_id' => 'x', 'services.rakuten.access_key' => 'y', 'services.rakuten.affiliate_id' => 'aff1']);
    cache()->flush();
    Http::fake([
        'openapi.rakuten.co.jp/*' => Http::response(['Items' => [['Item' => [
            'itemCode' => 'shop:123', 'itemName' => 'テスト用エンジンオイル 10W-40',
            'mediumImageUrls' => [['imageUrl' => 'https://img.example/o.jpg']],
            'itemPrice' => 2980, 'itemUrl' => 'https://item.rakuten.co.jp/shop/oil/?scid=aff1',
        ]]]], 200),
        'shopping.yahooapis.jp/*' => Http::response(['hits' => []], 200),
    ]);

    $m = oilModel(400);
    oilFitment($m);

    $html = view('bikes.partials.maintenance-oil', ['model' => $m])->render();

    expect($html)->toContain('rel="nofollow sponsored noopener"')
        ->toContain('PR・広告')
        ->toContain('item.rakuten.co.jp/shop/oil')
        ->toContain('テスト用エンジンオイル');
});
