<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Repositories\Bike\BikeModelRepository;
use App\Repositories\Bike\ListingRepository;
use App\Repositories\Bike\ManufacturerRepository;
use App\Repositories\Bike\CategoryRepository;
use Illuminate\Support\Collection;

/**
 * 車種マスタ・メーカー情報のビジネスロジック
 * フォルダ移動: Services/Bike/ 配下へ
 */
final class BikeService
{
    public function __construct(
        private readonly BikeModelRepository $modelRepo,
        private readonly ManufacturerRepository $manufacturerRepo,
        private readonly ListingRepository $listingRepo,
        private readonly CategoryRepository $categoryRepo
    ) {}

    /**
     * ★追加: 地域・都道府県データを取得
     */
    public function getRegions(): array
    {
        return config('bike.regions', []);
    }

    /**
     * トップページ表示用の主要メーカーを取得
     * (ホンダ、ヤマハ、スズキ、カワサキ、ハーレー の順で取得)
     */
    public function getMajorManufacturers(): Collection
    {
        // 全メーカーを取得
        $allMakers = $this->manufacturerRepo->getAllSortedByName();
        
        // 表示したいメーカー名のリスト（順序を維持したい場合）
        $targetNames = ['ホンダ', 'ヤマハ', 'スズキ', 'カワサキ', 'ハーレーダビッドソン'];
        
        $results = new Collection();

        foreach ($targetNames as $name) {
            // 名前で検索（部分一致など柔軟に）
            $found = $allMakers->first(function ($m) use ($name) {
                return str_contains($m->name, $name) || str_contains(strtolower($m->name), strtolower($name));
            });

            if ($found) {
                $results->push($found);
            }
        }

        return $results;
    }

    /**
     * ★修正: 全てのメーカーを取得するメソッドに変更
     * （以前の getMajorManufacturers を置き換え）
     */
    public function getAllManufacturers(): Collection
    {
        // ManufacturerRepository に getAllSortedByName がある前提です
        // もしエラーになる場合は getAll() に変えてください
        return $this->manufacturerRepo->getAllSortedByName();
    }

    /**
     * トップページ用カテゴリ一覧
     * ★修正: 画像があるカテゴリのみを返すように変更
     */
    public function getCategoriesForTopPage(): Collection
    {
        return $this->categoryRepo->getWithImagesSorted();
    }

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
        $manufacturers = $this->manufacturerRepo->getAll();
        
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

    /**
     * ★追加: 関連車両の取得 (Repositoryへ委譲)
     */
    public function getRelatedListings(int $bikeModelId, int $excludeId, int $limit = 8): Collection
    {
        return $this->listingRepo->getRelatedListings($bikeModelId, $excludeId, $limit);
    }
}