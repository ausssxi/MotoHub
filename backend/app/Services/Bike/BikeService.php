<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Repositories\Bike\BikeModelRepository;
use App\Repositories\Bike\ListingRepository;
use App\Repositories\Bike\ManufacturerRepository;
use App\Repositories\Bike\CategoryRepository;
use App\Models\Listing;
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

    /**
     * ★追加: 詳細ページ用のSEOリンク集を生成
     */
    public function getSeoLinks(Listing $listing): array
    {
        $links = [];
        
        // ★修正: リレーション経由でデータを正しく取得
        // Listingモデルには直接 prefecture や maker カラムはないため、関連モデルから取得します
        $pref = $listing->shop->prefecture ?? null;
        $maker = $listing->bikeModel->manufacturer->name ?? null;
        
        // カテゴリ名はリレーション名 'categoryData' を使用
        $catName = $listing->bikeModel->categoryData->name ?? null;
        
        // カテゴリIDの取得
        $catId = $listing->bikeModel?->category_id;
        
        // メーカーID
        $makerId = $listing->manufacturer_id ?? $listing->bikeModel?->manufacturer_id;

        // 1. エリア × メーカー
        if ($pref && $maker && $makerId) {
            $links[] = [
                'label' => "{$pref}の{$maker}在庫一覧",
                'url' => route('bikes.landing', ['prefecture' => $pref, 'slug' => $maker]),
            ];
        }

        // 2. エリア × カテゴリ
        if ($pref && $catName && $catName !== 'その他') {
            $links[] = [
                'label' => "{$pref}の{$catName}一覧",
                'url' => route('bikes.landing', ['prefecture' => $pref, 'slug' => $catName]),
            ];
        }

        // 3. メーカー × カテゴリ (全国)
        if ($maker && $catName && $makerId && $catId) {
            $links[] = [
                'label' => "{$maker}の{$catName} (全国)",
                'url' => route('bikes.search', ['manufacturer_id' => $makerId, 'category_id' => $catId]),
            ];
        }

        // 4. エリアの全車両
        if ($pref) {
            $links[] = [
                'label' => "{$pref}のバイク一覧",
                'url' => route('bikes.search', ['prefecture' => $pref]),
            ];
        }

        // 5. メーカーの全車両
        if ($maker && $makerId) {
            $links[] = [
                'label' => "{$maker}の中古・新車一覧",
                'url' => route('bikes.search', ['manufacturer_id' => $makerId]),
            ];
        }

        return $links;
    }

    /**
     * 車種の相場分析データを取得（統計情報 + ヒストグラム）
     */
    public function getMarketAnalysis(?int $bikeModelId, ?int $currentPrice): array
    {
        // デフォルト値
        $defaultStats = [
            'avg' => 0, 'min' => 0, 'max' => 0, 'count' => 0,
            'rank' => 'unknown', 'diff' => 0
        ];
        $defaultHistogram = ['labels' => [], 'data' => []];

        if (!$bikeModelId) {
            return ['stats' => $defaultStats, 'histogram' => $defaultHistogram];
        }

        // 1. 価格データの取得
        $prices = $this->listingRepo->findValidPricesByModelId($bikeModelId);

        $count = count($prices);
        if ($count === 0) {
            return ['stats' => $defaultStats, 'histogram' => $defaultHistogram];
        }

        // 2. 統計計算
        $avg = round(array_sum($prices) / $count, 1);
        $min = min($prices);
        $max = max($prices);
        $currentPrice = $currentPrice ?? 0;
        $diff = round($currentPrice - $avg, 1);

        // ランク判定
        $rank = 'unknown';
        if ($currentPrice > 0) {
            if ($currentPrice < $avg * 0.9) {
                $rank = 'S';
            } elseif ($currentPrice < $avg) {
                $rank = 'A';
            } elseif ($currentPrice < $avg * 1.1) {
                $rank = 'B';
            } else {
                $rank = 'C';
            }
        }

        $stats = [
            'avg' => $avg,
            'min' => $min,
            'max' => $max,
            'count' => $count,
            'rank' => $rank,
            'diff' => $diff,
        ];

        // 3. ヒストグラム生成
        $histogram = $this->generateHistogram($prices);

        return [
            'stats' => $stats,
            'histogram' => $histogram
        ];
    }

    /**
     * ヒストグラムデータの生成
     */
    private function generateHistogram(array $prices): array
    {
        if (empty($prices)) return ['labels' => [], 'data' => []];

        $min = floor(min($prices) / 10) * 10;
        $max = ceil(max($prices) / 10) * 10;
        $step = 10;

        // 幅調整
        if ($max - $min < $step * 3) {
            $min = max(0, $min - $step);
            $max = $max + $step;
        }

        $labels = [];
        $data = [];

        for ($i = $min; $i <= $max; $i += $step) {
            $labels[] = $i . '万円台';
            $count = 0;
            foreach ($prices as $p) {
                if ($p >= $i && $p < $i + $step) $count++;
            }
            $data[] = $count;
        }

        return ['labels' => $labels, 'data' => $data];
    }
}