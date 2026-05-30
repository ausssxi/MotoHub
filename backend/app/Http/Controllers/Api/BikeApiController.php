<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BikeModel;
use App\Services\Bike\ListingSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * フロントエンドからの非同期リクエストを処理するAPIコントローラー
 */
final class BikeApiController extends Controller
{
    public function __construct(
        private readonly ListingSearchService $searchService
    ) {}

    /**
     * 現在の絞り込み条件に基づくヒット件数を返す
     * GET /api/bikes/count
     */
    public function count(Request $request): JsonResponse
    {
        // 検索パラメータの取得
        $keyword = $request->query('keyword');
        $prefecture = $request->query('prefecture');

        // フィルター項目の抽出
        $filters = $request->only([
            'min_price', 'max_price',
            'min_mileage', 'max_mileage',
            'min_year', 'max_year',
            'is_new', 'has_repair_history',
            'manufacturer_id', 'bike_model_id',
        ]);

        // 件数の取得（Service側のロジックを利用）
        $count = $this->searchService->getFilteredCount($keyword, $prefecture, $filters);

        return response()->json([
            'count' => $count,
        ]);
    }

    /**
     * 特定メーカーの車種一覧を返す（ドリルダウン用）
     * GET /api/manufacturers/{manufacturer}/models
     */
    public function models(int $manufacturerId): JsonResponse
    {
        $models = Cache::remember(
            "api_models_by_manufacturer_v1_{$manufacturerId}",
            1800,
            fn () => $this->searchService->getModelsByManufacturer($manufacturerId)
        );

        return response()->json($models);
    }

    /**
     * 特定メーカーの車種一覧（軽量版: id, name のみ）
     * GET /api/manufacturers/{manufacturer}/models-light
     */
    public function modelsLight(int $manufacturerId): JsonResponse
    {
        $models = BikeModel::where('manufacturer_id', $manufacturerId)
            ->select(['id', 'name'])
            ->orderBy('name', 'asc')
            ->get();

        return response()->json($models);
    }
}
