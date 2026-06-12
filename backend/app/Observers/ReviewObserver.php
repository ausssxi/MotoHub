<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Review;
use Illuminate\Support\Facades\Cache;

/**
 * レビューの作成/更新(承認含む)/削除時に、モデル詳細ページの
 * フルページキャッシュをパージする（ListingObserverと同パターン）。
 * 公開投稿(BikeService::createReview)もFilament管理画面も
 * Eloquent経由なので、ここ1箇所で全フローをカバーする。
 */
final class ReviewObserver
{
    public function saved(Review $review): void
    {
        $this->purgeModelDetailCache($review);
    }

    public function deleted(Review $review): void
    {
        $this->purgeModelDetailCache($review);
    }

    private function purgeModelDetailCache(Review $review): void
    {
        if (!$review->bike_model_id) {
            return;
        }

        $bikeModel = $review->bikeModel()->with('manufacturer')->first();
        if (!$bikeModel || !$bikeModel->manufacturer) {
            return;
        }

        $mfrSlug = $bikeModel->manufacturer->slug;
        $modelSlug = $bikeModel->slug ?? $bikeModel->id;

        Cache::forget(\App\Models\BikeModel::modelDetailCacheKey($mfrSlug, $modelSlug));
    }
}
