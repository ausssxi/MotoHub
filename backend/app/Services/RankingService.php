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
        return Cache::remember('ranking_positions_all_v5', 604800, function () {
            $lms = Carbon::now()->subMonth()->startOfMonth();
            $lme = Carbon::now()->subMonth()->endOfMonth();

            $rows = Listing::cappedSold($lms, $lme)
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
        return Cache::remember("ranking_positions_cat_v5_{$categoryId}", 604800, function () use ($categoryId) {
            $lms = Carbon::now()->subMonth()->startOfMonth();
            $lme = Carbon::now()->subMonth()->endOfMonth();

            $rows = Listing::cappedSold($lms, $lme)
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
        return Cache::remember("model_ranking_stats_v5_{$bikeModelId}", 604800, function () use ($bikeModelId, $categoryId) {
            $lms = Carbon::now()->subMonth()->startOfMonth();
            $lme = Carbon::now()->subMonth()->endOfMonth();

            $monthlySold = Listing::cappedSold($lms, $lme)
                ->where('bike_model_id', $bikeModelId)
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
            $avgDays = Listing::cappedSold($lms, $lme)
                ->where('bike_model_id', $bikeModelId)
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
