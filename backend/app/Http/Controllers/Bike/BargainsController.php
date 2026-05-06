<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\Manufacturer;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class BargainsController extends Controller
{
    private const DISCOUNT_THRESHOLD = 0.8;

    public function index(): View
    {
        $data = Cache::remember('bargains:page', 3600, fn () => $this->getBargains());

        return view('bikes.bargains', ['data' => $data]);
    }

    private function getBargains(): array
    {
        // 「他車種」カテゴリのbike_model_idを除外（相場比較の信頼性が低い）
        $excludedModelIds = BikeModel::where('name', '他車種')->pluck('id');

        // bike_model_id ごとの平均価格を取得（同一車種3台以上のみ）
        $modelAverages = Listing::where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->whereNotIn('bike_model_id', $excludedModelIds)
            ->select('bike_model_id', DB::raw('AVG(total_price) as avg_price'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('bike_model_id')
            ->having('cnt', '>=', 3) // 3台以上ないと相場が信頼できない
            ->pluck('avg_price', 'bike_model_id');

        // お買い得車両を取得
        $listings = Listing::with(['bikeModel.manufacturer'])
            ->where('is_sold_out', false)
            ->whereNotNull('bike_model_id')
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->whereIn('bike_model_id', $modelAverages->keys())
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $bargains = [];
        foreach ($listings as $listing) {
            $avgPrice = (float) $modelAverages[$listing->bike_model_id];
            if ($avgPrice <= 0) {
                continue;
            }

            if ($listing->total_price < ($avgPrice * self::DISCOUNT_THRESHOLD)) {
                $percentOff = (int) round((($avgPrice - $listing->total_price) / $avgPrice) * 100);
                $bargains[] = [
                    'id' => $listing->id,
                    'name' => $listing->bikeModel?->name ?? '不明',
                    'manufacturer' => $listing->bikeModel?->manufacturer?->name ?? '',
                    'price' => $listing->total_price,
                    'avg_price' => (int) round($avgPrice),
                    'percent_off' => $percentOff,
                    'model_year' => $listing->model_year,
                    'mileage' => $listing->mileage,
                    'image' => $listing->images[0] ?? null,
                    'seo_url' => $listing->bikeModel?->seo_url,
                    'mfr_slug' => $listing->bikeModel?->manufacturer?->slug,
                    'model_slug' => $listing->bikeModel?->slug,
                ];
            }
        }

        // 割引率順にソート
        usort($bargains, fn ($a, $b) => $b['percent_off'] <=> $a['percent_off']);

        // 上位100件に絞る
        $bargains = array_slice($bargains, 0, 100);

        // 統計情報
        $totalCount = count($bargains);
        $maxDiscount = $totalCount > 0 ? max(array_column($bargains, 'percent_off')) : 0;
        $avgDiscount = $totalCount > 0 ? (int) round(array_sum(array_column($bargains, 'percent_off')) / $totalCount) : 0;
        $minPrice = $totalCount > 0 ? min(array_column($bargains, 'price')) : 0;

        // メーカー別内訳
        $makerCounts = collect($bargains)
            ->groupBy('manufacturer')
            ->map(fn ($items, $name) => ['name' => $name ?: '不明', 'cnt' => count($items)])
            ->sortByDesc('cnt')
            ->values();

        // 割引率帯別分布
        $discountBands = collect($bargains)
            ->groupBy(function ($item) {
                if ($item['percent_off'] >= 50) return '50%OFF〜';
                if ($item['percent_off'] >= 40) return '40〜50%OFF';
                if ($item['percent_off'] >= 30) return '30〜40%OFF';
                return '20〜30%OFF';
            })
            ->map(fn ($items, $label) => ['label' => $label, 'cnt' => count($items)])
            ->sortByDesc('cnt')
            ->values();

        return [
            'bargains' => $bargains,
            'totalCount' => $totalCount,
            'maxDiscount' => $maxDiscount,
            'avgDiscount' => $avgDiscount,
            'minPrice' => $minPrice,
            'makerCounts' => $makerCounts,
            'discountBands' => $discountBands,
        ];
    }
}
