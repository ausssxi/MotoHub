<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BikeRepository;
use Illuminate\Database\Eloquent\Collection;

final class BikeService
{
    public function __construct(
        private readonly BikeRepository $repository
    ) {}

    public function getPopularBikesForTopPage(): Collection
    {
        return $this->repository->getTopBikesByCount(16);
    }

    /**
     * 検索候補を取得
     */
    public function getSearchSuggestions(string $keyword): array
    {
        // 10件程度に絞って候補を取得
        $models = $this->repository->searchNamesByKeyword($keyword, 10);

        return $models->map(fn($m) => [
            'name' => $m->name,
            'count' => $m->listings_count,
        ])->toArray();
    }

    /**
     * 全車種一覧ページ用のデータを取得
     */
    public function getAllModelsForIndex(): array
    {
        return [
            'manufacturers' => $this->repository->getAllModelsGroupedByManufacturer(),
            'totalModelsCount' => $this->repository->countAllModels(),
        ];
    }
}