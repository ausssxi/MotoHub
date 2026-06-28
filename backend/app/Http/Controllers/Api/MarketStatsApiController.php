<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Bike\MarketStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * 外部パートナー向け 市場全体 統計API（第1段階・APIキー認証＋throttle）。
 * 認証/レート制限は rankings 系と同じ v1 グループ（api.key + throttle:rankings-api）。
 *
 * 集計は MarketStatsService（直近3ヶ月成約・全車種）。結果は同サービス内で1時間キャッシュ。
 */
final class MarketStatsApiController extends Controller
{
    /**
     * GET /api/v1/market/stats
     * 中古市場全体（車種を絞らない）の 地域 / 年式 / 走行距離帯 を1レスポンスで返す。
     */
    public function show(MarketStatsService $service): JsonResponse
    {
        try {
            $stats = $service->getStats();
        } catch (\Throwable $e) {
            Log::error('market-stats api failed', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'temporarily_unavailable',
                'message' => '一時的にデータを取得できません。時間をおいて再度お試しください。',
            ], 503);
        }

        return response()->json([
            'period' => [
                'from' => $stats['from'] ?? null,
                'to' => $stats['to'] ?? null,
                'months' => MarketStatsService::MONTHS,
            ],
            'updated_at' => now()->toIso8601String(),
            'source' => 'MotoHub (motohub.jp)',
            'scope' => '中古市場全体・直近3ヶ月の成約',
            // 内部ID・スクレイパー由来情報は出さない（他APIと同方針）
            'regions' => collect($stats['regions'] ?? [])->values()->map(fn ($r, $i) => [
                'rank' => $i + 1,
                'prefecture' => $r->prefecture,
                'count' => (int) $r->sold_count,
            ])->all(),
            'years' => collect($stats['years'] ?? [])->map(fn ($r) => [
                'year' => (int) $r->model_year,
                'count' => (int) $r->cnt,
            ])->all(),
            'mileage_ranges' => collect($stats['mileageRanges'] ?? [])->map(fn ($r) => [
                'range' => $r->mileage_range,
                'count' => (int) $r->cnt,
            ])->all(),
        ]);
    }
}
