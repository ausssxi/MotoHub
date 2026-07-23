<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\ModelFitment;
use App\Support\BatteryMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function batteryModel(string $name = 'テスト車'): BikeModel
{
    $m = Manufacturer::where('slug', 'honda-battery')->first();
    if (! $m) {
        $m = new Manufacturer;
        $m->forceFill(['name' => 'ホンダ', 'slug' => 'honda-battery'])->save();
    }
    $bm = new BikeModel;
    $bm->forceFill(['manufacturer_id' => $m->id, 'name' => $name, 'slug' => 'battery-'.uniqid(), 'displacement' => 125])->save();

    return $bm;
}

function batteryFitment(BikeModel $m, array $spec = [], string $partNo = 'YTX4L-BS', string $frame = 'AA01'): ModelFitment
{
    return ModelFitment::forceCreate([
        'bike_model_id' => $m->id, 'task' => 'battery', 'frame_code' => $frame, 'year_range' => '2020〜',
        'recommended_part_no' => $partNo,
        'compatible_part_nos' => [['brand' => '台湾ユアサ', 'part_no' => $partNo]],
        'spec' => array_merge(['voltage' => '12V', 'capacity' => '3Ah', 'type' => 'VRLA'], $spec),
        'source_1_name' => 'GSユアサ適合検索', 'source_1_url' => 'https://gyb.gs-yuasa.com/',
        'verified_at' => now(),
    ]);
}

// ─────────── BatteryMaintenance ヘルパー ───────────

it('resolves rich battery data from a verified battery row', function () {
    $m = batteryModel();
    batteryFitment($m);

    $data = BatteryMaintenance::forModel($m);

    expect($data['mode'])->toBe('rich')
        ->and($data['voltage'])->toBe('12V')
        ->and($data['capacity'])->toBe('3Ah')
        ->and($data['type'])->toBe('VRLA')
        ->and($data['frame_count'])->toBe(1)
        ->and($data['product_keyword'])->toBe('バイク バッテリー YTX4L-BS');
});

it('returns mode=none when no verified battery row exists (no general fallback)', function () {
    expect(BatteryMaintenance::forModel(batteryModel())['mode'])->toBe('none');
});

it('ignores non-verified battery rows (no fake data)', function () {
    $m = batteryModel();
    ModelFitment::forceCreate([
        'bike_model_id' => $m->id, 'task' => 'battery', 'frame_code' => '', 'year_range' => '',
        'recommended_part_no' => 'YTX5L-BS', 'spec' => ['voltage' => '12V'],
        'verified_at' => null,
    ]);

    expect(BatteryMaintenance::forModel($m)['mode'])->toBe('none');
});

it('collapses differing spec values across frame codes to 型式による and drops the product keyword', function () {
    $m = batteryModel();
    batteryFitment($m, ['capacity' => '3Ah'], 'YTX4L-BS', 'AA01');
    batteryFitment($m, ['capacity' => '5Ah'], 'YTX5L-BS', 'AA02'); // 容量も型番も相違

    $data = BatteryMaintenance::forModel($m);

    expect($data['mode'])->toBe('rich')
        ->and($data['voltage'])->toBe('12V')       // 全行同一 → 値
        ->and($data['capacity'])->toBe('型式による') // 相違 → 畳む
        ->and($data['frame_count'])->toBe(2)
        ->and($data['product_keyword'])->toBeNull(); // 型番相違 → keyword なし
});

// ─────────── partial 描画（model_detail はSQLite描画不可のため partial 単体で検証） ───────────

it('renders the rich block with 区分 and 適合表 link but NOT the part number (カニバリ回避)', function () {
    $m = batteryModel();
    batteryFitment($m);

    $html = view('bikes.partials.maintenance-battery', ['model' => $m])->render();

    expect($html)->toContain('この車種の規格')
        ->toContain('12V')->toContain('3Ah')->toContain('VRLA')
        ->toContain('2〜3年')                                  // 交換時期の一般目安
        ->toContain('GSユアサ適合検索')                        // 出典
        ->toContain('型番・互換品番・価格比較を見る')          // 適合表への導線
        ->not->toContain('YTX4L-BS');                          // ★型番は表示に出さない
});

it('renders nothing when no verified battery row exists', function () {
    $html = view('bikes.partials.maintenance-battery', ['model' => batteryModel()])->render();

    expect(trim($html))->toBe('');
});

it('renders affiliate battery product cards (rel=nofollow sponsored + PR) when part number is single', function () {
    config(['services.rakuten.app_id' => 'x', 'services.rakuten.access_key' => 'y', 'services.rakuten.affiliate_id' => 'aff1']);
    cache()->flush();
    Http::fake([
        'openapi.rakuten.co.jp/*' => Http::response(['Items' => [['Item' => [
            'itemCode' => 'shop:123', 'itemName' => 'テスト用バッテリー YTX4L-BS',
            'mediumImageUrls' => [['imageUrl' => 'https://img.example/b.jpg']],
            'itemPrice' => 3980, 'itemUrl' => 'https://item.rakuten.co.jp/shop/battery/?scid=aff1',
        ]]]], 200),
        'shopping.yahooapis.jp/*' => Http::response(['hits' => []], 200),
    ]);

    $m = batteryModel();
    batteryFitment($m);

    $html = view('bikes.partials.maintenance-battery', ['model' => $m])->render();

    expect($html)->toContain('rel="nofollow sponsored noopener"')
        ->toContain('PR・広告')
        ->toContain('item.rakuten.co.jp/shop/battery')
        ->toContain('おすすめバッテリー');
});

it('falls back to a search link (no product cards) when part numbers vary across frame codes', function () {
    config(['services.rakuten.app_id' => 'x', 'services.rakuten.access_key' => 'y']);
    cache()->flush();
    Http::fake(['*' => Http::response(['Items' => []], 200)]);

    $m = batteryModel();
    batteryFitment($m, [], 'YTX4L-BS', 'AA01');
    batteryFitment($m, [], 'YTX5L-BS', 'AA02');

    $html = view('bikes.partials.maintenance-battery', ['model' => $m])->render();

    expect($html)->toContain('対応バッテリーを探す')
        ->not->toContain('おすすめバッテリー')
        ->not->toContain('PR・広告');
});
