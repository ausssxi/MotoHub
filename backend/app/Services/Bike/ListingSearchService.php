<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Repositories\Bike\ListingRepository;
use App\Repositories\Bike\ManufacturerRepository;
use App\Repositories\Bike\BikeModelRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * バイク出品情報の検索・絞り込みロジックを担当
 * フォルダ移動: Services/Bike/ 配下へ
 */
final class ListingSearchService
{
    public function __construct(
        private readonly ListingRepository $listingRepo,
        private readonly ManufacturerRepository $manufacturerRepo,
        private readonly BikeModelRepository $modelRepo
    ) {}

    /**
     * ✨ 修正：UIで表示するソートオプションの定義
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

    public function search(?string $keyword, ?string $prefecture = null, string $sort = 'latest', array $filters = [], int $perPage = 30): array
    {
        // 1. 車種IDからメーカーを補完
        if (!empty($filters['bike_model_id']) && empty($filters['manufacturer_id'])) {
            $model = $this->modelRepo->find((int)$filters['bike_model_id']);
            if ($model) {
                $filters['manufacturer_id'] = $model->manufacturer_id;
            }
        }

        // 2. キーワードからのインテリジェント推論
        if (!empty($keyword) && empty($filters['bike_model_id'])) {
            $inference = $this->inferFromKeyword($keyword);
            if (empty($filters['manufacturer_id']) && $inference['manufacturer_id']) {
                $filters['manufacturer_id'] = $inference['manufacturer_id'];
            }
            if ($inference['bike_model_id']) {
                $filters['bike_model_id'] = $inference['bike_model_id'];
            }
        }

        // 3. リポジトリでデータ取得
        $paginated = $this->listingRepo->searchByKeyword($keyword, $prefecture, $sort, $filters, $perPage);
        $statsRaw = $this->listingRepo->getPriceStats($keyword, $prefecture, $filters);

        // 4. サイドバー用の車種リスト
        $models = collect();
        if (!empty($filters['manufacturer_id'])) {
            $models = $this->modelRepo->getByManufacturerId((int)$filters['manufacturer_id']);
        }

        return [
            'items' => $this->formatItems($paginated->getCollection()),
            'pagination' => $this->formatPagination($paginated),
            'stats' => [
                'avg'   => $statsRaw->avg_price ? number_format((float)($statsRaw->avg_price / 10000), 1) : null,
                'min'   => $statsRaw->min_price ? number_format((float)($statsRaw->min_price / 10000), 1) : null,
                'max'   => $statsRaw->max_price ? number_format((float)($statsRaw->max_price / 10000), 1) : null,
                'count' => $statsRaw->count,
            ],
            'manufacturers' => $this->manufacturerRepo->getAll(),
            'models'        => $models,
            'prefectures'   => $this->getPrefectures(),
            'filters'       => $filters,
            'sortOptions'   => $this->getSortOptions(), // ✨ ビューに渡すために追加
        ];
    }

    public function getFilteredCount(?string $keyword, ?string $prefecture, array $filters): int
    {
        if (!empty($filters['bike_model_id']) && empty($filters['manufacturer_id'])) {
            $model = $this->modelRepo->find((int)$filters['bike_model_id']);
            if ($model) $filters['manufacturer_id'] = $model->manufacturer_id;
        }

        if (!empty($keyword) && empty($filters['bike_model_id'])) {
            $inference = $this->inferFromKeyword($keyword);
            if (empty($filters['manufacturer_id'])) $filters['manufacturer_id'] = $inference['manufacturer_id'];
            $filters['bike_model_id'] = $inference['bike_model_id'];
        }

        $paginated = $this->listingRepo->searchByKeyword($keyword, $prefecture, 'latest', $filters, 1);
        return (int) $paginated->total();
    }

    private function inferFromKeyword(string $keyword): array
    {
        $res = ['manufacturer_id' => null, 'bike_model_id' => null];
        if (mb_strlen($keyword) < 2) return $res;

        $matchedModels = $this->modelRepo->searchByName($keyword, 30);
        if ($matchedModels->isEmpty()) return $res;

        $mIds = $matchedModels->pluck('manufacturer_id')->unique();
        if ($mIds->count() === 1) $res['manufacturer_id'] = (int)$mIds->first();

        $normalizedKeyword = $this->normalizeString($keyword);
        $exactMatch = $matchedModels->first(fn($m) => $this->normalizeString($m->name) === $normalizedKeyword);

        if ($exactMatch) {
            $res['bike_model_id'] = (int)$exactMatch->id;
            $res['manufacturer_id'] = (int)$exactMatch->manufacturer_id;
        } elseif ($matchedModels->count() === 1) {
            $res['bike_model_id'] = (int)$matchedModels->first()->id;
        } else {
            $bestMatch = $matchedModels->filter(fn($m) => str_starts_with($this->normalizeString($m->name), $normalizedKeyword))
                                      ->sortBy(fn($m) => strlen($m->name))
                                      ->first();
            if ($bestMatch) $res['bike_model_id'] = (int)$bestMatch->id;
        }

        return $res;
    }

    public function getSearchMetadata(?string $keyword = null, ?string $prefecture = null): array
    {
        $stats = $this->listingRepo->getMinMaxStats($keyword, $prefecture);
        $dbMaxPrice = (int) ceil(($stats->max_price ?? 0) / 10000);
        $dbMaxMileage = (int) ceil(($stats->max_mileage ?? 0) / 1000) * 1000;

        return [
            'price' => ['min' => 0, 'max' => max(300, $dbMaxPrice)],
            'mileage' => ['min' => 0, 'max' => max(50000, $dbMaxMileage)],
            'year' => [
                'min' => (int) ($stats->min_year ?? 1990),
                'max' => (int) ($stats->max_year ?? (int) date('Y')),
            ]
        ];
    }

    private function normalizeString(string $str): string {
        $str = mb_convert_kana($str, "asKV");
        $str = str_replace([' ', '　'], '', $str);
        return Str::lower($str);
    }

    private function formatItems(Collection $collection): array {
        return $collection->map(fn($item) => [
            'id' => $item->id,
            'source' => $this->resolveSourceDisplayName($item->site?->name ?? ''),
            'source_domain' => $this->resolveSourceDomain($item->site?->name ?? ''),
            'maker' => $item->bikeModel?->manufacturer?->name ?? '不明',
            'name' => $item->title ?? $item->bikeModel?->name ?? '車種名不明',
            'model_year' => $item->model_year ? "{$item->model_year}年" : '不明',
            'mileage' => $item->mileage !== null ? number_format($item->mileage) . 'km' : '走行不明',
            'displacement' => $item->bikeModel?->displacement ? "{$item->bikeModel->displacement}cc" : '-',
            'repair_history' => $item->has_repair_history ? 'あり' : 'なし',
            'condition' => $item->condition ?? '不明', 
            'total_price' => $item->total_price ? number_format((float)($item->total_price / 10000), 1) : '-',
            'base_price' => $item->price ? number_format((float)($item->price / 10000), 1) : '-',
            'store_name' => $item->shop?->name ?? '個人出品等',
            'url' => $item->source_url,
            'images' => $this->resolveImageUrls($item->local_image_paths),
        ])->toArray();
    }

    private function formatPagination(LengthAwarePaginator $paginated): array {
        $currentPage = $paginated->currentPage();
        $lastPage = $paginated->lastPage();
        $pages = [];
        $pages[] = $this->makePageItem(1, $paginated);
        if ($currentPage - 1 > 2) $pages[] = ['is_dot' => true];
        for ($i = max(2, $currentPage - 1); $i <= min($lastPage - 1, $currentPage + 1); $i++) {
            $pages[] = $this->makePageItem($i, $paginated);
        }
        if ($currentPage + 1 < $lastPage - 1) $pages[] = ['is_dot' => true];
        if ($lastPage > 1) $pages[] = $this->makePageItem($lastPage, $paginated);

        return [
            'total' => $paginated->total(),
            'current_page' => $currentPage,
            'last_page' => $lastPage,
            'prev_url' => $paginated->previousPageUrl(),
            'next_url' => $paginated->nextPageUrl(),
            'pages' => $pages,
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

    private function resolveSourceDisplayName(string $sourceName): string {
        $normalized = strtolower(trim($sourceName));
        return match ($normalized) {
            'goobike' => 'グーバイク',
            'bds', 'bikesensor' => 'BDSバイクセンサー',
            'webike' => 'Webike',
            default => $sourceName ?: '不明',
        };
    }

    private function resolveSourceDomain(string $sourceName): string {
        $normalized = strtolower(trim($sourceName));
        $domains = ['goobike' => 'goobike.com', 'bds' => 'bds-bikesensor.net', 'bikesensor' => 'bds-bikesensor.net', 'webike' => 'www.webike.net'];
        return $domains[$normalized] ?? 'google.com';
    }

    private function resolveImageUrls(?array $paths): array {
        if (empty($paths)) return [];
        return array_map(fn($p) => Storage::disk('public')->url(ltrim($p, '/')), $paths);
    }

    private function getPrefectures(): array {
        return ['北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県', '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県', '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県', '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県', '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'];
    }

    public function getActiveCount(): int {
        return $this->listingRepo->countActiveListings();
    }

    public function getModelsByManufacturer(int $manufacturerId): Collection {
        return $this->modelRepo->getByManufacturerId($manufacturerId);
    }
}