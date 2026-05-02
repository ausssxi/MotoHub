<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Listing;
use Illuminate\Support\Facades\Cache;

final class ListingObserver
{
    public function saved(Listing $listing): void
    {
        $this->purgeModelDetailCache($listing);
    }

    public function deleted(Listing $listing): void
    {
        $this->purgeModelDetailCache($listing);
    }

    private function purgeModelDetailCache(Listing $listing): void
    {
        if (!$listing->bike_model_id) {
            return;
        }

        $bikeModel = $listing->bikeModel()->with('manufacturer')->first();
        if (!$bikeModel || !$bikeModel->manufacturer) {
            return;
        }

        $mfrSlug = $bikeModel->manufacturer->slug;
        $modelSlug = $bikeModel->slug ?? $bikeModel->id;

        Cache::forget("model_detail_v1_{$mfrSlug}_{$modelSlug}");
    }
}
