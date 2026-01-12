<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BikeService;
use Illuminate\Contracts\View\View;

/**
 * 車種関連の画面制御を担当
 */
final class BikeController extends Controller
{
    /**
     * @param BikeService $bikeService
     */
    public function __construct(
        private readonly BikeService $bikeService
    ) {}

    /**
     * トップページ（車種一覧）の表示
     *
     * @return View
     */
    public function index(): View
    {
        // 人気車種の取得
        $popularBikes = $this->bikeService->getPopularBikesForTopPage();

        // Bladeで使用している地域・都道府県データ
        $regions = config('bike.regions');
        /**
         * compact に 'regions' を追加して渡します
         */
        return view('bikes.index', compact('popularBikes', 'regions'));
    }
}