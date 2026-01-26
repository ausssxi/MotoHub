<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Repositories\Bike\ListingRepository;
use App\Repositories\Bike\ListingStatsRepository; // ✨ 追加: 統計用リポジトリ
use App\Repositories\Bike\ManufacturerRepository;
use App\Repositories\Bike\BikeModelRepository;
use App\Http\Resources\Bike\ListingResource;
use App\Services\Bike\Search\KeywordInferrer;     // ✨ 追加
use App\Services\Bike\Search\SearchMetadataGenerator; // ✨ 追加
use App\Services\Bike\Search\PaginationFormatter; // ✨ 追加
use Illuminate\Support\Collection;

/**
 * バイク出品情報の検索・絞り込みロジックを担当。
 * 詳細なロジックは専用のヘルパークラスに移譲し、全体のフロー制御に集中します。
 */
final class ListingSearchService
{
    public function __construct(
        private readonly ListingRepository $listingRepo,
        private readonly ListingStatsRepository $statsRepo, // ✨ 統計用
        private readonly ManufacturerRepository $manufacturerRepo,
        private readonly BikeModelRepository $modelRepo,
        // ✨ 新しいヘルパークラス
        private readonly KeywordInferrer $inferrer,
        private readonly SearchMetadataGenerator $metaGenerator,
        private readonly PaginationFormatter $paginator
    ) {}

    public function search(?string $keyword, ?string $prefecture = null, string $sort = 'latest', array $filters = [], int $perPage = 30): array
    {
        // 1. 車種IDからメーカーを自動補完
        if (!empty($filters['bike_model_id']) && empty($filters['manufacturer_id'])) {
            $model = $this->modelRepo->find((int)$filters['bike_model_id']);
            if ($model) $filters['manufacturer_id'] = $model->manufacturer_id;
        }

        // 2. キーワード推論（Inferrerへ委譲）
        if (!empty($keyword) && empty($filters['bike_model_id'])) {
            $inference = $this->inferrer->infer($keyword);
            
            if (empty($filters['manufacturer_id']) && $inference['manufacturer_id']) {
                $filters['manufacturer_id'] = $inference['manufacturer_id'];
            }
            if ($inference['bike_model_id']) {
                $filters['bike_model_id'] = $inference['bike_model_id'];
            }
        }

        // 3. メタデータとUI上限値の計算 (検索前に実行)
        // これにより、検索クエリに適切な上限値(max_priceなど)を渡せます
        $searchMeta = $this->metaGenerator->generate($keyword, $prefecture, $filters);
        $uiParams = $this->metaGenerator->calculateUiLimits($searchMeta);

        // 4. データ取得 (UI上限値を渡す)
        $paginated = $this->listingRepo->searchByKeyword($keyword, $prefecture, $sort, $filters, $perPage, $uiParams);
        
        // 5. 統計取得 (StatsRepoを使用)
        $statsRaw = $this->statsRepo->getPriceStats($keyword, $prefecture, $filters);

        // 6. 付加情報の取得（メーカー・車種リストなど）
        $models = collect();
        if (!empty($filters['manufacturer_id'])) {
            $models = $this->modelRepo->getByManufacturerId((int)$filters['manufacturer_id']);
        }

        return [
            'items'         => ListingResource::collection($paginated->getCollection())->resolve(),
            'pagination'    => $this->paginator->format($paginated), // PaginatorFormatterへ委譲
            'stats'         => $this->metaGenerator->formatStats($statsRaw), // MetadataGeneratorへ委譲
            'meta'          => $searchMeta,
            'manufacturers' => $this->manufacturerRepo->getAllSortedByName(),
            'models'        => $models,
            'regions'       => config('bike.regions', []),
            'prefectures'   => collect(config('bike.regions', []))->flatten()->toArray(),
            'filters'       => $filters,
            'sortOptions'   => $this->getSortOptions(),
        ];
    }

    /**
     * スライダー用メタデータを取得
     */
    public function getSearchMetadata(?string $keyword = null, ?string $prefecture = null, array $filters = []): array
    {
        return $this->metaGenerator->generate($keyword, $prefecture, $filters);
    }

    public function getSortOptions(): array
    {
        return [
            'latest'       => '新着順',
            'price_asc'    => '価格の安い順',
            'price_desc'   => '価格の高い順',
            'mileage_asc'  => '走行距離が少ない',
            'mileage_desc' => '走行距離が多い',
            'year_desc'    => '年式が新しい',
            'year_asc'     => '年式が古い',
        ];
    }

    // 簡易メソッド (StatsRepoに移譲)
    public function getActiveCount(): int { return $this->statsRepo->countActiveListings(); }
    public function getModelsByManufacturer(int $mid): Collection { return $this->modelRepo->getByManufacturerId($mid); }
    
    // フィルタ適用後の件数取得（モバイル用）
    public function getFilteredCount($k, $p, $f): int { 
        // 件数取得時もUIリミットを考慮する必要があるため計算
        $meta = $this->metaGenerator->generate($k, $p, $f);
        $uiParams = $this->metaGenerator->calculateUiLimits($meta);
        return (int) $this->listingRepo->searchByKeyword($k, $p, 'latest', $f, 1, $uiParams)->total(); 
    }
}