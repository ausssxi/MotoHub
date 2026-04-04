<?php

declare(strict_types=1);

namespace App\Http\Controllers\Parking;

use App\Http\Controllers\Controller;
use App\Services\Parking\ParkingAreaService;
use Illuminate\Contracts\View\View;

class ParkingAreaController extends Controller
{
    public function __construct(
        private readonly ParkingAreaService $areaService
    ) {}

    /**
     * エリアインデックス（全都道府県一覧）
     */
    public function index(): View
    {
        $data = $this->areaService->getAreaIndex();

        return view('parking.area-index', $data);
    }

    /**
     * 都道府県ページ
     */
    public function prefecture(string $prefecture): View
    {
        $data = $this->areaService->getPrefectureDetail($prefecture);

        if (!$data) {
            abort(404);
        }

        $crossLinks = [
            ['label' => '中古バイク検索', 'url' => route('bikes.search', ['prefecture' => mb_substr($prefecture, 0, -1)]), 'icon' => 'search', 'description' => $prefecture . 'の在庫を検索'],
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => '全国の駐車場を探す'],
            ['label' => 'ショップマップ', 'url' => route('shops.map'), 'icon' => 'store', 'description' => 'バイクショップを探す'],
        ];

        return view('parking.area', array_merge($data, ['crossLinks' => $crossLinks]));
    }

    /**
     * 市区町村ページ
     */
    public function city(string $prefecture, string $city): View
    {
        $data = $this->areaService->getCityDetail($prefecture, $city);

        if (!$data) {
            abort(404);
        }

        $crossLinks = [
            ['label' => '中古バイク検索', 'url' => route('bikes.search', ['prefecture' => mb_substr($prefecture, 0, -1)]), 'icon' => 'search', 'description' => $prefecture . 'の在庫を検索'],
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => '全国の駐車場を探す'],
            ['label' => $prefecture . 'の駐車場', 'url' => route('parking.area.prefecture', $prefecture), 'icon' => 'map-pin', 'description' => $prefecture . 'の駐車場一覧'],
        ];

        return view('parking.area-city', array_merge($data, ['crossLinks' => $crossLinks]));
    }
}
