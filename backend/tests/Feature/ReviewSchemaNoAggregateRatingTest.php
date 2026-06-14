<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;

it('model-product JSON-LD omits aggregateRating but keeps Product + AggregateOffer', function () {
    $mfr = Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx', 'displacement' => 125]);

    // review が「あっても」schema には載らないことを確認（撤去前なら aggregateRating が出る条件）
    $html = view('components.jsonld.model-product', [
        'model' => $model,
        'stats' => ['count' => 10, 'min_raw' => 200000, 'max_raw' => 500000],
        'reviewStats' => ['count' => 3, 'avg_rating' => 4.7],
    ])->render();

    expect($html)->not->toContain('aggregateRating');
    expect($html)->not->toContain('AggregateRating');
    expect($html)->toContain('"@type": "Product"');
    expect($html)->toContain('AggregateOffer'); // 価格帯スキーマは維持
});

it('listing product JSON-LD omits aggregateRating but keeps Product', function () {
    $listing = (object) [
        'total_price' => '37.0', // 万円
        'condition' => '中古',
        'is_sold_out' => false,
        'images' => [],
        'description' => 'テスト車両の説明',
        'maker' => 'Honda',
        'name' => 'PCX',
        'shop_name' => 'テスト販売店',
        'prefecture' => '東京都',
    ];

    $html = view('components.jsonld.product', [
        'listing' => $listing,
        'reviewStats' => [
            'total' => 3,
            'design' => ['avg' => 5], 'engine' => ['avg' => 4], 'handling' => ['avg' => 4],
            'fuel_economy' => ['avg' => 5], 'cost_performance' => ['avg' => 4],
        ],
    ])->render();

    expect($html)->not->toContain('aggregateRating');
    expect($html)->not->toContain('AggregateRating');
    expect($html)->toContain('"@type": "Product"');
});
