<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Models\Manufacturer;
use App\Models\PriceHistory;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class PriceDropsController extends Controller
{
    public function index(?string $date = null): View
    {
        $targetDate = $date ? Carbon::parse($date) : Carbon::today();

        if ($targetDate->isFuture()) {
            $targetDate = Carbon::today();
        }

        $data = Cache::remember(
            "price-drops:{$targetDate->toDateString()}",
            $targetDate->isToday() ? 3600 : 86400,
            fn () => $this->getPriceDrops($targetDate),
        );

        $weekStart = $targetDate->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);
        if ($weekEnd->isFuture()) {
            $weekEnd = Carbon::today();
        }
        $weekDays = $this->getWeekSummary($weekStart, $weekEnd);

        return view('bikes.price_drops', [
            'data' => $data,
            'weekDays' => $weekDays,
            'weekStart' => $weekStart,
            'weekEnd' => $weekEnd,
            'targetDate' => $targetDate,
        ]);
    }

    /**
     * 値下げ表示用のしきい値（config/price_alerts.php）を適用する。数値はベタ書きしない。
     * 通知と同基準: 値下げのみ(old>new) / 変動額 >= min_drop_amount / 変動率 <= max_drop_ratio（桁違い誤読を除外）。
     * min_drop_ratio(1%) は通知専用のためここでは使わない（一覧・件数から実在の小額値下げまで消さないため）。
     * join/非join の両方で使うため列は price_histories. で明示（曖昧さ回避）。
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Query\Builder
     */
    private function applyDropThreshold($query)
    {
        $minAmount = (int) config('price_alerts.min_drop_amount', 5000);
        $maxRatio = (float) config('price_alerts.max_drop_ratio', 0.5);

        return $query
            ->whereColumn('price_histories.old_price', '>', 'price_histories.new_price')
            ->whereRaw('(price_histories.old_price - price_histories.new_price) >= ?', [$minAmount])
            ->whereRaw('(price_histories.old_price - price_histories.new_price) <= price_histories.old_price * ?', [$maxRatio]);
    }

    private function getPriceDrops(Carbon $date): array
    {
        // total / 額TOP20 / 率TOP20 が共有。しきい値を一元適用（通知と同基準）。
        $baseQuery = fn () => $this->applyDropThreshold(
            PriceHistory::whereDate('price_histories.created_at', $date)
                ->whereHas('listing', fn ($q) => $q->where('is_sold_out', false))
        );

        $totalDrops = $baseQuery()->count();

        // 値下げ額TOP20
        $topByAmount = $baseQuery()
            ->with(['listing.bikeModel.manufacturer'])
            ->orderByRaw('(old_price - new_price) DESC')
            ->limit(20)
            ->get()
            ->map(fn ($ph) => [
                'bike_name' => $ph->listing->bikeModel?->name ?? '不明',
                'manufacturer' => $ph->listing->bikeModel?->manufacturer?->name ?? '',
                'seo_url' => $ph->listing->bikeModel?->seo_url,
                'listing_id' => $ph->listing_id,
                'old_price' => $ph->old_price,
                'new_price' => $ph->new_price,
                'diff' => $ph->old_price - $ph->new_price,
                'rate' => $ph->old_price > 0
                    ? round(($ph->old_price - $ph->new_price) / $ph->old_price * 100, 1)
                    : 0,
            ]);

        // 値下げ率TOP20
        $topByRate = $baseQuery()
            ->where('old_price', '>', 0)
            ->with(['listing.bikeModel.manufacturer'])
            ->orderByRaw('((old_price - new_price) / old_price) DESC')
            ->limit(20)
            ->get()
            ->map(fn ($ph) => [
                'bike_name' => $ph->listing->bikeModel?->name ?? '不明',
                'manufacturer' => $ph->listing->bikeModel?->manufacturer?->name ?? '',
                'seo_url' => $ph->listing->bikeModel?->seo_url,
                'listing_id' => $ph->listing_id,
                'old_price' => $ph->old_price,
                'new_price' => $ph->new_price,
                'diff' => $ph->old_price - $ph->new_price,
                'rate' => round(($ph->old_price - $ph->new_price) / $ph->old_price * 100, 1),
            ]);

        // メーカー別（同一ページの totalDrops と整合させるため同じしきい値を適用）
        $makerCounts = $this->applyDropThreshold(
            PriceHistory::whereDate('price_histories.created_at', $date)
                ->join('listings', 'price_histories.listing_id', '=', 'listings.id')
                ->where('listings.is_sold_out', false)
                ->whereNotNull('listings.manufacturer_id')
        )
            ->select('listings.manufacturer_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('listings.manufacturer_id')
            ->orderByDesc('cnt')
            ->get();

        $mfrs = Manufacturer::whereIn('id', $makerCounts->pluck('manufacturer_id'))
            ->get()->keyBy('id');

        $makerCounts = $makerCounts->map(fn ($item) => [
            'name' => $mfrs->get($item->manufacturer_id)?->name ?? '不明',
            'cnt' => $item->cnt,
        ]);

        // 7日間推移
        $trend7days = [];
        for ($i = 6; $i >= 0; $i--) {
            $d = $date->copy()->subDays($i);
            $trend7days[] = [
                'label' => $d->format('n/j'),
                'count' => $this->applyDropThreshold(
                    PriceHistory::whereDate('price_histories.created_at', $d)
                        ->whereHas('listing', fn ($q) => $q->where('is_sold_out', false))
                )->count(),
            ];
        }

        return [
            'totalDrops' => $totalDrops,
            'topByAmount' => $topByAmount,
            'topByRate' => $topByRate,
            'makerCounts' => $makerCounts,
            'trend7days' => $trend7days,
            'date' => $date->toDateString(),
        ];
    }

    private function getWeekSummary(Carbon $start, Carbon $end): array
    {
        $cacheKey = "price_drops_week_{$start->toDateString()}_{$end->toDateString()}";
        $ttl = $end->gte(Carbon::today()) ? 3600 : 86400;

        $dayCounts = Cache::remember($cacheKey, $ttl, function () use ($start, $end) {
            return $this->applyDropThreshold(
                PriceHistory::whereBetween('price_histories.created_at', [$start->copy()->startOfDay(), $end->copy()->endOfDay()])
                    ->join('listings', 'price_histories.listing_id', '=', 'listings.id')
                    ->where('listings.is_sold_out', false)
            )
                ->select(DB::raw('DATE(price_histories.created_at) as drop_date'), DB::raw('COUNT(*) as cnt'))
                ->groupBy(DB::raw('DATE(price_histories.created_at)'))
                ->pluck('cnt', 'drop_date')
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
