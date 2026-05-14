<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Listing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class RankingService
{
    /**
     * 全車種の月間販売台数ランキング（1日キャッシュ）
     * @return array<int, int> [bike_model_id => rank(1-indexed)]
     */
    public function getAllRankPositions(): array
    {
        return Cache::remember('ranking_positions_all_v2', 86400, function () {
            $lms = Carbon::now()->subMonth()->startOfMonth();
            $lme = Carbon::now()->subMonth()->endOfMonth();

            $rows = Listing::where('is_sold_out', true)
                ->whereRaw('created_at <= updated_at - INTERVAL 3 DAY')
                ->whereBetween('updated_at', [$lms, $lme])
                ->whereNotNull('bike_model_id')
                ->select('bike_model_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('bike_model_id')
                ->orderByDesc('cnt')
                ->get();

            $positions = [];
            foreach ($rows as $i => $row) {
                $positions[$row->bike_model_id] = $i + 1;
            }

            return $positions;
        });
    }

    /**
     * カテゴリ内ランキング（1日キャッシュ）
     * @return array<int, int> [bike_model_id => rank(1-indexed)]
     */
    public function getCategoryRankPositions(int $categoryId): array
    {
        return Cache::remember("ranking_positions_cat_v2_{$categoryId}", 86400, function () use ($categoryId) {
            $lms = Carbon::now()->subMonth()->startOfMonth();
            $lme = Carbon::now()->subMonth()->endOfMonth();

            $rows = Listing::where('is_sold_out', true)
                ->whereRaw('created_at <= updated_at - INTERVAL 3 DAY')
                ->whereBetween('updated_at', [$lms, $lme])
                ->whereNotNull('bike_model_id')
                ->whereHas('bikeModel', fn ($q) => $q->where('category_id', $categoryId))
                ->select('bike_model_id', DB::raw('COUNT(*) as cnt'))
                ->groupBy('bike_model_id')
                ->orderByDesc('cnt')
                ->get();

            $positions = [];
            foreach ($rows as $i => $row) {
                $positions[$row->bike_model_id] = $i + 1;
            }

            return $positions;
        });
    }

    /**
     * 車種詳細ページ用のランキングデータを取得
     */
    public function getModelRankingStats(int $bikeModelId, ?int $categoryId = null): ?array
    {
        return Cache::remember("model_ranking_stats_v2_{$bikeModelId}", 3600, function () use ($bikeModelId, $categoryId) {
            $lms = Carbon::now()->subMonth()->startOfMonth();
            $lme = Carbon::now()->subMonth()->endOfMonth();

            $monthlySold = Listing::where('bike_model_id', $bikeModelId)
                ->where('is_sold_out', true)
                ->whereRaw('created_at <= updated_at - INTERVAL 3 DAY')
                ->whereBetween('updated_at', [$lms, $lme])
                ->count();

            if ($monthlySold === 0) {
                return null;
            }

            // 全車種ランキング
            $allPositions = $this->getAllRankPositions();
            $overallRank = $allPositions[$bikeModelId] ?? null;
            $totalModels = count($allPositions);

            // カテゴリ内ランキング
            $categoryRank = null;
            $categoryTotal = null;
            if ($categoryId) {
                $catPositions = $this->getCategoryRankPositions($categoryId);
                $categoryRank = $catPositions[$bikeModelId] ?? null;
                $categoryTotal = count($catPositions);
            }

            // 平均在庫日数
            $avgDays = Listing::where('bike_model_id', $bikeModelId)
                ->where('is_sold_out', true)
                ->whereRaw('created_at <= updated_at - INTERVAL 3 DAY')
                ->whereBetween('updated_at', [$lms, $lme])
                ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_days')
                ->value('avg_days');

            // 上位20%判定
            $isPopular = $overallRank && $totalModels > 0 && $overallRank <= ceil($totalModels * 0.2);

            return [
                'monthlySold'   => $monthlySold,
                'overallRank'   => $overallRank,
                'totalModels'   => $totalModels,
                'categoryRank'  => $categoryRank,
                'categoryTotal' => $categoryTotal,
                'avgDays'       => (int) round((float) ($avgDays ?? 0)),
                'isPopular'     => $isPopular,
            ];
        });
    }
}
