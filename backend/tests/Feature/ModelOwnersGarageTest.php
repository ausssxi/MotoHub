<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\MyBike;
use App\Models\User;
use App\Services\MyBike\MyBikeService;

// 注: 車種ページ全体(/bikes/{mfr}/{slug})は priceStats が MySQL専用関数(DATEDIFF)を使うため
// SQLite では描画できない（既存も実機 curl で確認）。ここではオーナーカード生成ロジック
// (publicGarageCardsForModel = 表示の単一ソース) を直接検証する。ページ描画は実機確認で担保。

function ownersModel(string $slug = 'pcx', string $name = 'PCX'): BikeModel
{
    $mfr = Manufacturer::where('slug', 'honda')->first() ?? Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);

    return BikeModel::where('slug', $slug)->first() ?? BikeModel::create(['manufacturer_id' => $mfr->id, 'name' => $name, 'slug' => $slug]);
}

function ownerBike(BikeModel $model, bool $public, string $handle = 'rider_x', string $realName = '本名タロウ'): MyBike
{
    $user = User::factory()->create(['name' => $realName, 'review_display_name' => $handle]);

    return MyBike::create([
        'user_id' => $user->id, 'bike_model_id' => $model->id, 'name' => 'マイ'.$handle,
        'is_public' => $public, 'initial_odometer' => 0, 'current_odometer' => 24000,
    ]);
}

it('returns public garages of the model with handle + stats (single source for the card)', function () {
    $model = ownersModel();
    $bike = ownerBike($model, true);
    $svc = app(MyBikeService::class);
    $svc->recordFuel($bike, ['filled_at' => '2026-01-01', 'odometer' => 23500, 'quantity' => 5, 'is_full_tank' => 1]);
    $svc->recordFuel($bike, ['filled_at' => '2026-02-01', 'odometer' => 24000, 'quantity' => 5, 'is_full_tank' => 1]);
    $svc->recordMaintenance($bike, ['maintained_at' => '2026-02-01', 'title' => 'オイル交換', 'odometer' => 24000, 'cost' => 3000]);

    $result = $svc->publicGarageCardsForModel($model->id);
    expect($result['total'])->toBe(1)
        ->and($result['cards'])->toHaveCount(1);

    $card = $result['cards']->first();
    expect($card['id'])->toBe($bike->id)
        ->and($card['handle'])->toBe('rider_x')
        ->and($card['odometer'])->toBe(24000)
        ->and($card['total_cost'])->toBe(3000)
        ->and($card['avg_efficiency'])->toBe(100.0); // 500km / 5L
});

it('excludes private garages and other-model garages', function () {
    $model = ownersModel();
    ownerBike($model, false, 'secret_rider');          // 非公開
    $other = ownersModel('cb400', 'CB400');
    ownerBike($other, true, 'other_rider');            // 別車種・公開

    $result = app(MyBikeService::class)->publicGarageCardsForModel($model->id);
    expect($result['total'])->toBe(0)
        ->and($result['cards'])->toHaveCount(0);

    // 別車種側には別車種のみ
    $otherResult = app(MyBikeService::class)->publicGarageCardsForModel($other->id);
    expect($otherResult['cards']->pluck('handle')->all())->toBe(['other_rider']);
});

it('never exposes the real name; uses the handle, with 名無しライダー fallback', function () {
    $model = ownersModel();
    $bike = ownerBike($model, true, 'rider_x', '漏れ太郎');
    $noHandle = ownerBike($model, true, 'tmp', '名前バレ太郎');
    $noHandle->user->update(['review_display_name' => null]);

    $cards = app(MyBikeService::class)->publicGarageCardsForModel($model->id)['cards'];
    $handles = $cards->pluck('handle')->all();

    expect($handles)->toContain('rider_x')
        ->toContain('名無しライダー')
        ->not->toContain('漏れ太郎')
        ->not->toContain('名前バレ太郎');
});

it('reports the true total even when more than the card limit exist (もっと見る)', function () {
    $model = ownersModel();
    foreach (range(1, 8) as $i) {
        ownerBike($model, true, 'rider_'.$i);
    }

    $result = app(MyBikeService::class)->publicGarageCardsForModel($model->id, 6);
    expect($result['total'])->toBe(8)          // 総数は正確
        ->and($result['cards'])->toHaveCount(6); // カードは上限まで
});

it('graceful with no records (0km / ¥0 / null efficiency)', function () {
    $model = ownersModel();
    ownerBike($model, true);

    $card = app(MyBikeService::class)->publicGarageCardsForModel($model->id)['cards']->first();
    expect($card['total_cost'])->toBe(0)
        ->and($card['avg_efficiency'])->toBeNull();
});
