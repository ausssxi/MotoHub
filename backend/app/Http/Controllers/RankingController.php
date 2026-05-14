<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\Manufacturer;
use App\Models\Shop;
use App\Services\Bike\BikePartsService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
final class RankingController extends Controller
{
    /**
     * メインページ（今月のランキング）
     */
    public function index(): View
    {
        $now = Carbon::now();
        $monthlyRanking = $this->getMonthlyRanking($now->year, $now->month);
        $dailySummary = $this->getDailySummary($now->year, $now->month);

        return view('ranking.index', [
            'ranking' => $monthlyRanking,
            'dailySummary' => $dailySummary,
            'year' => $now->year,
            'month' => $now->month,
        ]);
    }

    /**
     * 日別ランキング（直近7日間ナビ付き）
     */
    public function daily(Request $request, ?string $date = null): View
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::yesterday();

        if ($targetDate->isFuture()) {
            $targetDate = Carbon::yesterday();
        }

        $ranking = Cache::remember(
            "ranking_daily_v2_{$targetDate->toDateString()}",
            $targetDate->isToday() ? 3600 : 86400,
            fn () => $this->getDailyRanking($targetDate),
        );

        $weekStart = $targetDate->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);
        if ($weekEnd->isFuture()) {
            $weekEnd = Carbon::today();
        }
        $weekDays = $this->getWeekSummary($weekStart, $weekEnd);

        return view('ranking.daily', [
            'ranking' => $ranking,
            'weekDays' => $weekDays,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'targetDate' => $targetDate,
        ]);
    }

    /**
     * 週間ランキング
     */
    public function weekly(): View
    {
        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays(7);

        $ranking = Cache::remember(
            "ranking_weekly_v2_{$endDate->toDateString()}",
            3600,
            fn () => $this->getRanking($startDate->copy(), $endDate->copy()),
        );

        return view('ranking.weekly', [
            'ranking' => $ranking,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ]);
    }

    /**
     * 月別ランキング
     */
    public function monthly(?string $month = null): View
    {
        $target = $month ? Carbon::parse($month . '-01') : Carbon::now();
        $ranking = $this->getMonthlyRanking($target->year, $target->month);

        return view('ranking.monthly', [
            'ranking' => $ranking,
            'year' => $target->year,
            'month' => $target->month,
        ]);
    }

    /**
     * 車種別データ分析
     */
    public function modelStats(int $bikeModelId): View
    {
        $bikeModel = BikeModel::with('manufacturer')->findOrFail($bikeModelId);

        $stats = Cache::remember(
            "model_stats_ranking_v2_{$bikeModelId}",
            3600,
            fn () => $this->getModelStats($bikeModelId),
        );

        $activeCount = Listing::where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', false)
            ->count();

        $relatedListings = Listing::with('shop:id,prefecture')
            ->where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', false)
            ->latest()
            ->limit(4)
            ->get();

        $relatedParts = app(BikePartsService::class)->fetchFlat($bikeModel);

        return view('ranking.model', [
            'bikeModel' => $bikeModel,
            'stats' => $stats,
            'activeCount' => $activeCount,
            'relatedListings' => $relatedListings,
            'relatedParts' => $relatedParts,
        ]);
    }

    // ─── Private helpers ────────────────────────────────

    private function getDailyRanking(Carbon $date): array
    {
        $baseQuery = fn () => Listing::cappedSold($date, $date);

        $totalSold = $baseQuery()->count();

        $modelRanking = $this->buildModelRanking($baseQuery(), 20);

        $makerRanking = $this->buildMakerRanking($baseQuery());

        $shopRanking = $this->buildShopRanking($baseQuery());

        $priceRanges = $this->buildPriceRanges($baseQuery());

        $displacementRanges = $baseQuery()
            ->whereNotNull('displacement')
            ->select(DB::raw("
                CASE
                    WHEN displacement <= 50 THEN '〜50cc'
                    WHEN displacement <= 125 THEN '51〜125cc'
                    WHEN displacement <= 250 THEN '126〜250cc'
                    WHEN displacement <= 400 THEN '251〜400cc'
                    ELSE '401cc〜'
                END as cc_range
            "), DB::raw('COUNT(*) as cnt'))
            ->groupBy('cc_range')
            ->orderByDesc('cnt')
            ->get();

        return [
            'totalSold' => $totalSold,
            'modelRanking' => $modelRanking,
            'makerRanking' => $makerRanking,
            'shopRanking' => $shopRanking,
            'priceRanges' => $priceRanges,
            'displacementRanges' => $displacementRanges,
            'date' => $date->toDateString(),
        ];
    }

    private function getRanking(Carbon $start, Carbon $end): array
    {
        $baseQuery = fn () => Listing::cappedSold($start, $end);

        $totalSold = $baseQuery()->count();
        $modelRanking = $this->buildModelRanking($baseQuery(), 30);
        $makerRanking = $this->buildMakerRanking($baseQuery());
        $shopRanking = $this->buildShopRanking($baseQuery());
        $priceRanges = $this->buildPriceRanges($baseQuery());

        return [
            'totalSold' => $totalSold,
            'modelRanking' => $modelRanking,
            'makerRanking' => $makerRanking,
            'shopRanking' => $shopRanking,
            'priceRanges' => $priceRanges,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
        ];
    }

    private function buildModelRanking($query, int $limit = 30)
    {
        $rows = (clone $query)->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('bike_model_id')
            ->orderByDesc('sold_count')
            ->limit($limit)
            ->get();

        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $rows->pluck('bike_model_id'))
            ->get()->keyBy('id');

        return $rows->map(function ($item) use ($models) {
            $m = $models->get($item->bike_model_id);
            return [
                'bike_model_id' => $item->bike_model_id,
                'name' => $m->name ?? '不明',
                'manufacturer' => $m->manufacturer->name ?? '不明',
                'image_url' => $m?->image_url,
                'seo_url' => $m?->seo_url,
                'sold_count' => $item->sold_count,
            ];
        });
    }

    private function buildMakerRanking($query)
    {
        $rows = (clone $query)->whereNotNull('manufacturer_id')
            ->select('manufacturer_id', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('manufacturer_id')
            ->orderByDesc('sold_count')
            ->limit(10)
            ->get();

        $mfrs = Manufacturer::whereIn('id', $rows->pluck('manufacturer_id'))
            ->get()->keyBy('id');

        return $rows->map(function ($item) use ($mfrs) {
            $mfr = $mfrs->get($item->manufacturer_id);
            return [
                'manufacturer_id' => $item->manufacturer_id,
                'name' => $mfr->name ?? '不明',
                'logo_url' => $mfr?->local_logo_path
                    ? asset('storage/' . ltrim($mfr->local_logo_path, '/'))
                    : $mfr?->logo_url,
                'sold_count' => $item->sold_count,
            ];
        });
    }

    private function buildShopRanking($query, int $limit = 20)
    {
        $rows = (clone $query)->whereNotNull('shop_id')
            ->select('shop_id', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('shop_id')
            ->orderByDesc('sold_count')
            ->limit($limit)
            ->get();

        $shops = Shop::whereIn('id', $rows->pluck('shop_id'))->get()->keyBy('id');

        return $rows->map(function ($item) use ($shops) {
            $shop = $shops->get($item->shop_id);
            return [
                'shop_id' => $item->shop_id,
                'name' => $shop->name ?? '不明',
                'prefecture' => $shop->prefecture ?? '',
                'sold_count' => $item->sold_count,
            ];
        })->toArray();
    }

    private function buildPriceRanges($query)
    {
        return (clone $query)->whereNotNull('total_price')
            ->select(DB::raw("
                CASE
                    WHEN total_price < 300000 THEN '〜30万円'
                    WHEN total_price < 600000 THEN '30〜60万円'
                    WHEN total_price < 1000000 THEN '60〜100万円'
                    WHEN total_price < 1500000 THEN '100〜150万円'
                    ELSE '150万円〜'
                END as price_range
            "), DB::raw('COUNT(*) as cnt'))
            ->groupBy('price_range')
            ->orderByDesc('cnt')
            ->get();
    }

    private function getMonthlyRanking(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();

        return Cache::remember(
            "ranking_monthly_v3_{$year}_{$month}",
            Carbon::now()->month === $month && Carbon::now()->year === $year ? 3600 : 86400,
            fn () => $this->getRanking($start, $end),
        );
    }

    private function getDailySummary(int $year, int $month): array
    {
        return Cache::remember(
            "ranking_daily_summary_v2_{$year}_{$month}",
            Carbon::now()->month === $month && Carbon::now()->year === $year ? 3600 : 86400,
            function () use ($year, $month) {
                $start = Carbon::create($year, $month, 1)->startOfDay();
                $end = $start->copy()->endOfMonth()->endOfDay();
                // 当日は集計未確定のため除外（昨日までのデータのみ）
                $yesterday = Carbon::yesterday()->endOfDay();
                if ($end->gt($yesterday)) {
                    $end = $yesterday;
                }

                return Listing::cappedSold($start, $end)
                    ->select(
                        DB::raw('DATE(updated_at) as sold_date'),
                        DB::raw('COUNT(*) as cnt'),
                    )
                    ->groupBy(DB::raw('DATE(updated_at)'))
                    ->orderBy('sold_date')
                    ->pluck('cnt', 'sold_date')
                    ->toArray();
            },
        );
    }

    private function getWeekSummary(Carbon $start, Carbon $end): array
    {
        $cacheKey = "ranking_week_v2_{$start->toDateString()}_{$end->toDateString()}";
        $ttl = $end->gte(Carbon::today()) ? 3600 : 86400;

        $dayCounts = Cache::remember($cacheKey, $ttl, function () use ($start, $end) {
            return Listing::cappedSold($start, $end)
                ->select(DB::raw('DATE(updated_at) as sold_date'), DB::raw('COUNT(*) as cnt'))
                ->groupBy(DB::raw('DATE(updated_at)'))
                ->pluck('cnt', 'sold_date')
                ->toArray();
        });

        $days = [];
        $current = $start->copy();
        while ($current->lte($end)) {
            $dateStr = $current->toDateString();
            $days[] = [
                'date' => $dateStr,
                'label' => $current->format('n/j'),
                'dow' => ['日','月','火','水','木','金','土'][$current->dayOfWeek],
                'count' => $dayCounts[$dateStr] ?? 0,
                'isFuture' => $current->isFuture(),
                'isToday' => $current->isToday(),
            ];
            $current->addDay();
        }

        return $days;
    }

    private function getModelStats(int $bikeModelId): array
    {
        $lastMonthStart = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();
        $threeMonthsAgo = Carbon::now()->subMonths(3);

        $lastMonthSold = Listing::cappedSold($lastMonthStart, $lastMonthEnd)
            ->where('bike_model_id', $bikeModelId)
            ->count();

        // 全車種中の順位
        $allModelSales = Listing::cappedSold($lastMonthStart, $lastMonthEnd)
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('bike_model_id')
            ->orderByDesc('cnt')
            ->get();

        $rank = $allModelSales->search(fn ($item) => $item->bike_model_id == $bikeModelId);
        $rank = $rank !== false ? $rank + 1 : null;

        $avgDays = Listing::cappedSold($lastMonthStart, $lastMonthEnd)
            ->where('bike_model_id', $bikeModelId)
            ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_days')
            ->value('avg_days');

        // 価格帯（過去3ヶ月）
        $priceRanges = Listing::cappedSold($threeMonthsAgo, Carbon::now())
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
        $regionRanking = Listing::cappedSold($threeMonthsAgo, Carbon::now())
            ->where('listings.bike_model_id', $bikeModelId)
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->whereNotNull('shops.prefecture')
            ->select('shops.prefecture', DB::raw('COUNT(*) as cnt'))
            ->groupBy('shops.prefecture')
            ->orderByDesc('cnt')
            ->limit(10)
            ->get();

        // 走行距離帯
        $mileageRanges = Listing::cappedSold($threeMonthsAgo, Carbon::now())
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
        $yearRanking = Listing::cappedSold($threeMonthsAgo, Carbon::now())
            ->where('bike_model_id', $bikeModelId)
            ->whereNotNull('model_year')
            ->select('model_year', DB::raw('COUNT(*) as cnt'))
            ->groupBy('model_year')
            ->orderByDesc('model_year')
            ->limit(10)
            ->get();

        // 販売推移（過去6ヶ月）
        $monthlySales = [];
        for ($i = 5; $i >= 0; $i--) {
            $ms = Carbon::now()->subMonths($i)->startOfMonth();
            $me = Carbon::now()->subMonths($i)->endOfMonth();
            $monthlySales[] = [
                'month' => $ms->format('Y年n月'),
                'label' => $ms->format('n月'),
                'count' => Listing::cappedSold($ms, $me)
                    ->where('bike_model_id', $bikeModelId)
                    ->count(),
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
