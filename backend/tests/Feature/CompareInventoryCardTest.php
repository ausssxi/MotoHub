<?php

declare(strict_types=1);

function sampleListingCard(): array
{
    return [
        'id' => 12345,
        'images' => [],
        'bargain_score' => 20,
        'name' => 'テスト車両',
        'maker' => 'Honda',
        'category' => 'ネイキッド',
        'condition' => '中古',
        'prefecture' => '東京都',
        'tags' => [],
        'model_year' => '2020年',
        'mileage' => '10,000km',
        'displacement' => '250cc',
        'repair_history' => 'なし',
        'total_price' => '45.0',
        'base_price' => '40.0',
        'source_icon_key' => 'default',
        'source' => 'グーバイク',
        'site_name' => 'グーバイク',
        'store_name' => 'テスト販売店',
    ];
}

it('shows the bargain badge by default', function () {
    $html = view('bikes.partials.bike_card', ['listing' => sampleListingCard()])->render();

    expect($html)->toContain('お得');
});

it('suppresses the bargain badge when hideBargainBadge is true', function () {
    $html = view('bikes.partials.bike_card', [
        'listing' => sampleListingCard(),
        'hideBargainBadge' => true,
    ])->render();

    expect($html)->not->toContain('お得');
});
