<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BikeModel;
use App\Services\Bike\PriceStatsService;
use Illuminate\Http\JsonResponse;

/**
 * 埋め込みウィジェット用の公開API
 * 外部サイトからJSで呼び出される（CORS許可）
 */
class WidgetApiController extends Controller
{
    public function __construct(
        private PriceStatsService $priceStatsService
    ) {}

    /**
     * 車種の価格情報をJSON返却
     * GET /api/widget/price/{bikeModelId}
     */
    public function price(int $bikeModelId): JsonResponse
    {
        $model = BikeModel::with('manufacturer')->find($bikeModelId);

        if (!$model) {
            return response()->json(['error' => 'Model not found'], 404);
        }

        $stats = $this->priceStatsService->getModelStats($bikeModelId);
        $resale = $this->priceStatsService->getResaleStats($bikeModelId);

        $data = [
            'model_name'        => $model->name,
            'manufacturer_name' => $model->manufacturer?->name ?? '',
            'displacement'      => $model->displacement,
            'image_url'         => $model->image_url,
            'seo_url'           => $model->seo_url,
            'stats'             => [
                'count' => $stats['count'] ?? 0,
                'avg'   => $stats['avg'] ?? null,
                'min'   => $stats['min'] ?? null,
                'max'   => $stats['max'] ?? null,
            ],
            'resale'            => [
                'min'        => $resale['resale_min'] ?? null,
                'max'        => $resale['resale_max'] ?? null,
                'data_count' => $resale['data_count'] ?? 0,
            ],
            'updated_at'        => now()->format('Y/m/d'),
        ];

        return response()->json($data)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET')
            ->header('Cache-Control', 'public, max-age=3600'); // 1時間キャッシュ
    }
}