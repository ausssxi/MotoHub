<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\ModelFitment;
use App\Support\PlugMaintenance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function plugModel(string $name = 'テスト車'): BikeModel
{
    $m = Manufacturer::where('slug', 'honda-plug')->first();
    if (! $m) {
        $m = new Manufacturer;
        $m->forceFill(['name' => 'ホンダ', 'slug' => 'honda-plug'])->save();
    }
    $uniq = uniqid();
    $bm = new BikeModel;
    $bm->forceFill(['manufacturer_id' => $m->id, 'name' => $name.'-'.$uniq, 'slug' => 'plug-'.$uniq, 'displacement' => 250])->save();

    return $bm;
}

function plugFitment(BikeModel $m, array $spec = [], string $partNo = 'CPR6EA-9S', string $frame = 'AA09'): ModelFitment
{
    return ModelFitment::forceCreate([
        'bike_model_id' => $m->id, 'task' => 'plug', 'frame_code' => $frame, 'year_range' => '2017〜',
        'recommended_part_no' => $partNo,
        'spec' => array_merge(['heat' => '6', 'plugs' => '1'], $spec),
        'source_1_name' => 'NGK 適合表', 'source_1_url' => 'https://www.ngk-sparkplugs.jp/',
        'verified_at' => now(),
    ]);
}

// ─────────── PlugMaintenance ヘルパー ───────────

it('resolves rich plug data from a verified plug row', function () {
    $m = plugModel();
    plugFitment($m);

    $data = PlugMaintenance::forModel($m);

    expect($data['mode'])->toBe('rich')
        ->and($data['heat'])->toBe('6')
        ->and($data['plugs'])->toBe('1')
        ->and($data['frame_count'])->toBe(1)
        ->and($data['product_keyword'])->toBe('バイク スパークプラグ CPR6EA-9S');
});

it('returns mode=none when no verified plug row exists (no general fallback)', function () {
    expect(PlugMaintenance::forModel(plugModel())['mode'])->toBe('none');
});

it('ignores non-verified plug rows (no fake data)', function () {
    $m = plugModel();
    ModelFitment::forceCreate([
        'bike_model_id' => $m->id, 'task' => 'plug', 'frame_code' => '', 'year_range' => '',
        'recommended_part_no' => 'CR7HSA', 'spec' => ['heat' => '7'],
        'verified_at' => null,
    ]);

    expect(PlugMaintenance::forModel($m)['mode'])->toBe('none');
});

it('collapses differing spec values across frame codes to 型式による and drops the product keyword', function () {
    $m = plugModel();
    plugFitment($m, ['heat' => '6'], 'CPR6EA-9S', 'AA09');
    plugFitment($m, ['heat' => '7'], 'CPR7EA-9S', 'AA04'); // 熱価も型番も相違

    $data = PlugMaintenance::forModel($m);

    expect($data['mode'])->toBe('rich')
        ->and($data['plugs'])->toBe('1')            // 全行同一 → 値
        ->and($data['heat'])->toBe('型式による')     // 相違 → 畳む
        ->and($data['frame_count'])->toBe(2)
        ->and($data['product_keyword'])->toBeNull(); // 型番相違 → keyword なし
});

// ─────────── partial 描画 ───────────

it('renders the rich block with 区分 and 適合表 link but NOT the part number (カニバリ回避)', function () {
    cache()->flush();
    Http::fake(['*' => Http::response([], 200)]); // 商品APIは空＝chrome のみ検証

    $m = plugModel();
    plugFitment($m);

    $html = view('bikes.partials.maintenance-plug', ['model' => $m])->render();

    expect($html)->toContain('この車種の規格')
        ->toContain('熱価')->toContain('6番')        // 熱価「◯番」
        ->toContain('必要本数')->toContain('1本')     // 本数「◯本」
        ->toContain('3,000〜5,000km')                 // 交換時期の目安
        ->toContain('NGK 適合表')                     // 出典
        ->toContain('型番・互換品番・価格比較を見る')  // 適合表への導線
        ->not->toContain('CPR6EA-9S');                // ★型番は表示に出さない
});

it('renders nothing when no verified plug row exists', function () {
    $html = view('bikes.partials.maintenance-plug', ['model' => plugModel()])->render();

    expect(trim($html))->toBe('');
});

it('renders affiliate plug product cards (rel=nofollow sponsored + PR) when part number is single', function () {
    config(['services.rakuten.app_id' => 'x', 'services.rakuten.access_key' => 'y', 'services.rakuten.affiliate_id' => 'aff1']);
    cache()->flush();
    Http::fake([
        'openapi.rakuten.co.jp/*' => Http::response(['Items' => [['Item' => [
            'itemCode' => 'shop:123', 'itemName' => 'テスト用スパークプラグ',
            'mediumImageUrls' => [['imageUrl' => 'https://img.example/p.jpg']],
            'itemPrice' => 680, 'itemUrl' => 'https://item.rakuten.co.jp/shop/plug/',
        ]]]], 200),
        'shopping.yahooapis.jp/*' => Http::response(['hits' => []], 200),
    ]);

    $m = plugModel();
    plugFitment($m);

    $html = view('bikes.partials.maintenance-plug', ['model' => $m])->render();

    expect($html)->toContain('rel="nofollow sponsored noopener"')
        ->toContain('PR・広告')
        ->toContain('item.rakuten.co.jp/shop/plug')
        ->toContain('おすすめプラグ');
});

it('falls back to a /parts/compare search link (HTML, not JSON API) when part numbers vary', function () {
    cache()->flush();
    Http::fake(['*' => Http::response(['Items' => []], 200)]);

    $m = plugModel();
    plugFitment($m, [], 'CPR6EA-9S', 'AA09');
    plugFitment($m, [], 'CPR7EA-9S', 'AA04');

    $html = view('bikes.partials.maintenance-plug', ['model' => $m])->render();

    expect($html)->toContain('対応プラグを探す')
        ->toContain('/parts/compare')
        ->not->toContain('/parts/search')
        ->not->toContain('おすすめプラグ')
        ->not->toContain('PR・広告');
});
