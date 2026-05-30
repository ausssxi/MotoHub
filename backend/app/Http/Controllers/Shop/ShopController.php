<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use App\Models\Shop;
use App\Models\Listing;
use App\Models\BikeModel;
use App\Models\BlogPost;
use App\Services\Shop\ShopService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ShopController extends Controller
{
    public function __construct(
        private readonly ShopService $shopService
    ) {}

    /**
     * 店舗詳細ページ
     */
    public function show(int $id): View
    {
        // サービスからデータ（shop, listings）を一括取得
        $data = $this->shopService->getShopDetailWithListings($id);

        // 販売実績データ
        $data['salesStats'] = $this->getShopSalesStats($id);

        // チェーン店判定（在庫一括管理の案内用）
        $data['chainInfo'] = null;
        $shop = $data['shop'];
        foreach (config('bike.chains', []) as $slug => $chain) {
            if (str_contains($shop->name, $chain['pattern'])) {
                $mainShop = \App\Models\Shop::where('name', 'like', "%{$chain['pattern']}%")
                    ->withCount(['listings' => fn ($q) => $q->where('is_sold_out', 0)])
                    ->orderByDesc('listings_count')
                    ->first();
                if ($mainShop && $mainShop->id !== $shop->id && $mainShop->listings_count > 0) {
                    $data['chainInfo'] = [
                        'name' => $chain['name'],
                        'slug' => $slug,
                        'main_shop_id' => $mainShop->id,
                        'stock' => $mainShop->listings_count,
                    ];
                }
                break;
            }
        }

        return view('shops.show', $data);
    }

    private function getShopSalesStats(int $shopId): ?array
    {
        return Cache::remember("shop_sales_stats_{$shopId}", 3600, function () use ($shopId) {
            $threeMonthsAgo = Carbon::now()->subMonths(3);

            $totalSold = Listing::where('shop_id', $shopId)
                ->where('is_sold_out', true)
                ->where('updated_at', '>=', $threeMonthsAgo)
                ->count();

            if ($totalSold === 0) {
                return null;
            }

            // 販売推移（過去6ヶ月）
            $monthlySales = [];
            for ($i = 5; $i >= 0; $i--) {
                $ms = Carbon::now()->subMonths($i)->startOfMonth();
                $me = Carbon::now()->subMonths($i)->endOfMonth();
                $monthlySales[] = [
                    'label' => $ms->format('n月'),
                    'count' => Listing::where('shop_id', $shopId)
                        ->where('is_sold_out', true)
                        ->whereBetween('updated_at', [$ms, $me])
                        ->count(),
                ];
            }

            // 人気車種TOP5
            $topModels = Listing::where('shop_id', $shopId)
                ->where('is_sold_out', true)
                ->where('updated_at', '>=', $threeMonthsAgo)
                ->whereNotNull('bike_model_id')
                ->select('bike_model_id', DB::raw('COUNT(*) as sold_count'))
                ->groupBy('bike_model_id')
                ->orderByDesc('sold_count')
                ->limit(5)
                ->get();

            $models = BikeModel::with('manufacturer')
                ->whereIn('id', $topModels->pluck('bike_model_id'))
                ->get()->keyBy('id');

            $topModelsList = $topModels->map(function ($item) use ($models) {
                $m = $models->get($item->bike_model_id);
                return [
                    'bike_model_id' => $item->bike_model_id,
                    'name' => $m->name ?? '不明',
                    'manufacturer' => $m->manufacturer->name ?? '',
                    'seo_url' => $m?->seo_url,
                    'sold_count' => $item->sold_count,
                ];
            });

            // 平均在庫日数
            $avgDays = Listing::where('shop_id', $shopId)
                ->where('is_sold_out', true)
                ->where('updated_at', '>=', $threeMonthsAgo)
                ->selectRaw('AVG(DATEDIFF(updated_at, created_at)) as avg_days')
                ->value('avg_days');

            return [
                'totalSold' => $totalSold,
                'monthlySales' => $monthlySales,
                'topModels' => $topModelsList,
                'avgDays' => (int) round((float) ($avgDays ?? 0)),
            ];
        });
    }

    /**
     * 訪問済みカウントをインクリメント
     */
    public function visited(Shop $shop): JsonResponse
    {
        $shop->increment('visited_count');

        return response()->json(['count' => $shop->visited_count]);
    }

    /**
     * ショップマップページ
     */
    public function map(): View
    {
        return view('shops.map');
    }

    /**
     * チェーン別ショップまとめページ
     */
    public function chainShow(string $chainSlug): View
    {
        $chains = config('bike.chains');
        if (!isset($chains[$chainSlug])) {
            abort(404);
        }

        $chain = $chains[$chainSlug];
        $shops = Shop::where('name', 'like', "%{$chain['pattern']}%")
            ->withCount(['listings' => fn ($q) => $q->where('is_sold_out', 0)])
            ->orderByDesc('listings_count')
            ->get();

        $totalStock = $shops->sum('listings_count');

        $mainShop = $shops->sortByDesc('listings_count')->first();
        $mainShopStock = $mainShop?->listings_count ?? 0;

        // 解説記事（公開済みのみ）。config の guide_slug が設定されたチェーンだけ表示される。
        $guideArticle = !empty($chain['guide_slug'])
            ? BlogPost::published()->where('slug', $chain['guide_slug'])->first(['id', 'slug', 'title'])
            : null;

        $crossLinks = [
            ['label' => '中古バイク検索', 'url' => route('bikes.search'), 'icon' => 'search', 'description' => '全国の在庫を検索'],
            ['label' => '車種カタログ', 'url' => route('bikes.models'), 'icon' => 'book-open', 'description' => '車種の相場を確認'],
            ['label' => 'ショップマップ', 'url' => route('shops.map'), 'icon' => 'store', 'description' => 'バイクショップを探す'],
        ];

        return view('shops.chain', compact('chain', 'chainSlug', 'shops', 'totalStock', 'mainShop', 'mainShopStock', 'crossLinks', 'guideArticle'));
    }

    /**
     * 地図用データ取得API
     */
    public function area(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ne_lat' => 'required|numeric',
            'ne_lng' => 'required|numeric',
            'sw_lat' => 'required|numeric',
            'sw_lng' => 'required|numeric',
        ]);

        $shops = $this->shopService->getShopsInArea($validated);

        return response()->json($shops);
    }
}
