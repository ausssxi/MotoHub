<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Listing;
use App\Models\MarketPriceLog;
use App\Repositories\Bike\ListingStatsRepository;
use App\Services\Bike\TrendService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * 中古バイク相場ハブ（/market・恒久slug）。TheftController の型を踏襲した「データハブ」型。
 * ★値上がり/値下がりの集計は App\Services\Bike\TrendService::getRanking() をそのまま使う
 *   （TrendService は変更しない）。相場ログは market_price_logs（自社集計）。
 *   全体サマリ（掲載中台数・平均価格）と集計期間は DB 実測から算出し、market_hub_summary_v1
 *   で1時間キャッシュ（数値はハードコードせず、データ蓄積とともに精度が上がる）。
 */
final class MarketController extends Controller
{
    public function __construct(
        private readonly TrendService $trendService,
        private readonly ListingStatsRepository $statsRepo,
    ) {}

    public function show(Request $request): View
    {
        // ?days= は 30 / 90 / max の3値のみ受理。未指定・不正値は 30 にフォールバック。
        $daysParam = $request->query('days');
        $daysParam = is_string($daysParam) ? $daysParam : '';
        $days = in_array($daysParam, ['30', '90', 'max'], true) ? $daysParam : '30';

        // 全体サマリ（掲載中台数・平均価格）と集計期間（DB実測）を1時間キャッシュ。
        // ★キャッシュキー: market_hub_summary_v1（本番で cache:clear は使わない運用のため、
        //   無効化は Cache::forget('market_hub_summary_v1') か短TTL自然失効で行う）。
        ['summary' => $summary, 'period' => $period] = Cache::remember('market_hub_summary_v1', 3600, function () {
            $oldest = MarketPriceLog::min('recorded_at');
            $latest = MarketPriceLog::max('recorded_at');

            // 記録日数（最古〜最新の差）。TrendService の period.days と同じ diffInDays 基準。
            $recordDays = ($oldest && $latest)
                ? (int) Carbon::parse($oldest)->diffInDays(Carbon::parse($latest))
                : 0;

            return [
                // 掲載中台数=ListingStatsRepository::countActiveListings()（既存定義・Listing::active()=is_sold_out:false）。
                // 平均価格も同一の active() スコープの avg（新条件は発明しない）。
                'summary' => [
                    'stock' => $this->statsRepo->countActiveListings(),
                    'avg_price' => (int) round((float) Listing::active()->avg('total_price')),
                ],
                'period' => [
                    'from' => $oldest,   // 最古 recorded_at（date 文字列 / データ0件なら null）
                    'to' => $latest,     // 最新 recorded_at
                    'days' => $recordDays,
                    'model_count' => (int) MarketPriceLog::distinct('bike_model_id')->count('bike_model_id'),
                ],
            ];
        });

        // getRanking に渡す日数。max は 最新-最古 の記録日数（0なら30に救済）。
        $rankDays = match ($days) {
            '90' => 90,
            'max' => max(1, (int) ($period['days'] ?: 30)),
            default => 30,
        };

        // 値上がり/値下がり集計（TrendService はそのまま利用）。データ0件でも空配列で落ちない。
        $ranking = $this->trendService->getRanking($rankDays);
        $risers = array_slice($ranking['rise'] ?? [], 0, 10);
        $fallers = array_slice($ranking['drop'] ?? [], 0, 10);

        return view('market', [
            'summary' => $summary,
            'period' => $period,
            'risers' => $risers,
            'fallers' => $fallers,
            'days' => $days,
        ]);
    }
}
