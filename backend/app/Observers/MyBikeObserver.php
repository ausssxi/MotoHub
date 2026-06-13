<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\MyBike;
use Illuminate\Support\Facades\Cache;

/**
 * ガレージ(MyBike)の作成/更新/削除時に、モデル詳細ページの
 * フルページキャッシュをパージする（ReviewObserver / ListingObserverと同パターン）。
 * モデル詳細の「オーナー」欄($owners)に当該車種の登録が表示されるため、
 * 登録/削除がEloquent経由で行われる本リポジトリ全フローをここ1箇所でカバーする。
 */
final class MyBikeObserver
{
    public function saved(MyBike $myBike): void
    {
        $this->purgeModelDetailCache($myBike);
    }

    public function deleted(MyBike $myBike): void
    {
        $this->purgeModelDetailCache($myBike);
    }

    private function purgeModelDetailCache(MyBike $myBike): void
    {
        if (!$myBike->bike_model_id) {
            return;
        }

        $bikeModel = $myBike->bikeModel()->with('manufacturer')->first();
        if (!$bikeModel || !$bikeModel->manufacturer) {
            return;
        }

        $mfrSlug = $bikeModel->manufacturer->slug;
        $modelSlug = $bikeModel->slug ?? $bikeModel->id;

        Cache::forget(\App\Models\BikeModel::modelDetailCacheKey($mfrSlug, $modelSlug));
    }
}
