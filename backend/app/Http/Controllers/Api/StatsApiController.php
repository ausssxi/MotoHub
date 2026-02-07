<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Bike\PriceStatsService;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function __construct(
        private readonly PriceStatsService $statsService
    ) {}

    /**
     * 車種ごとの価格統計を取得
     */
    public function getPriceStats(int $bikeModelId): JsonResponse
    {
        $stats = $this->statsService->getModelStats($bikeModelId);
        return response()->json($stats);
    }
}