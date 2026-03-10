<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\MarketPriceLog;
use App\Models\BikeModel;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

final class TrendService
{
    /**
     * 指定された日数の相場変動ランキングを取得する
     *
     * @param int $days 比較する過去の日数 (デフォルト30日)
     */
    public function getRanking(int $days = 30): array
    {
        return Cache::remember("bike_trends_{$days}", 3600, function () use ($days) {
            $latestDate = MarketPriceLog::max('recorded_at');
            if (!$latestDate) return ['drop' => [], 'rise' => []];

            // 1. 最新の相場データ (最低3台以上出品されている車種に限定してノイズを減らす)
            $latestLogs = MarketPriceLog::where('recorded_at', $latestDate)
                ->where('listing_count', '>=', 3)
                ->get()
                ->keyBy('bike_model_id');

            if ($latestLogs->isEmpty()) return ['drop' => [], 'rise' => []];

            // 2. 比較対象の過去データ
            // まず指定日数(30日前)の前後10日で探し、なければ最古のデータにフォールバック
            $pastDateTarget = Carbon::parse($latestDate)->subDays($days)->toDateString();
            $pastDateLimit = Carbon::parse($latestDate)->subDays($days + 10)->toDateString();

            $pastLogs = MarketPriceLog::where('recorded_at', '<=', $pastDateTarget)
                ->where('recorded_at', '>=', $pastDateLimit)
                ->orderBy('recorded_at', 'desc')
                ->get()
                ->unique('bike_model_id')
                ->keyBy('bike_model_id');

            // 過去データが見つからない場合、最古の記録日から比較（データ蓄積が浅い場合の救済）
            if ($pastLogs->isEmpty()) {
                $oldestDate = MarketPriceLog::where('recorded_at', '<', $latestDate)->min('recorded_at');
                if ($oldestDate) {
                    $pastLogs = MarketPriceLog::where('recorded_at', $oldestDate)
                        ->orderBy('recorded_at', 'desc')
                        ->get()
                        ->unique('bike_model_id')
                        ->keyBy('bike_model_id');
                    $pastDateTarget = $oldestDate;
                    $days = (int) Carbon::parse($oldestDate)->diffInDays(Carbon::parse($latestDate));
                }
            }

            $trends = [];
            $modelIds = $latestLogs->keys()->toArray();
            $models = BikeModel::with('manufacturer')->whereIn('id', $modelIds)->get()->keyBy('id');

            foreach ($latestLogs as $modelId => $latest) {
                if (!isset($pastLogs[$modelId]) || !isset($models[$modelId])) continue;
                
                $past = $pastLogs[$modelId];
                
                // 過去の平均価格が0の場合は比較できない
                if ($past->avg_price <= 0) continue;

                $diff = $latest->avg_price - $past->avg_price;
                $rate = ($diff / $past->avg_price) * 100;

                // 差額が1万円未満の微細な変動はノイズとして除外
                if (abs($diff) < 10000) continue;

                $trends[] = [
                    'model_id' => $modelId,
                    'model_name' => $models[$modelId]->name,
                    'maker_name' => $models[$modelId]->manufacturer->name ?? '不明',
                    'current_price' => round($latest->avg_price / 10000, 1),
                    'past_price' => round($past->avg_price / 10000, 1),
                    'diff' => round($diff / 10000, 1),
                    'rate' => round($rate, 1),
                    'count' => $latest->listing_count,
                    'image_url' => $models[$modelId]->image_url,
                ];
            }

            $collection = collect($trends);

            return [
                // 値下がりランキング（値下がり額が大きい順＝diffがマイナスで小さい順）
                'drop' => $collection->where('diff', '<', 0)->sortBy('diff')->take(30)->values()->all(),
                
                // 高騰ランキング（値上がり額が大きい順＝diffがプラスで大きい順）
                'rise' => $collection->where('diff', '>', 0)->sortByDesc('diff')->take(30)->values()->all(),
                
                // 分析期間の表示用
                'period' => [
                    'from' => Carbon::parse($pastDateTarget)->format('Y年m月d日'),
                    'to' => Carbon::parse($latestDate)->format('Y年m月d日'),
                    'days' => $days
                ]
            ];
        });
    }
}