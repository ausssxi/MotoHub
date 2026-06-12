<?php

declare(strict_types=1);

use App\Models\BikeModel;
use App\Models\Manufacturer;
use App\Models\Review;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

it('purges the model detail cache when a review is posted', function () {
    // reCAPTCHA検証の外部HTTPをfake（success + score 0.9）
    Http::fake([
        'www.google.com/*' => Http::response(['success' => true, 'score' => 0.9]),
    ]);

    $mfr = Manufacturer::forceCreate(['name' => 'Honda', 'slug' => 'honda']);
    $model = BikeModel::create([
        'manufacturer_id' => $mfr->id,
        'name' => 'レブル250',
        'slug' => 'rebel-250',
    ]);

    // モデル詳細ページのフルページキャッシュが温まっている状態を再現
    $cacheKey = BikeModel::modelDetailCacheKey('honda', 'rebel-250');
    Cache::put($cacheKey, ['stale' => 'html'], 3600);

    $response = $this->postJson("/bikes/models/{$model->id}/reviews", [
        'nickname' => 'テストライダー',
        'rating' => 5,
        'title' => '最高の相棒',
        'body' => '足つきが良く初心者でも扱いやすい。',
        'recaptcha_token' => 'dummy-token',
    ]);

    $response->assertOk()->assertJson(['success' => true]);

    // DBに保存され、当該モデルの詳細キャッシュキーが消えている
    expect(Review::where('bike_model_id', $model->id)->count())->toBe(1);
    expect(Cache::has($cacheKey))->toBeFalse();
});

it('purges the model detail cache when a review is deleted (admin flow)', function () {
    $mfr = Manufacturer::forceCreate(['name' => 'Yamaha', 'slug' => 'yamaha']);
    $model = BikeModel::create([
        'manufacturer_id' => $mfr->id,
        'name' => 'SR400',
        'slug' => 'sr400',
    ]);
    $review = Review::create([
        'bike_model_id' => $model->id,
        'nickname' => 'テスト',
        'rating' => 4,
        'title' => 't',
        'body' => 'b',
        'is_approved' => true,
    ]);

    $cacheKey = BikeModel::modelDetailCacheKey('yamaha', 'sr400');
    Cache::put($cacheKey, ['stale' => 'html'], 3600);

    $review->delete();

    expect(Cache::has($cacheKey))->toBeFalse();
});
