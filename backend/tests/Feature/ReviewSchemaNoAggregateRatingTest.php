<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;

it('model-product JSON-LD gates aggregateRating on 2+ approved reviews (Phase2)', function () {
    $mfr = Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => 'PCX', 'slug' => 'pcx', 'displacement' => 125]);
    $stats = ['count' => 10, 'min_raw' => 200000, 'max_raw' => 500000];

    // 承認済み2件以上 → AggregateRating を出す（is_approved のみ集計＝シード除外は controller 側）
    $with3 = view('components.jsonld.model-product', ['model' => $model, 'stats' => $stats, 'reviewStats' => ['count' => 3, 'avg_rating' => 4.7]])->render();
    // 1件以下 → 薄い/自作自演回避のため出さない
    $with1 = view('components.jsonld.model-product', ['model' => $model, 'stats' => $stats, 'reviewStats' => ['count' => 1, 'avg_rating' => 5.0]])->render();

    expect($with3)->toContain('AggregateRating')->toContain('"@type": "Product"')->toContain('AggregateOffer')
        ->and($with1)->not->toContain('AggregateRating');
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
