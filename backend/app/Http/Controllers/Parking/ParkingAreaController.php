<?php

declare(strict_types=1);

namespace App\Http\Controllers\Parking;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Services\Parking\ParkingAreaService;
use App\Services\Parking\StationParkingService;
use Illuminate\Contracts\View\View;

class ParkingAreaController extends Controller
{
    public function __construct(
        private readonly ParkingAreaService $areaService,
        private readonly StationParkingService $stationService
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

        if (! $data) {
            abort(404);
        }

        $crossLinks = [
            ['label' => '中古バイク検索', 'url' => route('bikes.search', ['prefecture' => mb_substr($prefecture, 0, -1)]), 'icon' => 'search', 'description' => $prefecture.'の在庫を検索'],
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

        if (! $data) {
            abort(404);
        }

        // この市区町村の駅を取得
        $stations = Station::where('prefecture', $prefecture)
            ->where('city', $city)
            ->withCount('bikeParkings')
            ->orderByDesc('bike_parkings_count')
            ->orderBy('name')
            ->get();

        $crossLinks = [
            ['label' => '中古バイク検索', 'url' => route('bikes.search', ['prefecture' => mb_substr($prefecture, 0, -1)]), 'icon' => 'search', 'description' => $prefecture.'の在庫を検索'],
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => '全国の駐車場を探す'],
            ['label' => $prefecture.'の駐車場', 'url' => route('parking.area.prefecture', $prefecture), 'icon' => 'map-pin', 'description' => $prefecture.'の駐車場一覧'],
        ];

        return view('parking.area-city', array_merge($data, [
            'crossLinks' => $crossLinks,
            'stations' => $stations,
        ]));
    }

    /**
     * 市区町村配下の駅ページ（Canonical → /parking/station/{slug}）
     */
    public function stationInArea(string $prefecture, string $city, string $stationName): View
    {
        // stationNameから「駅」サフィックスを除去
        $cleanName = preg_replace('/駅$/u', '', $stationName);

        $station = Station::where('prefecture', $prefecture)
            ->where('city', $city)
            ->where('name', $cleanName)
            ->firstOrFail();

        $data = $this->stationService->getStationDetail($station);

        $canonicalUrl = route('parking.station.show', $station->slug);

        $breadcrumb = [
            ['label' => 'HOME', 'url' => route('bikes.index')],
            ['label' => '駐車場マップ', 'url' => route('parking.index')],
            ['label' => 'エリアから探す', 'url' => route('parking.area.index')],
            ['label' => $prefecture, 'url' => route('parking.area.prefecture', $prefecture)],
            ['label' => $city, 'url' => route('parking.area.city', [$prefecture, $city])],
            ['label' => $cleanName.'駅', 'url' => null],
        ];

        $crossLinks = [
            ['label' => '中古バイク検索', 'url' => route('bikes.search', ['prefecture' => mb_substr($prefecture, 0, -1)]), 'icon' => 'search', 'description' => $prefecture.'の在庫を検索'],
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => '全国の駐車場を探す'],
            ['label' => $prefecture.'の駐車場', 'url' => route('parking.area.prefecture', $prefecture), 'icon' => 'map-pin', 'description' => $prefecture.'の駐車場一覧'],
        ];

        return view('parking.station-show', array_merge($data, [
            'crossLinks' => $crossLinks,
            'canonicalUrl' => $canonicalUrl,
            'breadcrumb' => $breadcrumb,
        ]));
    }
}
