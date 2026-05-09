<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\Manufacturer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class DiscontinuedController extends Controller
{
    private const PER_PAGE = 30;

    public function index(Request $request): View
    {
        $query = BikeModel::with('manufacturer')
            ->where('is_discontinued', true);

        // フィルター: メーカー
        if ($request->filled('manufacturer')) {
            $query->whereHas('manufacturer', fn ($q) => $q->where('slug', $request->input('manufacturer')));
        }

        // フィルター: 排気量帯
        if ($request->filled('displacement')) {
            $query = $this->applyDisplacementFilter($query, $request->input('displacement'));
        }

        // フィルター: 生産終了年代
        if ($request->filled('era')) {
            $query = $this->applyEraFilter($query, $request->input('era'));
        }

        // ソート
        $sort = $request->input('sort', 'stock');

        $models = $query->withCount(['listings as active_count' => fn ($q) => $q->where('is_sold_out', false)])
            ->when($sort === 'stock', fn ($q) => $q->orderByDesc('active_count'))
            ->when($sort === 'year', fn ($q) => $q->orderByDesc('production_end_year'))
            ->when($sort === 'name', fn ($q) => $q->orderBy('name'))
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        // 在庫数・価格情報を一括取得
        $modelIds = $models->pluck('id')->toArray();
        $priceStats = $this->getPriceStats($modelIds);

        // 人気ピックアップ（在庫数TOP10）
        $pickup = Cache::remember('discontinued:pickup', 3600, function () {
            return BikeModel::with('manufacturer')
                ->where('is_discontinued', true)
                ->withCount(['listings as active_count' => fn ($q) => $q->where('is_sold_out', false)])
                ->orderByDesc('active_count')
                ->limit(10)
                ->get();
        });
        $pickupPriceStats = $this->getPriceStats($pickup->pluck('id')->toArray());

        // フィルター用メーカー一覧
        $manufacturers = Cache::remember('discontinued:manufacturers', 3600, function () {
            return Manufacturer::whereHas('bikeModels', fn ($q) => $q->where('is_discontinued', true))
                ->orderBy('name')
                ->get(['id', 'name', 'slug']);
        });

        // 統計
        $totalCount = Cache::remember('discontinued:total', 3600, function () {
            return BikeModel::where('is_discontinued', true)->count();
        });

        return view('bikes.discontinued', [
            'models' => $models,
            'priceStats' => $priceStats,
            'pickup' => $pickup,
            'pickupPriceStats' => $pickupPriceStats,
            'manufacturers' => $manufacturers,
            'totalCount' => $totalCount,
            'currentFilters' => $request->only(['manufacturer', 'displacement', 'era', 'sort']),
        ]);
    }

    private function applyDisplacementFilter($query, string $band)
    {
        return match ($band) {
            '125' => $query->where('displacement', '<=', 125),
            '250' => $query->whereBetween('displacement', [126, 250]),
            '400' => $query->whereBetween('displacement', [251, 400]),
            '401' => $query->where('displacement', '>', 400),
            default => $query,
        };
    }

    private function applyEraFilter($query, string $era)
    {
        return match ($era) {
            '2000' => $query->where('production_end_year', '<=', 2000),
            '2010' => $query->whereBetween('production_end_year', [2001, 2010]),
            '2020' => $query->whereBetween('production_end_year', [2011, 2020]),
            default => $query,
        };
    }

    /**
     * @return array<int, array{min: int, max: int, count: int}>
     */
    private function getPriceStats(array $modelIds): array
    {
        if (empty($modelIds)) {
            return [];
        }

        return Listing::where('is_sold_out', false)
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->whereIn('bike_model_id', $modelIds)
            ->select(
                'bike_model_id',
                DB::raw('MIN(total_price) as min_price'),
                DB::raw('MAX(total_price) as max_price'),
                DB::raw('COUNT(*) as listing_count')
            )
            ->groupBy('bike_model_id')
            ->get()
            ->keyBy('bike_model_id')
            ->map(fn ($row) => [
                'min' => (int) round($row->min_price / 10000),
                'max' => (int) round($row->max_price / 10000),
                'count' => $row->listing_count,
            ])
            ->toArray();
    }
}
