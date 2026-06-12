<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\SeoCompare;
use App\Services\Bike\SeoCompareService;

function makeModel(string $name, int $displacement): BikeModel
{
    // Manufacturer::$fillable は ['slug'] のみなので create() では name が落ちる → forceCreate で必須カラムを明示
    $mfr = Manufacturer::forceCreate([
        'name' => "MFR {$name}",
        'slug' => 'mfr-' . md5($name),
    ]);

    return BikeModel::create([
        'manufacturer_id' => $mfr->id,
        'name' => $name,
        'displacement' => $displacement,
    ]);
}

it('renders the compare hub grouped by cc-band and category', function () {
    $a = makeModel('レブル250', 249);
    $b = makeModel('GB350', 348);

    SeoCompare::create([
        'slug' => 'rebel-250-vs-gb350',
        'model1_id' => $a->id,
        'model2_id' => $b->id,
        'is_active' => true,
        'sort_order' => 1,
    ]);

    $response = $this->get('/bikes/compare');

    $response->assertStatus(200);
    $response->assertSee('車種比較一覧');
    $response->assertSee('レブル250 vs GB350');
    $response->assertSee(route('bikes.model_compare', 'rebel-250-vs-gb350'), false);
});

it('excludes inactive pairs from the hub groups', function () {
    $a = makeModel('CB400SF', 399);
    $b = makeModel('XJR400', 399);

    SeoCompare::create([
        'slug' => 'cb400sf-vs-xjr400',
        'model1_id' => $a->id,
        'model2_id' => $b->id,
        'is_active' => false,
        'sort_order' => 1,
    ]);

    $groups = app(SeoCompareService::class)->getHubGroups();

    expect($groups)->toBe([]);
});
