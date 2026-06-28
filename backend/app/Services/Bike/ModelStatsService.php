<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\Listing;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 車種別「市場データ（売れている地域 / 走行距離帯 / 年式 / 価格帯 / 販売推移 等）」の共有集計。
 *
 * もとは RankingController::getModelStats() に private で実装されていたものを、
 * 車種分析ページ(/ranking/model/{id}) とデータAPI(/api/v1/models/{id}/stats) の両方から
 * 同一ロジック・同一キャッシュで使えるよう抽出した（＝サイトとAPIの数字が必ず一致する）。
 *
 * スコープは Listing::cappedSold(3ヶ月前, now)->excludeBulkSold() ＋ bike_model_id 指定。
 * 戻り値の構造・キーは抽出前と完全に同一（既存ページを壊さないこと）。
 */
final class ModelStatsService
{
    /** ページと共有するキャッシュキー（1週間）。値・キーとも抽出前から不変。 */
    private const CACHE_KEY = 'model_stats_ranking_v6_';

    private const CACHE_TTL = 604800; // 1週間

    /** 集計対象期間（直近◯ヶ月の成約データ）。 */
    public const MONTHS = 3;

    /**
     * 車種別の市場データを返す（既存ページと同じキャッシュに相乗り）。
     *
     * @return array<string, mixed>
     */
    public function getStats(int $bikeModelId): array
    {
        return Cache::remember(
            self::CACHE_KEY.$bikeModelId,
            self::CACHE_TTL,
            fn () => $this->compute($bikeModelId),
        );
    }

    /**
     * 実集計（抽出前の RankingController::getModelStats と同一ロジック）。
     *
     * @return array<string, mixed>
     */
    private function compute(int $bikeModelId): array
    {
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();
        $threeMonthsAgo = Carbon::now()->subMonths(self::MONTHS);

        $lastMonthSold = Listing::cappedSold($lastMonthStart, $lastMonthEnd)->excludeBulkSold()
            ->where('bike_model_id', $bikeModelId)
            ->count();

        // 全車種中の順位
        $allModelSales = Listing::cappedSold($lastMonthStart, $lastMonthEnd)->excludeBulkSold()
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('bike_model_id')
            ->orderByDesc('cnt')
            ->get();

        $rank = $allModelSales->search(fn ($item) => $item->bike_model_id == $bikeModelId);
        $rank = $rank !== false ? $rank + 1 : null;

        $avgDays = Listing::cappedSold($lastMonthStart, $lastMonthEnd)->excludeBulkSold()
            ->where('bike_model_id', $bikeModelId)
            ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_days')
            ->value('avg_days');

        // 価格帯（過去3ヶ月）
        $now = Carbon::now();
        $priceRanges = Listing::cappedSold($threeMonthsAgo, $now)->excludeBulkSold()
            ->where('bike_model_id', $bikeModelId)
            ->whereNotNull('total_price')
            ->select(DB::raw("
                CASE
                    WHEN total_price < 200000 THEN '〜20万円'
                    WHEN total_price < 300000 THEN '20〜30万円'
                    WHEN total_price < 400000 THEN '30〜40万円'
                    WHEN total_price < 500000 THEN '40〜50万円'
                    WHEN total_price < 700000 THEN '50〜70万円'
                    WHEN total_price < 1000000 THEN '70〜100万円'
                    ELSE '100万円〜'
                END as price_range
            "), DB::raw('COUNT(*) as cnt'))
            ->groupBy('price_range')
            ->orderByDesc('cnt')
            ->get();

        // 地域TOP10
        $regionRanking = Listing::cappedSold($threeMonthsAgo, $now)->excludeBulkSold()
            ->where('listings.bike_model_id', $bikeModelId)
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->whereNotNull('shops.prefecture')
            ->select('shops.prefecture', DB::raw('COUNT(*) as cnt'))
            ->groupBy('shops.prefecture')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        // 走行距離帯
        $mileageRanges = Listing::cappedSold($threeMonthsAgo, $now)->excludeBulkSold()
            ->where('bike_model_id', $bikeModelId)
            ->whereNotNull('mileage')
            ->select(DB::raw("
                CASE
                    WHEN mileage < 5000 THEN '〜5,000km'
                    WHEN mileage < 10000 THEN '5,000〜10,000km'
                    WHEN mileage < 20000 THEN '10,000〜20,000km'
                    WHEN mileage < 30000 THEN '20,000〜30,000km'
                    ELSE '30,000km〜'
                END as mileage_range
            "), DB::raw('COUNT(*) as cnt'))
            ->groupBy('mileage_range')
            ->orderByDesc('cnt')
            ->get();

        // 年式（新しい順）
        $yearRanking = Listing::cappedSold($threeMonthsAgo, $now)->excludeBulkSold()
            ->where('bike_model_id', $bikeModelId)
            ->whereNotNull('model_year')
            ->select('model_year', DB::raw('COUNT(*) as cnt'))
            ->groupBy('model_year')
            ->orderByDesc('model_year')
            ->limit(10)
            ->get();

        // 販売推移（過去6ヶ月）。6ヶ月ぶんを1クエリの GROUP BY(年月) に集約し、
        // cappedSold のウィンドウ実行を 6回→1回に削減（最適化C）。
        // ※ cappedSold の per-day cap は PARTITION に DATE(updated_at) を含むため、
        //   月別6ループでも6ヶ月一括でも各月の結果は同一（日は必ず単一の月に属する）。
        $rangeStart = Carbon::now()->subMonths(5)->startOfMonth();
        $rangeEnd = Carbon::now()->endOfMonth();
        $monthlyAgg = Listing::cappedSold($rangeStart, $rangeEnd)->excludeBulkSold()
            ->where('bike_model_id', $bikeModelId)
            ->selectRaw('YEAR(listings.updated_at) as y, MONTH(listings.updated_at) as m, COUNT(*) as cnt')
            ->groupBy('y', 'm')
            ->get()
            ->keyBy(fn ($r) => $r->y.'-'.$r->m);

        $monthlySales = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i)->startOfMonth();
            $monthlySales[] = [
                'month' => $month->format('Y年n月'),
                'label' => $month->format('n月'),
                'count' => (int) ($monthlyAgg->get($month->year.'-'.$month->month)->cnt ?? 0),
            ];
        }

        return [
            'lastMonthSold' => $lastMonthSold,
            'rank' => $rank,
            'totalModels' => $allModelSales->count(),
            'avgDays' => (int) round((float) ($avgDays ?? 0)),
            'dailyAvg' => round($lastMonthSold / 30, 1),
            'priceRanges' => $priceRanges,
            'regionRanking' => $regionRanking,
            'mileageRanges' => $mileageRanges,
            'yearRanking' => $yearRanking,
            'monthlySales' => $monthlySales,
        ];
    }
}
