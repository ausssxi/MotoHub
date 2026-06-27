<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Bike\ClassRankingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * 外部パートナー向け ランキングデータAPI（第1段階・APIキー認証＋throttle）。
 * 認証は api.key ミドルウェア、レート制限は throttle:rankings-api（routes/api.php で適用）。
 */
final class RankingApiController extends Controller
{
    private const LIMIT = 20;          // TOP20

    private const CACHE_TTL = 3600;    // 1時間（データは日次更新で十分・重い再集計を毎回走らせない）

    /**
     * GET /api/v1/rankings/listings?class={50|125|250|400|middle|large}
     * クラス別の掲載台数ランキング（在庫数・降順）を返す。
     * 受理クラスは ClassRankingService::RANGES と一致（サイト表示と同じ集計＝数字も一致）。
     */
    public function listings(Request $request, ClassRankingService $service): JsonResponse
    {
        $class = ClassRankingService::normalizeClass((string) $request->query('class', ''));

        if ($class === null) {
            return response()->json([
                'error' => 'invalid_class',
                'message' => 'class は '.implode(' / ', ClassRankingService::classes()).' のいずれかを指定してください。',
                'allowed' => ClassRankingService::classes(),
            ], 422);
        }

        try {
            $payload = Cache::remember("api_v1_rankings_listings_v2_{$class}", self::CACHE_TTL, function () use ($service, $class) {
                return [
                    'generated_at' => now()->toIso8601String(),
                    'rankings' => $service->classRanking($class, self::LIMIT),
                ];
            });
        } catch (\Throwable $e) {
            Log::error('rankings api failed', ['class' => $class, 'error' => $e->getMessage()]);

            return response()->json([
                'error' => 'temporarily_unavailable',
                'message' => '一時的にデータを取得できません。時間をおいて再度お試しください。',
            ], 503);
        }

        return response()->json([
            'class' => $class,
            'updated_at' => $payload['generated_at'],
            'source' => 'MotoHub (motohub.jp)',
            'count' => count($payload['rankings']),
            'rankings' => $payload['rankings'],
        ]);
    }
}
