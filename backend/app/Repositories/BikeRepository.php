<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\BikeModel;
use Illuminate\Database\Eloquent\Collection;

final class BikeRepository
{
    /**
     * Get bike models sorted by the number of listings.
     *
     * @param int|null $limit Number of items to retrieve (Defaults to config value)
     * @return Collection
     */
    public function getTopBikesByCount(?int $limit = null): Collection
    {
        // Get values from config/bike.php
        $limit = $limit ?? config('bike.ranking.top_page_limit', 16);
        $excludedNames = config('bike.ranking.excluded_names', ['他車種']);

        return BikeModel::query()
            // Exclude specific model names based on config
            ->whereNotIn('name', $excludedNames)
            ->withCount('listings')
            // Sort by the aggregated listings_count
            ->orderBy('listings_count', 'desc')
            ->limit($limit)
            ->get();
    }
}