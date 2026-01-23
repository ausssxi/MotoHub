<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Repositories\Bike\ListingRepository;
use App\Repositories\Bike\ManufacturerRepository;
use App\Repositories\Bike\BikeModelRepository;
use App\Http\Resources\Bike\ListingResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * バイク出品情報の検索・絞り込みロジックを担当。
 * データ変換は ListingResource に委譲し、推論ロジックとUIメタデータの生成に集中します。
 */
final class ListingSearchService
{
    public function __construct(
        private readonly ListingRepository $listingRepo,
        private readonly ManufacturerRepository $manufacturerRepo,
        private readonly BikeModelRepository $modelRepo
    ) {}

    /**
     * ソートオプションの定義
     */
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

    /**
     * メイン検索メソッド
     */
    public function search(?string $keyword, ?string $prefecture = null, string $sort = 'latest', array $filters = [], int $perPage = 30): array
    {
        // 1. 車種IDからメーカーを自動補完
        if (!empty($filters['bike_model_id']) && empty($filters['manufacturer_id'])) {
            $model = $this->modelRepo->find((int)$filters['bike_model_id']);
            if ($model) $filters['manufacturer_id'] = $model->manufacturer_id;
        }

        // 2. キーワードからのインテリジェント推論（ハーレー等のブランド名や「その他」の誤判定を防止）
        if (!empty($keyword) && empty($filters['bike_model_id'])) {
            $inference = $this->inferFromKeyword($keyword);
            
            if (empty($filters['manufacturer_id']) && $inference['manufacturer_id']) {
                $filters['manufacturer_id'] = $inference['manufacturer_id'];
            }
            
            // 車種IDの固定（推論結果が「その他」でなければセット）
            if ($inference['bike_model_id']) {
                $filters['bike_model_id'] = $inference['bike_model_id'];
            }
        }

        // 3. データ取得
        $paginated = $this->listingRepo->searchByKeyword($keyword, $prefecture, $sort, $filters, $perPage);
        $statsRaw = $this->listingRepo->getPriceStats($keyword, $prefecture, $filters);

        // 4. 付加情報の取得（filters を渡してスライダーの目盛りを車種に連動させる）
        $searchMeta = $this->getSearchMetadata($keyword, $prefecture, $filters);

        $models = collect();
        if (!empty($filters['manufacturer_id'])) {
            $models = $this->modelRepo->getByManufacturerId((int)$filters['manufacturer_id']);
        }

        return [
            // ✨ ListingResource を使用してデータ変換ロジックを共通化
            'items'         => ListingResource::collection($paginated->getCollection())->resolve(),
            'pagination'    => $this->formatPagination($paginated),
            'stats'         => $this->formatStats($statsRaw), // ✨ 赤波線解消：下記にメソッドを定義
            'meta'          => $searchMeta, 
            'manufacturers' => $this->manufacturerRepo->getAllSortedByName(),
            'models'        => $models,
            'regions'       => $this->getRegions(),
            'prefectures'   => $this->getPrefectures(), // ✨ config と同期
            'filters'       => $filters,
            'sortOptions'   => $this->getSortOptions(),
        ];
    }

    /**
     * ✨ 赤波線解消：統計データのフォーマット
     */
    private function formatStats(object $stats): array
    {
        return [
            'avg'   => $stats->avg_price ? number_format((float)($stats->avg_price / 10000), 1) : null,
            'min'   => $stats->min_price ? number_format((float)($stats->min_price / 10000), 1) : null,
            'max'   => $stats->max_price ? number_format((float)($stats->max_price / 10000), 1) : null,
            'count' => $stats->count,
        ];
    }

    /**
     * ハイブリッド型メタデータ生成（不具合修正版）
     * スライダーの上限が跳ね上がるのを防ぐため、範囲フィルタを隔離して計算します。
     */
    public function getSearchMetadata(?string $keyword = null, ?string $prefecture = null, array $filters = []): array
    {
        /**
         * ✨ 解決の鍵: 「35万の呪縛」を解く
         * スライダーの「器（上限値）」を決めるためのクエリからは、現在のスライダー入力値を完全に除外します。
         * これをしないと、カブ(35万)の設定が残ったままPCX(65万)に変えた際、
         * 「PCXかつ35万以下」のバイクが見つからず 0件(NULL)となり、1000万への跳ね上がりが発生します。
         */
        $rangeKeys = ['min_price', 'max_price', 'min_mileage', 'max_mileage', 'min_year', 'max_year'];
        $cleanFilters = array_diff_key($filters, array_flip($rangeKeys));

        // リポジトリで「車種などの構造的な条件のみ」に基づいた純粋な上限を取得
        $stats = $this->listingRepo->getMinMaxStats($keyword, $prefecture, $cleanFilters);

        // 実績値（万円単位）。データがない場合は 0。
        $rawPrice = $stats->max_price ? (int) ceil($stats->max_price / 10000) : 0;
        $rawMileage = (int) ($stats->max_mileage ?? 0);

        return [
            'price' => [
                'min' => 0,
                'max' => $this->roundUpPrice($rawPrice) // キリの良い天井へ
            ],
            'mileage' => [
                'min' => 0,
                'max' => $this->roundUpMileage($rawMileage)
            ],
            'year' => [
                'min' => (int) ($stats->min_year ?? 1990),
                'max' => (int) ($stats->max_year ?? (int) date('Y')),
            ]
        ];
    }

    /**
     * 価格の上限を丸める（ハイブリッドUI）
     */
    private function roundUpPrice(int $price): int
    {
        // NULLや0の場合は300万円を標準にする
        if ($price <= 0) return 300;     
        if ($price <= 50) return 50;     // カブ等：上限50万
        if ($price <= 100) return 100;   // PCX等：上限100万
        if ($price <= 200) return 200;   
        if ($price <= 300) return 300;   
        if ($price <= 500) return 500;   
        
        // それ以上は 100万単位で切り上げ（1000万にならないよう実績値をベースにする）
        return (int) ceil($price / 100) * 100;
    }

    /**
     * 走行距離の上限を丸める
     */
    private function roundUpMileage(int $mileage): int
    {
        if ($mileage <= 0) return 50000;
        if ($mileage <= 10000) return 10000;
        if ($mileage <= 30000) return 30000;
        if ($mileage <= 50000) return 50000;
        if ($mileage <= 100000) return 100000;
        return (int) ceil($mileage / 50000) * 50000;
    }

    /**
     * キーワード推論エンジン（ハーレー、その他問題の修正版）
     */
    private function inferFromKeyword(string $keyword): array
    {
        $res = ['manufacturer_id' => null, 'bike_model_id' => null];
        if (mb_strlen($keyword) < 2) return $res;

        $normalizedKeyword = $this->normalizeString($keyword);

        // ブランド名単体での検索かチェック
        $allManufacturers = $this->manufacturerRepo->getAll();
        $matchedManufacturer = $allManufacturers->first(fn($m) => $this->normalizeString($m->name) === $normalizedKeyword);

        if ($matchedManufacturer) {
            $res['manufacturer_id'] = (int)$matchedManufacturer->id;
            return $res;
        }

        // 車種検索
        $matchedModels = $this->modelRepo->searchByName($keyword, 30);
        if ($matchedModels->isEmpty()) return $res;

        $mIds = $matchedModels->pluck('manufacturer_id')->unique();
        if ($mIds->count() === 1) $res['manufacturer_id'] = (int)$mIds->first();

        $exactMatch = $matchedModels->first(fn($m) => $this->normalizeString($m->name) === $normalizedKeyword);

        if ($exactMatch) {
            if (!str_contains($exactMatch->name, 'その他')) {
                $res['bike_model_id'] = (int)$exactMatch->id;
                $res['manufacturer_id'] = (int)$exactMatch->manufacturer_id;
            } else {
                $res['manufacturer_id'] = (int)$exactMatch->manufacturer_id;
            }
        } elseif ($matchedModels->count() === 1) {
            $model = $matchedModels->first();
            if (!str_contains($model->name, 'その他')) {
                $res['bike_model_id'] = (int)$model->id;
            }
        }

        return $res;
    }

    /**
     * ページネーションの高度な整形（三点リーダー対応）
     */
    private function formatPagination(LengthAwarePaginator $paginated): array {
        $currentPage = $paginated->currentPage();
        $lastPage = $paginated->lastPage();
        $pages = [];
        
        $pages[] = $this->makePageItem(1, $paginated);
        if ($currentPage - 1 > 2) $pages[] = ['is_dot' => true, 'label' => '...', 'url' => null, 'is_active' => false];
        
        for ($i = max(2, $currentPage - 1); $i <= min($lastPage - 1, $currentPage + 1); $i++) {
            $pages[] = $this->makePageItem($i, $paginated);
        }
        
        if ($currentPage + 1 < $lastPage - 1) $pages[] = ['is_dot' => true, 'label' => '...', 'url' => null, 'is_active' => false];
        if ($lastPage > 1) $pages[] = $this->makePageItem($lastPage, $paginated);

        return [
            'total'        => $paginated->total(),
            'current_page' => $currentPage,
            'last_page'    => $lastPage,
            'prev_url'     => $paginated->previousPageUrl(),
            'next_url'     => $paginated->nextPageUrl(),
            'pages'        => $pages,
        ];
    }

    private function makePageItem(int $page, LengthAwarePaginator $paginated): array {
        return [
            'label' => $page,
            'url' => $paginated->url($page),
            'is_active' => $page === $paginated->currentPage(),
            'is_dot' => false,
        ];
    }

    /**
     * 地域データの取得 (config/bike.php 同期)
     */
    public function getRegions(): array { return config('bike.regions', []); }
    private function getPrefectures(): array { return collect($this->getRegions())->flatten()->toArray(); }

    private function normalizeString(string $str): string {
        return Str::lower(str_replace([' ', '　'], '', mb_convert_kana($str, "asKV")));
    }

    public function getActiveCount(): int { return $this->listingRepo->countActiveListings(); }
    public function getModelsByManufacturer(int $mid): Collection { return $this->modelRepo->getByManufacturerId($mid); }
    public function getFilteredCount($k, $p, $f): int { return (int) $this->listingRepo->searchByKeyword($k, $p, 'latest', $f, 1)->total(); }
}