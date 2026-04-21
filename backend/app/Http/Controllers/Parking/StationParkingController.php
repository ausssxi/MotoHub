<?php

declare(strict_types=1);

namespace App\Http\Controllers\Parking;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Services\Parking\StationParkingService;
use Illuminate\Contracts\View\View;

final class StationParkingController extends Controller
{
    public function __construct(
        private readonly StationParkingService $service
    ) {}

    /**
     * 駅別駐車場一覧（主要駅インデックス）
     */
    public function index(): View
    {
        $data = $this->service->getStationIndex();

        return view('parking.station-index', $data);
    }

    /**
     * 駅別駐車場詳細
     */
    public function show(Station $station): View
    {
        $data = $this->service->getStationDetail($station);

        $crossLinks = [
            ['label' => '中古バイク検索', 'url' => route('bikes.search', ['prefecture' => mb_substr($station->prefecture, 0, -1)]), 'icon' => 'search', 'description' => $station->prefecture.'の在庫を検索'],
            ['label' => '駐車場マップ', 'url' => route('parking.index'), 'icon' => 'square-parking', 'description' => '全国の駐車場を探す'],
            ['label' => $station->prefecture.'の駐車場', 'url' => route('parking.area.prefecture', $station->prefecture), 'icon' => 'map-pin', 'description' => $station->prefecture.'の駐車場一覧'],
        ];

        return view('parking.station-show', array_merge($data, ['crossLinks' => $crossLinks]));
    }
}
