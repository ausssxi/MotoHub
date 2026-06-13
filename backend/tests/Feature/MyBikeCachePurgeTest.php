<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\User;
use App\Repositories\MyBike\MyBikeRepository;
use Illuminate\Support\Facades\Cache;

it('purges the model detail cache when a garage bike is registered', function () {
    $mfr = Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::create([
        'manufacturer_id' => $mfr->id,
        'name' => 'レブル250',
        'slug' => 'rebel-250',
    ]);
    $user = User::factory()->create();

    // モデル詳細ページのフルページキャッシュが温まっている状態を再現
    $cacheKey = BikeModel::modelDetailCacheKey('honda', 'rebel-250');
    Cache::put($cacheKey, ['stale' => 'html'], 3600);

    app(MyBikeRepository::class)->create($user, [
        'bike_model_id' => $model->id,
        'name' => 'マイレブル',
    ]);

    expect(Cache::has($cacheKey))->toBeFalse();
});

it('purges the model detail cache when a garage bike is deleted', function () {
    $mfr = Manufacturer::forceCreate(['name' => 'Yamaha', 'slug' => 'yamaha']);
    $model = BikeModel::create([
        'manufacturer_id' => $mfr->id,
        'name' => 'SR400',
        'slug' => 'sr400',
    ]);
    $user = User::factory()->create();

    $repo = app(MyBikeRepository::class);
    $myBike = $repo->create($user, [
        'bike_model_id' => $model->id,
        'name' => 'マイSR',
    ]);

    // 登録時のパージ後、キャッシュを温め直してから削除を検証
    $cacheKey = BikeModel::modelDetailCacheKey('yamaha', 'sr400');
    Cache::put($cacheKey, ['stale' => 'html'], 3600);

    $repo->delete($myBike);

    expect(Cache::has($cacheKey))->toBeFalse();
});
