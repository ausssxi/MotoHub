<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BikeModel;
use App\Services\Bike\ModelStatsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * 外部パートナー向け 車種別 市場データAPI（第1段階・APIキー認証＋throttle）。
 * 認証/レート制限は rankings 系と同じ v1 グループ（api.key + throttle:rankings-api）。
 *
 * 集計は車種分析ページ(/ranking/model/{id})と同じ ModelStatsService を流用＝数字が一致。
 * キャッシュも同サービス内の model_stats_ranking_v6_{id}（1週間）に相乗り（二重キャッシュしない）。
 */
final class ModelStatsApiController extends Controller
{
    /**
     * GET /api/v1/models/{modelId}/stats
     * 車種別の市場データ（売れている地域 / 走行距離帯 / 年式 / 価格帯 / サマリー / 6ヶ月販売推移）を1本で返す。
     */
    public function show(int $modelId, ModelStatsService $service): JsonResponse
    {
        $bikeModel = BikeModel::with('manufacturer')->find($modelId);

        if (! $bikeModel) {
            return response()->json([
                'error' => 'model_not_found',
                'message' => '指定された車種が見つかりません。',
            ], 404);
        }

        try {
            $stats = $service->getStats($modelId); // ページと同一キャッシュに相乗り
        } catch (\Throwable $e) {
            Log::error('model-stats api failed', ['model_id' => $modelId, 'error' => $e->getMessage()]);

            return response()->json([
                'error' => 'temporarily_unavailable',
                'message' => '一時的にデータを取得できません。時間をおいて再度お試しください。',
            ], 503);
        }

        $now = now();

        return response()->json([
            'model' => $bikeModel->displayLabel(),
            'maker' => $bikeModel->manufacturer?->name ?? '',
            'period' => [
                'from' => $now->copy()->subMonths(ModelStatsService::MONTHS)->toDateString(),
                'to' => $now->toDateString(),
                'months' => ModelStatsService::MONTHS,
            ],
            'updated_at' => $now->toIso8601String(),
            'source' => 'MotoHub (motohub.jp)',
            'summary' => [
                'last_month_sold' => (int) ($stats['lastMonthSold'] ?? 0),
                'market_rank' => $stats['rank'] !== null ? (int) $stats['rank'] : null,
                'market_total' => (int) ($stats['totalModels'] ?? 0),
                'avg_stock_days' => (int) ($stats['avgDays'] ?? 0),
            ],
            // 内部ID・スクレイパー由来情報は出さない（listings / price-trends APIと同方針）
            'regions' => collect($stats['regionRanking'] ?? [])->values()->map(fn ($r, $i) => [
                'rank' => $i + 1,
                'prefecture' => $r->prefecture,
                'count' => (int) $r->cnt,
            ])->all(),
            'mileage_ranges' => collect($stats['mileageRanges'] ?? [])->map(fn ($r) => [
                'range' => $r->mileage_range,
                'count' => (int) $r->cnt,
            ])->all(),
            'years' => collect($stats['yearRanking'] ?? [])->map(fn ($r) => [
                'year' => (int) $r->model_year,
                'count' => (int) $r->cnt,
            ])->all(),
            'price_ranges' => collect($stats['priceRanges'] ?? [])->map(fn ($r) => [
                'range' => $r->price_range,
                'count' => (int) $r->cnt,
            ])->all(),
            'monthly_sales' => collect($stats['monthlySales'] ?? [])->map(fn ($m) => [
                'month' => $this->jpYearMonthToIso((string) ($m['month'] ?? '')),
                'count' => (int) ($m['count'] ?? 0),
            ])->all(),
        ]);
    }

    /**
     * 'Y年n月'（例: 2026年1月）を ISO の 'Y-m'（2026-01）へ変換。APIは機械可読な表記で返す。
     * 変換できない場合はそのまま返す（フォールバック）。
     */
    private function jpYearMonthToIso(string $jp): string
    {
        if (preg_match('/(\d{4})年(\d{1,2})月/u', $jp, $m)) {
            return sprintf('%04d-%02d', (int) $m[1], (int) $m[2]);
        }

        return $jp;
    }
}
