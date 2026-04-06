<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\Manufacturer;
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
     * 日別ランキング（カレンダー付き）
     */
    public function daily(Request $request, ?string $date = null): View
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::today();

        // 未来の日付は今日にリダイレクト
        if ($targetDate->isFuture()) {
            $targetDate = Carbon::today();
        }

        $ranking = Cache::remember(
            "ranking_daily_{$targetDate->toDateString()}",
            $targetDate->isToday() ? 3600 : 86400,
            fn () => $this->getDailyRanking($targetDate),
        );

        $calendarData = $this->getDailySummary(
            $targetDate->year,
            $targetDate->month,
        );

        return view('ranking.daily', [
            'ranking' => $ranking,
            'calendarData' => $calendarData,
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
            "ranking_weekly_{$endDate->toDateString()}",
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
     * 日別ランキングデータ取得
     */
    private function getDailyRanking(Carbon $date): array
    {
        $totalSold = Listing::where('is_sold_out', true)
            ->whereDate('updated_at', $date)
            ->count();

        // 車種別ランキング TOP20
        $modelRows = Listing::where('is_sold_out', true)
            ->whereDate('updated_at', $date)
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('bike_model_id')
            ->orderByDesc('sold_count')
            ->limit(20)
            ->get();

        $modelIds = $modelRows->pluck('bike_model_id')->toArray();
        $models = BikeModel::with('manufacturer')->whereIn('id', $modelIds)->get()->keyBy('id');

        $modelRanking = $modelRows->map(function ($item) use ($models) {
            $model = $models->get($item->bike_model_id);
            return [
                'bike_model_id' => $item->bike_model_id,
                'name' => $model->name ?? '不明',
                'manufacturer' => $model->manufacturer->name ?? '不明',
                'image_url' => $model?->image_url,
                'seo_url' => $model?->seo_url,
                'sold_count' => $item->sold_count,
            ];
        });

        // メーカー別ランキング
        $makerRows = Listing::where('is_sold_out', true)
            ->whereDate('updated_at', $date)
            ->whereNotNull('manufacturer_id')
            ->select('manufacturer_id', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('manufacturer_id')
            ->orderByDesc('sold_count')
            ->limit(10)
            ->get();

        $mfrIds = $makerRows->pluck('manufacturer_id')->toArray();
        $mfrs = Manufacturer::whereIn('id', $mfrIds)->get()->keyBy('id');

        $makerRanking = $makerRows->map(function ($item) use ($mfrs) {
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

        // 価格帯別
        $priceRanges = Listing::where('is_sold_out', true)
            ->whereDate('updated_at', $date)
            ->whereNotNull('total_price')
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

        // 排気量帯別
        $displacementRanges = Listing::where('is_sold_out', true)
            ->whereDate('updated_at', $date)
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
            'priceRanges' => $priceRanges,
            'displacementRanges' => $displacementRanges,
            'date' => $date->toDateString(),
        ];
    }

    /**
     * 期間指定ランキング（週間・月間共通）
     */
    private function getRanking(Carbon $start, Carbon $end): array
    {
        $from = $start->copy()->startOfDay();
        $to = $end->copy()->endOfDay();

        $totalSold = Listing::where('is_sold_out', true)
            ->whereBetween('updated_at', [$from, $to])
            ->count();

        $modelRows = Listing::where('is_sold_out', true)
            ->whereBetween('updated_at', [$from, $to])
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('bike_model_id')
            ->orderByDesc('sold_count')
            ->limit(30)
            ->get();

        $modelIds = $modelRows->pluck('bike_model_id')->toArray();
        $models = BikeModel::with('manufacturer')->whereIn('id', $modelIds)->get()->keyBy('id');

        $modelRanking = $modelRows->map(function ($item) use ($models) {
            $model = $models->get($item->bike_model_id);
            return [
                'bike_model_id' => $item->bike_model_id,
                'name' => $model->name ?? '不明',
                'manufacturer' => $model->manufacturer->name ?? '不明',
                'image_url' => $model?->image_url,
                'seo_url' => $model?->seo_url,
                'sold_count' => $item->sold_count,
            ];
        });

        $makerRows = Listing::where('is_sold_out', true)
            ->whereBetween('updated_at', [$from, $to])
            ->whereNotNull('manufacturer_id')
            ->select('manufacturer_id', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('manufacturer_id')
            ->orderByDesc('sold_count')
            ->limit(10)
            ->get();

        $mfrIds = $makerRows->pluck('manufacturer_id')->toArray();
        $mfrs = Manufacturer::whereIn('id', $mfrIds)->get()->keyBy('id');

        $makerRanking = $makerRows->map(function ($item) use ($mfrs) {
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

        return [
            'totalSold' => $totalSold,
            'modelRanking' => $modelRanking,
            'makerRanking' => $makerRanking,
            'startDate' => $start->toDateString(),
            'endDate' => $end->toDateString(),
        ];
    }

    /**
     * 月間ランキング
     */
    private function getMonthlyRanking(int $year, int $month): array
    {
        $start = Carbon::create($year, $month, 1);
        $end = $start->copy()->endOfMonth();

        return Cache::remember(
            "ranking_monthly_{$year}_{$month}",
            Carbon::now()->month === $month && Carbon::now()->year === $year ? 3600 : 86400,
            fn () => $this->getRanking($start, $end),
        );
    }

    /**
     * カレンダー用: 月の日別販売台数
     */
    private function getDailySummary(int $year, int $month): array
    {
        return Cache::remember(
            "ranking_daily_summary_{$year}_{$month}",
            Carbon::now()->month === $month && Carbon::now()->year === $year ? 3600 : 86400,
            function () use ($year, $month) {
                $start = Carbon::create($year, $month, 1)->startOfDay();
                $end = $start->copy()->endOfMonth()->endOfDay();

                return Listing::where('is_sold_out', true)
                    ->whereBetween('updated_at', [$start, $end])
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
}
