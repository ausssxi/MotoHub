<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ListingRepository;
use App\Models\BikeModel;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * バイク出品情報の検索・絞り込みロジックを担当し、UI向けのデータ整形を行うサービス
 */
final class ListingSearchService
{
    /**
     * @param ListingRepository $repository 出品情報のデータアクセスを担当するリポジトリ
     */
    public function __construct(
        private readonly ListingRepository $repository
    ) {}

    /**
     * フィルター条件を含めて検索を実行し、結果を整形して返す
     */
    public function search(?string $keyword, ?string $prefecture = null, string $sort = 'latest', array $filters = [], int $perPage = 30): array
    {
        // 1. 車種IDが指定されている場合、メーカーIDを逆算して補完（サイドバーの連動維持のため）
        if (!empty($filters['bike_model_id']) && empty($filters['manufacturer_id'])) {
            $model = BikeModel::find($filters['bike_model_id']);
            if ($model) {
                $filters['manufacturer_id'] = $model->manufacturer_id;
            }
        }

        // 2. ✨ アップグレード：推論条件の緩和と強化
        // 「メーカー選択済み」の状態でも、車種IDが未選択であればキーワードから特定の1車種を絞り込みます
        if (!empty($keyword) && empty($filters['bike_model_id'])) {
            $inference = $this->inferFromKeyword($keyword);
            
            // メーカーが未選択の場合のみ、推論結果からメーカーを補完
            if (empty($filters['manufacturer_id']) && $inference['manufacturer_id']) {
                $filters['manufacturer_id'] = $inference['manufacturer_id'];
            }
            
            // 車種IDを推論結果で補完
            if ($inference['bike_model_id']) {
                $filters['bike_model_id'] = $inference['bike_model_id'];
            }
        }

        // 3. リポジトリで検索と統計を実行
        $paginated = $this->repository->searchByKeyword($keyword, $prefecture, $sort, $filters, $perPage);
        $statsRaw = $this->repository->getPriceStats($keyword, $prefecture, $filters);

        // 4. ✨ サイドバー用の車種リスト準備
        // キーワード検索からメーカーが特定された際、そのメーカーの車種一覧をViewに渡すことで
        // JavaScriptによる再取得（および選択状態の消失）を防ぎます。
        $models = collect();
        if (!empty($filters['manufacturer_id'])) {
            $models = $this->repository->getModelsByManufacturer((int)$filters['manufacturer_id']);
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
            'manufacturers' => $this->repository->getAllManufacturers(),
            'models'        => $models,
            'prefectures'   => $this->getPrefectures(),
            'filters'       => $filters, // 推論・補完後のフィルタを返す
        ];
    }

    /**
     * フィルター条件に基づいた総ヒット件数を取得する (スマホ版リアルタイム件数表示用)
     */
    public function getFilteredCount(?string $keyword, ?string $prefecture, array $filters): int
    {
        // 検索時と同じ補完ロジックを通す（件数の整合性を保つため）
        if (!empty($filters['bike_model_id']) && empty($filters['manufacturer_id'])) {
            $model = BikeModel::find($filters['bike_model_id']);
            if ($model) $filters['manufacturer_id'] = $model->manufacturer_id;
        }

        if (!empty($keyword) && empty($filters['bike_model_id'])) {
            $inference = $this->inferFromKeyword($keyword);
            if (empty($filters['manufacturer_id']) && $inference['manufacturer_id']) $filters['manufacturer_id'] = $inference['manufacturer_id'];
            if ($inference['bike_model_id']) $filters['bike_model_id'] = $inference['bike_model_id'];
        }

        $paginated = $this->repository->searchByKeyword($keyword, $prefecture, 'latest', $filters, 1);
        return (int) $paginated->total();
    }

    /**
     * キーワードからメーカーと車種を特定するロジック (インテリジェント・マッピング)
     */
    private function inferFromKeyword(string $keyword): array
    {
        $res = ['manufacturer_id' => null, 'bike_model_id' => null];
        if (mb_strlen($keyword) < 2) return $res;

        $normalizedKeyword = $this->normalizeString($keyword);

        // キーワードに関連する車種を検索（半角・全角変換を考慮）
        $matchedModels = BikeModel::where('name', 'like', "%{$keyword}%")
            ->orWhere('name', 'like', "%" . mb_convert_kana($keyword, "KVa") . "%")
            ->limit(30)
            ->get();

        if ($matchedModels->isEmpty()) return $res;

        // 1. メーカーの特定 (ヒットしたものがすべて同一メーカーなら特定)
        $mIds = $matchedModels->pluck('manufacturer_id')->unique();
        if ($mIds->count() === 1) {
            $res['manufacturer_id'] = (int)$mIds->first();
        }

        // 2. 車種の特定 (精密マッチング)
        // A. 正規化後の完全一致を探す
        $exactMatch = $matchedModels->first(function ($m) use ($normalizedKeyword) {
            return $this->normalizeString($m->name) === $normalizedKeyword;
        });

        if ($exactMatch) {
            $res['bike_model_id'] = (int)$exactMatch->id;
            $res['manufacturer_id'] = (int)$exactMatch->manufacturer_id;
        } 
        // B. 候補が1件しかない場合はそれを採用
        elseif ($matchedModels->count() === 1) {
            $res['bike_model_id'] = (int)$matchedModels->first()->id;
        }
        // C. ヒットが複数あるが、キーワードが名称の先頭にある最も短い名前を採用（例：スーパーカブ50）
        else {
            $bestMatch = $matchedModels->filter(fn($m) => str_starts_with($this->normalizeString($m->name), $normalizedKeyword))
                                      ->sortBy(fn($m) => strlen($m->name))
                                      ->first();
            if ($bestMatch) {
                $res['bike_model_id'] = (int)$bestMatch->id;
            }
        }

        return $res;
    }

    /**
     * 照合用の文字列正規化
     */
    private function normalizeString(string $str): string
    {
        $str = mb_convert_kana($str, "asKV");
        $str = str_replace([' ', '　'], '', $str);
        return Str::lower($str);
    }

    /**
     * 特定のメーカーに紐づく車種一覧を取得（API用）
     */
    public function getModelsByManufacturer(int $manufacturerId): Collection
    {
        return $this->repository->getModelsByManufacturer($manufacturerId);
    }

    /**
     * アイテムデータの整形
     */
    private function formatItems(Collection $collection): array
    {
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

    /**
     * ページネーション情報の整形
     */
    private function formatPagination(LengthAwarePaginator $paginated): array
    {
        $currentPage = $paginated->currentPage();
        $lastPage = $paginated->lastPage();
        $range = 1; 
        $pages = [];
        
        $pages[] = $this->makePageItem(1, $paginated);
        if ($currentPage - $range > 2) {
            $pages[] = ['is_dot' => true];
        }
        for ($i = max(2, $currentPage - $range); $i <= min($lastPage - 1, $currentPage + $range); $i++) {
            $pages[] = $this->makePageItem($i, $paginated);
        }
        if ($currentPage + $range < $lastPage - 1) {
            $pages[] = ['is_dot' => true];
        }
        if ($lastPage > 1) {
            $pages[] = $this->makePageItem($lastPage, $paginated);
        }

        return [
            'total'        => $paginated->total(),
            'current_page' => $currentPage,
            'last_page'    => $lastPage,
            'from'         => $paginated->firstItem(),
            'to'           => $paginated->lastItem(),
            'prev_url'     => $paginated->previousPageUrl(),
            'next_url'     => $paginated->nextPageUrl(),
            'pages'        => $pages,
            'display_text' => "全 {$lastPage} ページ中 {$currentPage} ページ"
        ];
    }

    /**
     * 絞り込みスライダーの境界値を取得
     */
    public function getSearchMetadata(?string $keyword = null, ?string $prefecture = null): array
    {
        $stats = $this->repository->getMinMaxStats($keyword, $prefecture);
        $dbMaxPrice = (int) ceil(($stats->max_price ?? 0) / 10000);
        $dbMaxMileage = (int) ceil(($stats->max_mileage ?? 0) / 1000) * 1000;

        return [
            'price' => [
                'min' => 0,
                'max' => max(300, $dbMaxPrice),
            ],
            'mileage' => [
                'min' => 0,
                'max' => max(50000, $dbMaxMileage),
            ],
            'year' => [
                'min' => (int) ($stats->min_year ?? 1990),
                'max' => (int) ($stats->max_year ?? (int) date('Y')),
            ]
        ];
    }

    /**
     * 都道府県リスト
     */
    private function getPrefectures(): array
    {
        return [
            '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
            '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
            '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
            '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
            '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
            '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
            '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'
        ];
    }

    public function getActiveCount(): int
    {
        return $this->repository->countActiveListings();
    }

    private function makePageItem(int $page, LengthAwarePaginator $paginated): array
    {
        return [
            'label'     => $page,
            'url'       => $paginated->url($page),
            'is_active' => $page === $paginated->currentPage(),
            'is_dot'    => false,
        ];
    }

    private function resolveSourceDisplayName(string $sourceName): string
    {
        $normalized = strtolower(trim($sourceName));
        return match ($normalized) {
            'goobike'           => 'グーバイク',
            'bds', 'bikesensor' => 'BDSバイクセンサー',
            'webike'            => 'Webike',
            default             => ($sourceName ?: '不明'),
        };
    }

    private function resolveSourceDomain(string $sourceName): string
    {
        $normalized = strtolower(trim($sourceName));
        $domains = [
            'goobike'    => 'goobike.com',
            'bds'        => 'bds-bikesensor.net',
            'bikesensor' => 'bds-bikesensor.net',
            'webike'     => 'www.webike.net'
        ];
        return $domains[$normalized] ?? 'google.com';
    }

    private function resolveImageUrls(?array $paths): array
    {
        if (empty($paths)) return [];
        return array_map(function ($path) {
            return Storage::disk('public')->url(ltrim($path, '/'));
        }, $paths);
    }
}