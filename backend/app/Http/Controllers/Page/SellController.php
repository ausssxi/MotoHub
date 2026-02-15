<?php

declare(strict_types=1);

namespace App\Http\Controllers\Page;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use App\Services\Bike\BikeService;
use App\Services\Bike\PriceStatsService;
use App\Models\BikeModel;

class SellController extends Controller
{
    public function __construct(
        private readonly BikeService $bikeService,
        private readonly PriceStatsService $priceStatsService
    ) {}

    /**
     * 査定LPの表示
     */
    public function index(): View
    {
        // メーカー一覧を取得（プルダウン用）
        $manufacturers = $this->bikeService->getAllManufacturers();
        
        return view('pages.sell', compact('manufacturers'));
    }

    /**
     * 査定額の計算API
     */
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'bike_model_id' => 'required|integer|exists:bike_models,id',
            'year' => 'nullable|integer|min:1980|max:' . (date('Y') + 1),
        ]);

        $modelId = (int)$request->input('bike_model_id');
        $year = $request->input('year') ? (int)$request->input('year') : null;

        // 車種情報の取得
        $model = BikeModel::with('manufacturer')->find($modelId);
        
        // 査定額の計算
        $result = $this->priceStatsService->estimatePurchasePrice($modelId, $year);

        return response()->json(array_merge($result, [
            'model_name' => $model->name,
            'maker_name' => $model->manufacturer->name,
            'year' => $year,
        ]));
    }
}