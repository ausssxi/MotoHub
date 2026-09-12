<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Support\TireSize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush(); // indexData/pageableIndex の array キャッシュがテスト間で残らないように
});

function tireIndexMfr(): Manufacturer
{
    $mfr = Manufacturer::where('slug', 'honda')->first();
    if (! $mfr) {
        $mfr = new Manufacturer(['slug' => 'honda']);
        $mfr->name = 'ホンダ';
        $mfr->save();
    }

    return $mfr;
}

/**
 * 前輪サイズ front を共有する車種を count 台。display_name を与える（索引の代表名に使われる）。
 */
function seedIndexModels(string $front, int $count = 5, string $displayPrefix = 'モデル'): void
{
    $mfr = tireIndexMfr();
    for ($i = 0; $i < $count; $i++) {
        $m = new BikeModel([
            'manufacturer_id' => $mfr->id,
            'name' => 'raw-'.md5($front.$i),
            'display_name' => $displayPrefix.$i,
            'slug' => 'idx-'.md5($front.'-'.$i),
        ]);
        $m->tire_size_front = $front;
        $m->tire_size_rear = '180/55ZR17';
        $m->save();
    }
}

it('groups sizes by rim size into the fixed buckets', function () {
    seedIndexModels('120/70ZR17', 6, 'Z900RS'); // リム17 → 17インチ
    seedIndexModels('130/70-13', 5, 'PCX');      // リム13 → 12〜14インチ
    seedIndexModels('MT90B16', 5, 'ハーレー');    // 判定不能 → その他

    $groups = TireSize::indexData();
    $labels = array_column($groups, 'label');

    expect($labels)->toContain('17インチ');
    expect($labels)->toContain('12〜14インチ');
    expect($labels)->toContain('その他');

    // 17インチ グループに 120/70ZR17 が入り、SVG図がある。
    $g17 = collect($groups)->firstWhere('label', '17インチ');
    $sizes17 = array_column($g17['sizes'], 'size');
    expect($sizes17)->toContain('120/70ZR17');
    expect($g17['sizes'][0]['svg'])->toContain('<svg');

    // その他は図が描けない（svg=null）。
    $other = collect($groups)->firstWhere('label', 'その他');
    expect($other['sizes'][0]['svg'])->toBeNull();
    expect(array_column($other['sizes'], 'size'))->toContain('MT90B16');
});

it('puts 15-inch sizes into the new 15インチ group', function () {
    seedIndexModels('120/70R15', 5, 'TMAX');

    $groups = TireSize::indexData();
    $g15 = collect($groups)->firstWhere('label', '15インチ');

    expect($g15)->not->toBeNull();
    expect($g15['desc'])->toBe('ビッグスクーターの前輪');
    expect(array_column($g15['sizes'], 'size'))->toContain('120/70R15');
    expect($g15['sizes'][0]['svg'])->toContain('<svg'); // 図も出る
});

it('renders the index page with server-side svg and model-name text', function () {
    seedIndexModels('120/70ZR17', 6, 'Z900RS');

    $res = $this->get('/bikes/tire-size')->assertOk();

    // サーバー側で生成した SVG がHTMLに含まれる（JS実行前に見える）。
    $res->assertSee('<svg', false);
    // リムグループの見出し・説明。
    $res->assertSee('17インチ');
    $res->assertSee('スポーツ・ネイキッドの前輪（最も多い）');
    // 代表車種名（display_name）がテキストで出ている。
    $res->assertSee('Z900RS0');
});

it('has no N+1: indexData runs a bounded number of queries regardless of card count', function () {
    // 複数サイズ・多数車種をシードしても、クエリは「全車種1回＋在庫1回」に収まること。
    seedIndexModels('120/70ZR17', 6, 'Z900RS');
    seedIndexModels('110/70R17', 6, 'CBR');
    seedIndexModels('100/90-19', 6, 'W800');
    seedIndexModels('2.75-21', 6, 'CRF');
    seedIndexModels('MT90B16', 6, 'FLH');

    DB::flushQueryLog();
    DB::enableQueryLog();
    $groups = TireSize::indexData();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($groups)->not->toBeEmpty();
    // 車種取得(1) + 在庫集計(1)。カード数に比例して増えない。
    expect($queryCount)->toBeLessThanOrEqual(3);
});

it('keeps the detail page returning 200 (unchanged)', function () {
    seedIndexModels('120/70ZR17', 6, 'Z900RS');

    $this->get('/bikes/tire-size/120-70zr17')->assertOk();
});
