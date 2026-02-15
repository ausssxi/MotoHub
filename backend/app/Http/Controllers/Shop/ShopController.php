<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
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
     * ショップマップページ
     */
    public function map(): View
    {
        return view('shops.map');
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