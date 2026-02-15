<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\BikeModelMarketStat;
use App\Models\MarketPriceLog;
use Illuminate\Database\Eloquent\Collection;

/**
 * 市場統計データ(BikeModelMarketStat)および
 * 価格履歴ログ(MarketPriceLog)のデータ操作を担当
 */
final class MarketStatsRepository
{
    /**
     * 車種の最新統計データを取得
     */
    public function getLatestStat(int $bikeModelId): ?BikeModelMarketStat
    {
        return BikeModelMarketStat::where('bike_model_id', $bikeModelId)->first();
    }

    /**
     * 指定期間の価格推移ログを取得
     * デフォルトは過去1年分
     */
    public function getLogs(int $bikeModelId, int $months = 12): Collection
    {
        return MarketPriceLog::where('bike_model_id', $bikeModelId)
            ->where('recorded_at', '>=', now()->subMonths($months))
            ->orderBy('recorded_at', 'asc')
            ->get();
    }

    /**
     * 最新の価格ログを1件取得（データ不足時のデモ生成用など）
     */
    public function getLatestLog(int $bikeModelId): ?MarketPriceLog
    {
        return MarketPriceLog::where('bike_model_id', $bikeModelId)
            ->latest('recorded_at')
            ->first();
    }
}