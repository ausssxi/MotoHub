<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Repositories\Bike\BikeModelRepository;
use App\Repositories\Bike\ManufacturerRepository;
use Illuminate\Support\Collection;

/**
 * 車種マスタ・メーカー情報のビジネスロジック
 * フォルダ移動: Services/Bike/ 配下へ
 */
final class BikeService
{
    public function __construct(
        private readonly BikeModelRepository $modelRepo,
        private readonly ManufacturerRepository $manufacturerRepo
    ) {}

    /**
     * トップページ用の人気車種
     */
    public function getPopularBikesForTopPage(): Collection
    {
        return $this->modelRepo->getTopModels(16);
    }

    /**
     * 検索サジェスト用
     */
    public function getSearchSuggestions(string $keyword): array
    {
        $models = $this->modelRepo->searchByName($keyword, 10);
        return $models->map(fn($m) => [
            'name' => $m->name,
            'count' => $m->listings_count,
        ])->toArray();
    }

    /**
     * 車種一覧ページ用のデータ
     */
    public function getAllModelsForIndex(): array
    {
        $manufacturers = $this->manufacturerRepo->getAllSortedByName();
        
        // 各メーカーに車種を紐付けて取得
        $manufacturers->each(function ($m) {
            $m->bike_models = $this->modelRepo->getByManufacturerId((int)$m->id);
            $m->bike_models_count = $m->bike_models->count();
        });

        return [
            'manufacturers' => $manufacturers,
            'totalModelsCount' => $manufacturers->sum('bike_models_count')
        ];
    }
}