<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\BikeModel;
use App\Models\BikeModelMarketStat;
use App\Models\Listing;
use App\Models\MarketPriceLog;
use Illuminate\Support\Facades\DB;

final class ModelContentDataService
{
    /**
     * 指定されたbike_model_idに対して、コンテンツ生成用のデータを収集する
     */
    public function collect(int $bikeModelId): array
    {
        $model = BikeModel::with(['manufacturer', 'categoryData'])->findOrFail($bikeModelId);

        $activeCount = Listing::where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', false)
            ->count();

        $soldCount = Listing::where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', true)
            ->count();

        $priceStats = $this->getPriceStats($bikeModelId);
        $mileageStats = $this->getMileageStats($bikeModelId);
        $avgDaysToSell = $this->getAvgDaysToSell($bikeModelId);
        $topPrefectures = $this->getTopPrefectures($bikeModelId);
        $yearStats = $this->getYearStats($bikeModelId);
        $priceTrend = $this->getPriceTrend($bikeModelId);
        $engagementStats = $this->getEngagementStats($bikeModelId);
        $sameClassModels = $this->getSameClassModels($model);

        return [
            'model_name' => $model->name,
            'manufacturer_name' => $model->manufacturer->name,
            'displacement' => $model->displacement,
            'category_name' => $model->categoryData?->name,
            'active_count' => $activeCount,
            'sold_count' => $soldCount,
            'avg_price' => $priceStats['avg'],
            'min_price' => $priceStats['min'],
            'max_price' => $priceStats['max'],
            'avg_mileage' => $mileageStats,
            'avg_days_to_sell' => $avgDaysToSell,
            'top_prefectures' => $topPrefectures,
            'model_year_range' => $yearStats['range'],
            'most_common_year' => $yearStats['most_common'],
            'price_trend' => $priceTrend,
            'favorite_avg' => $engagementStats['favorite_avg'],
            'view_count_avg' => $engagementStats['view_count_avg'],
            'same_class_models' => $sameClassModels,
        ];
    }

    /**
     * bike_model_market_statsから価格統計を取得、なければlistingsから直接計算
     */
    private function getPriceStats(int $bikeModelId): array
    {
        $stat = BikeModelMarketStat::where('bike_model_id', $bikeModelId)->first();

        if ($stat && $stat->listing_count > 0) {
            return [
                'avg' => round((float) $stat->avg_price / 10000, 1),
                'min' => round((float) $stat->min_price / 10000, 1),
                'max' => round((float) $stat->max_price / 10000, 1),
            ];
        }

        // フォールバック: listingsから直接計算
        $result = Listing::where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', false)
            ->whereNotNull('total_price')
            ->where('total_price', '>', 10000)
            ->selectRaw('AVG(total_price) as avg_price, MIN(total_price) as min_price, MAX(total_price) as max_price')
            ->first();

        return [
            'avg' => $result?->avg_price ? round((float) $result->avg_price / 10000, 1) : null,
            'min' => $result?->min_price ? round((float) $result->min_price / 10000, 1) : null,
            'max' => $result?->max_price ? round((float) $result->max_price / 10000, 1) : null,
        ];
    }

    /**
     * 平均走行距離（km）を取得
     */
    private function getMileageStats(int $bikeModelId): ?int
    {
        $avg = Listing::where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', false)
            ->whereNotNull('mileage')
            ->where('mileage', '>', 0)
            ->avg('mileage');

        return $avg ? (int) round((float) $avg) : null;
    }

    /**
     * 売れるまでの平均日数
     */
    private function getAvgDaysToSell(int $bikeModelId): ?int
    {
        $avg = Listing::where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', true)
            ->whereRaw('DATEDIFF(updated_at, created_at) BETWEEN 1 AND 365')
            ->selectRaw('ROUND(AVG(DATEDIFF(updated_at, created_at))) as avg_days')
            ->value('avg_days');

        return $avg ? (int) $avg : null;
    }

    /**
     * 販売台数上位3都道府県
     */
    private function getTopPrefectures(int $bikeModelId): array
    {
        return Listing::where('listings.bike_model_id', $bikeModelId)
            ->where('listings.is_sold_out', false)
            ->join('shops', 'listings.shop_id', '=', 'shops.id')
            ->whereNotNull('shops.prefecture')
            ->select('shops.prefecture', DB::raw('COUNT(*) as cnt'))
            ->groupBy('shops.prefecture')
            ->orderByDesc('cnt')
            ->limit(3)
            ->pluck('prefecture')
            ->toArray();
    }

    /**
     * 年式の分布（範囲と最頻値）
     */
    private function getYearStats(int $bikeModelId): array
    {
        $query = Listing::where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', false)
            ->whereNotNull('model_year')
            ->where('model_year', '>', 1970);

        $minYear = (clone $query)->min('model_year');
        $maxYear = (clone $query)->max('model_year');

        // 最頻値（MODE）
        $mostCommon = (clone $query)
            ->select('model_year', DB::raw('COUNT(*) as cnt'))
            ->groupBy('model_year')
            ->orderByDesc('cnt')
            ->value('model_year');

        $range = null;
        if ($minYear && $maxYear) {
            $range = $minYear === $maxYear
                ? (string) $minYear
                : "{$minYear}〜{$maxYear}";
        }

        return [
            'range' => $range,
            'most_common' => $mostCommon ? (int) $mostCommon : null,
        ];
    }

    /**
     * 直近3ヶ月の価格トレンド判定
     */
    private function getPriceTrend(int $bikeModelId): ?string
    {
        $logs = MarketPriceLog::where('bike_model_id', $bikeModelId)
            ->orderByDesc('recorded_at')
            ->limit(3)
            ->get();

        if ($logs->count() < 2) {
            return null;
        }

        $latest = $logs->first()->avg_price;
        $oldest = $logs->last()->avg_price;

        if ($oldest <= 0) {
            return null;
        }

        $changeRate = (($latest - $oldest) / $oldest) * 100;

        if ($changeRate > 5) {
            return '上昇';
        }
        if ($changeRate < -5) {
            return '下落';
        }

        return '横ばい';
    }

    /**
     * エンゲージメント統計（お気に入り平均、閲覧数平均）
     */
    private function getEngagementStats(int $bikeModelId): array
    {
        $result = Listing::where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', false)
            ->selectRaw('ROUND(AVG(favorite_count)) as fav_avg, ROUND(AVG(view_count_total)) as view_avg')
            ->first();

        return [
            'favorite_avg' => $result?->fav_avg ? (int) $result->fav_avg : 0,
            'view_count_avg' => $result?->view_avg ? (int) $result->view_avg : 0,
        ];
    }

    /**
     * 同じカテゴリ・排気量帯の車種TOP3（自分自身除外）
     */
    private function getSameClassModels(BikeModel $model): array
    {
        $query = BikeModel::where('id', '!=', $model->id)
            ->where('category_id', $model->category_id);

        if ($model->displacement) {
            $query->whereBetween('displacement', [
                $model->displacement - 50,
                $model->displacement + 50,
            ]);
        }

        return $query
            ->withCount(['listings' => fn ($q) => $q->where('is_sold_out', false)])
            ->orderByDesc('listings_count')
            ->limit(3)
            ->pluck('name')
            ->toArray();
    }
}
