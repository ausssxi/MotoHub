<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\BikeModel;
use App\Models\Manufacturer;
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

    /**
     * サジェスト用に名前で検索
     */
    public function searchNamesByKeyword(string $keyword, int $limit = 10): Collection
    {
        return BikeModel::query()
            ->where('name', 'like', "%{$keyword}%")
            ->withCount(['listings' => fn($q) => $q->where('is_sold_out', false)])
            ->orderBy('listings_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 全車種をメーカーごとに取得する
     * メーカーの表示順はID昇順に変更
     */
    public function getAllModelsGroupedByManufacturer(): Collection
    {
        return Manufacturer::query()
            ->with(['bikeModels' => function($query) {
                $query->withCount('listings')->orderBy('listings_count', 'desc');
            }])
            ->withCount('bikeModels')
            ->orderBy('id', 'asc') // 名前順(name)からID順に変更
            ->get();
    }

    /**
     * 全車種の総数を取得
     */
    public function countAllModels(): int
    {
        return BikeModel::count();
    }
}