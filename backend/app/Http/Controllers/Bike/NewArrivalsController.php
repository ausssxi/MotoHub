<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\Manufacturer;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class NewArrivalsController extends Controller
{
    public function index(?string $date = null): View
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::today();

        if ($targetDate->isFuture()) {
            $targetDate = Carbon::today();
        }

        $data = Cache::remember(
            "new-arrivals:{$targetDate->toDateString()}",
            $targetDate->isToday() ? 3600 : 86400,
            fn () => $this->getNewArrivals($targetDate),
        );

        $weekStart = $targetDate->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);
        if ($weekEnd->isFuture()) {
            $weekEnd = Carbon::today();
        }
        $weekDays = $this->getWeekSummary($weekStart, $weekEnd);

        return view('bikes.new_arrivals', [
            'data' => $data,
            'weekDays' => $weekDays,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'targetDate' => $targetDate,
        ]);
    }

    private function getNewArrivals(Carbon $date): array
    {
        $baseQuery = fn () => Listing::where('is_sold_out', false)
            ->whereDate('created_at', $date);

        $totalNew = $baseQuery()->count();

        $makerCounts = $baseQuery()
            ->whereNotNull('manufacturer_id')
            ->select('manufacturer_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('manufacturer_id')
            ->orderByDesc('cnt')
            ->get();

        $mfrs = Manufacturer::whereIn('id', $makerCounts->pluck('manufacturer_id'))
            ->get()->keyBy('id');

        $makerCounts = $makerCounts->map(fn ($item) => [
            'name' => $mfrs->get($item->manufacturer_id)?->name ?? '不明',
            'cnt' => $item->cnt,
        ]);

        $priceBands = $baseQuery()
            ->whereNotNull('total_price')
            ->select(DB::raw("
                CASE
                    WHEN total_price < 200000 THEN '〜20万円'
                    WHEN total_price < 400000 THEN '20〜40万円'
                    WHEN total_price < 600000 THEN '40〜60万円'
                    WHEN total_price < 800000 THEN '60〜80万円'
                    ELSE '80万円〜'
                END as price_range
            "), DB::raw('COUNT(*) as cnt'))
            ->groupBy('price_range')
            ->orderByDesc('cnt')
            ->get();

        $modelTop20Rows = $baseQuery()
            ->whereNotNull('bike_model_id')
            ->select('bike_model_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('bike_model_id')
            ->orderByDesc('cnt')
            ->limit(20)
            ->get();

        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $modelTop20Rows->pluck('bike_model_id'))
            ->get()->keyBy('id');

        $modelTop20 = $modelTop20Rows->map(fn ($item) => [
            'bike_model_id' => $item->bike_model_id,
            'name' => $models->get($item->bike_model_id)?->name ?? '不明',
            'manufacturer' => $models->get($item->bike_model_id)?->manufacturer?->name ?? '不明',
            'image_url' => $models->get($item->bike_model_id)?->image_url,
            'seo_url' => $models->get($item->bike_model_id)?->seo_url,
            'cnt' => $item->cnt,
        ]);

        // 7日間推移
        $trend7days = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $date->copy()->subDays($i);
            $trend7days[] = [
                'label' => $d->format('n/j'),
                'count' => Listing::where('is_sold_out', false)
                    ->whereDate('created_at', $d)
                    ->count(),
            ];
        }

        return [
            'totalNew' => $totalNew,
            'makerCounts' => $makerCounts,
            'priceBands' => $priceBands,
            'modelTop20' => $modelTop20,
            'trend7days' => $trend7days,
            'date' => $date->toDateString(),
        ];
    }

    private function getWeekSummary(Carbon $start, Carbon $end): array
    {
        $cacheKey = "new_arrivals_week_{$start->toDateString()}_{$end->toDateString()}";
        $ttl = $end->gte(Carbon::today()) ? 3600 : 86400;

        $dayCounts = Cache::remember($cacheKey, $ttl, function () use ($start, $end) {
            return Listing::where('is_sold_out', false)
                ->whereBetween('created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                ->select(DB::raw('DATE(created_at) as arrival_date'), DB::raw('COUNT(*) as cnt'))
                ->groupBy(DB::raw('DATE(created_at)'))
                ->pluck('cnt', 'arrival_date')
                ->toArray();
        });

        $days = [];
        $current = $start->copy();
        while ($current->lte($end)) {
            $dateStr = $current->toDateString();
            $days[] = [
                'date' => $dateStr,
                'label' => $current->format('n/j'),
                'dow' => ['日', '月', '火', '水', '木', '金', '土'][$current->dayOfWeek],
                'count' => $dayCounts[$dateStr] ?? 0,
                'isFuture' => $current->isFuture(),
            ];
            $current->addDay();
        }

        return $days;
    }
}
