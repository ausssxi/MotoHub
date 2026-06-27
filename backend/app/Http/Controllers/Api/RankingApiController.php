<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Bike\ClassRankingService;
use App\Services\Bike\TrendService;
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

    private const TREND_DAYS = 30;     // 相場推移の比較日数（/trends ページと同一）

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

    /**
     * GET /api/v1/rankings/price-trends?direction={down|up}
     * 相場推移ランキング（値下がり=down / 高騰=up）。集計は /trends と同じ TrendService を流用し、
     * 数字・並び順を一致させる。並びは TrendService 既定の「変化額(diff)基準」
     * （down=値下がり額が大きい順 / up=高騰額が大きい順。rate順ではない）。
     * 比較は「最新の日次平均価格 vs 約30日前の日次平均価格」。
     */
    public function priceTrends(Request $request, TrendService $service): JsonResponse
    {
        $direction = strtolower(trim((string) $request->query('direction', '')));
        $map = ['down' => 'drop', 'up' => 'rise']; // API値 → TrendService戻り値キー

        if (! isset($map[$direction])) {
            return response()->json([
                'error' => 'invalid_direction',
                'message' => 'direction は down（値下がり）/ up（高騰）のいずれかを指定してください。',
                'allowed' => array_keys($map),
            ], 422);
        }

        try {
            // TrendService 内の bike_trends_{days} キャッシュ(1時間)にそのまま乗る（API側で二重キャッシュしない）
            $trends = $service->getRanking(self::TREND_DAYS);
        } catch (\Throwable $e) {
            Log::error('price-trends api failed', ['direction' => $direction, 'error' => $e->getMessage()]);

            return response()->json([
                'error' => 'temporarily_unavailable',
                'message' => '一時的にデータを取得できません。時間をおいて再度お試しください。',
            ], 503);
        }

        $rows = $trends[$map[$direction]] ?? [];
        $period = $trends['period'] ?? ['from' => null, 'to' => null, 'days' => self::TREND_DAYS];

        // 公開してよい項目のみへ整形（内部ID model_id / UI用 image_url は出さない＝listings APIと同一方針）
        $rankings = [];
        foreach (array_values($rows) as $i => $row) {
            $rankings[] = [
                'rank' => $i + 1,
                'model' => $row['model_name'] ?? '',
                'maker' => $row['maker_name'] ?? '',
                'current_price_man' => $row['current_price'] ?? null,
                'past_price_man' => $row['past_price'] ?? null,
                'diff_man' => $row['diff'] ?? null,
                'rate_pct' => $row['rate'] ?? null,
                'count' => $row['count'] ?? null,
            ];
        }

        return response()->json([
            'direction' => $direction,
            'period' => [
                'from' => $this->toIsoDate((string) ($period['from'] ?? '')),
                'to' => $this->toIsoDate((string) ($period['to'] ?? '')),
                'days' => $period['days'] ?? self::TREND_DAYS,
            ],
            'updated_at' => now()->toIso8601String(),
            'source' => 'MotoHub (motohub.jp)',
            'count' => count($rankings),
            'rankings' => $rankings,
        ]);
    }

    /**
     * TrendService の表示用日付 'Y年m月d日' を ISO(Y-m-d) へ変換（APIは機械可読なISOで返す）。
     * 変換できない形式はそのまま返す（フォールバック）。
     */
    private function toIsoDate(string $jp): string
    {
        if (preg_match('/(\d{4})年(\d{2})月(\d{2})日/u', $jp, $m)) {
            return "{$m[1]}-{$m[2]}-{$m[3]}";
        }

        return $jp;
    }
}
