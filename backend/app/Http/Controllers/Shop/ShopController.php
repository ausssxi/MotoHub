<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use App\Models\Shop;
use App\Services\Shop\ShopService;

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

        return view('shops.show', $data);
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

        $crossLinks = [
            ['label' => '中古バイク検索', 'url' => route('bikes.search'), 'icon' => 'search', 'description' => '全国の在庫を検索'],
            ['label' => '車種カタログ', 'url' => route('bikes.models'), 'icon' => 'book-open', 'description' => '車種の相場を確認'],
            ['label' => 'ショップマップ', 'url' => route('shops.map'), 'icon' => 'store', 'description' => 'バイクショップを探す'],
        ];

        return view('shops.chain', compact('chain', 'chainSlug', 'shops', 'totalStock', 'crossLinks'));
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