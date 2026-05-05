<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Shop\ShopAreaService;
use Illuminate\Contracts\View\View;

final class ShopAreaController extends Controller
{
    public function __construct(
        private readonly ShopAreaService $areaService,
    ) {}

    /**
     * エリアインデックス（全都道府県一覧）
     */
    public function index(): View
    {
        $data = $this->areaService->getAreaIndex();

        return view('shops.area-index', $data);
    }

    /**
     * 都道府県ページ
     */
    public function prefecture(string $prefecture): View
    {
        $data = $this->areaService->getPrefectureDetail($prefecture);

        if (! $data) {
            abort(404);
        }

        $crossLinks = [
            ['label' => '中古バイク検索', 'url' => route('bikes.search', ['prefecture' => mb_substr($prefecture, 0, -1)]), 'icon' => 'search', 'description' => $prefecture.'の在庫を検索'],
            ['label' => 'ショップマップ', 'url' => route('shops.map'), 'icon' => 'store', 'description' => '全国のバイクショップを探す'],
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => 'バイク駐車場を探す'],
        ];

        return view('shops.area-prefecture', array_merge($data, ['crossLinks' => $crossLinks]));
    }

    /**
     * 市区町村ページ
     */
    public function city(string $prefecture, string $city): View
    {
        $data = $this->areaService->getCityDetail($prefecture, $city);

        if (! $data) {
            abort(404);
        }

        $crossLinks = [
            ['label' => '中古バイク検索', 'url' => route('bikes.search', ['prefecture' => mb_substr($prefecture, 0, -1)]), 'icon' => 'search', 'description' => $prefecture.'の在庫を検索'],
            ['label' => 'ショップマップ', 'url' => route('shops.map'), 'icon' => 'store', 'description' => '全国のバイクショップを探す'],
            ['label' => $prefecture.'のショップ', 'url' => route('shops.area.prefecture', $prefecture), 'icon' => 'map-pin', 'description' => $prefecture.'のバイクショップ一覧'],
        ];

        return view('shops.area-city', array_merge($data, ['crossLinks' => $crossLinks]));
    }
}
