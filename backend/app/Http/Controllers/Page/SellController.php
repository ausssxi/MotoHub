<?php

declare(strict_types=1);

namespace App\Http\Controllers\Page;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use App\Services\Page\SellService; // 新しく作ったサービス

class SellController extends Controller
{
    public function __construct(
        private readonly SellService $sellService
    ) {}

    /**
     * 査定LPの表示
     */
    public function index(): View
    {
        // サービスからデータを取得
        $manufacturers = $this->sellService->getManufacturersForForm();
        
        return view('pages.sell', compact('manufacturers'));
    }

    /**
     * 査定額の計算API
     */
    public function calculate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'bike_model_id' => 'required|integer',
            'year' => 'nullable|integer|min:1980|max:' . (date('Y') + 1),
            'mileage' => 'nullable|integer|min:0|max:999999',
        ]);

        $modelId = (int)$validated['bike_model_id'];
        $year = !empty($validated['year']) ? (int)$validated['year'] : null;
        $mileage = !empty($validated['mileage']) ? (int)$validated['mileage'] : null;

        // ビジネスロジックを実行
        $result = $this->sellService->calculateAssessment($modelId, $year, $mileage);

        if (isset($result['status']) && $result['status'] === 'error') {
             return response()->json($result, 404);
        }

        return response()->json($result);
    }
}