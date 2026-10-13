<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Services\Parking\ParkingService;

/**
 * 入庫可能バイク判定サンプルの表示名が正式表記になること。
 * DBの name は MySQL の照合順序が大小文字を無視するため pcx / ninja400 / z900rs 等
 * 小文字で入っていることがある。画面には正式表記（PCX / Ninja 400 / Z900RS / Rebel 250）を出す。
 */
it('uses official display labels for bike compatibility samples even when db names are lowercase', function () {
    $mfr = Manufacturer::query()->forceCreate(['name' => 'テスト', 'slug' => 'test-mfr']);

    // わざと小文字・別表記で登録（本番DBに存在し得る状態）。
    $rows = [
        ['name' => 'pcx', 'displacement' => 125],
        ['name' => 'スーパーカブ110', 'displacement' => 110],
        ['name' => 'レブル250', 'displacement' => 250],
        ['name' => 'ninja400', 'displacement' => 400],
        ['name' => 'cb400sf', 'displacement' => 400],
        ['name' => 'z900rs', 'displacement' => 948],
        ['name' => 'ハヤブサ', 'displacement' => 1340],
    ];
    foreach ($rows as $i => $r) {
        BikeModel::query()->forceCreate([
            'manufacturer_id' => $mfr->id,
            'name' => $r['name'],
            'displacement' => $r['displacement'],
            'slug' => 'm'.$i,
        ]);
    }

    $service = app(ParkingService::class);
    $method = new ReflectionMethod($service, 'getBikeCompatibility');
    $method->setAccessible(true);

    /** @var array<int, array{name:string}> $samples */
    $samples = $method->invoke($service, []);
    $names = array_column($samples, 'name');

    expect($names)
        ->toContain('PCX')
        ->toContain('Ninja 400')
        ->toContain('Z900RS')
        ->toContain('Rebel 250')
        ->toContain('CB400SF')
        ->toContain('スーパーカブ110') // そのまま
        ->toContain('ハヤブサ')          // そのまま
        ->not->toContain('pcx')
        ->not->toContain('ninja400')
        ->not->toContain('z900rs')
        ->not->toContain('レブル250');
});
