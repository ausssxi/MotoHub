<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\Listing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * バイク出品情報の「統計・集計」操作を担当
 */
final class ListingStatsRepository
{
    /**
     * 全有効台数
     */
    public function countActiveListings(): int
    {
        return Listing::active()->count();
    }

    /**
     * 相場統計の取得
     */
    public function getPriceStats(?string $keyword, ?string $prefecture, array $filters): object
    {
        // 検索条件を適用するためのクエリビルダは ListingRepository のロジックと似ていますが、
        // 統計用として簡易的に構築するか、スコープを活用します。
        // ここではモデルのスコープを直接チェーンして構築します。
        $query = Listing::query()->active()
            ->withKeyword($keyword, !empty($filters['bike_model_id']))
            ->byPrefecture($prefecture ?: ($filters['prefecture'] ?? null))
            ->byModel($filters['manufacturer_id'] ?? null, $filters['bike_model_id'] ?? null)
            ->byCondition($filters['is_new'] ?? null)
            ->byRepairHistory($filters['has_repair_history'] ?? null);

        // 範囲指定も適用（現在の絞り込み結果に対する統計なので）
        $query->priceBetween($filters['min_price'] ?? null, $filters['max_price'] ?? null)
              ->mileageBetween($filters['min_mileage'] ?? null, $filters['max_mileage'] ?? null)
              ->yearBetween($filters['min_year'] ?? null, $filters['max_year'] ?? null);

        return $query->select([
                DB::raw('AVG(total_price) as avg_price'),
                DB::raw('MIN(total_price) as min_price'),
                DB::raw('MAX(total_price) as max_price'),
                DB::raw('COUNT(*) as count')
            ])
            ->where('total_price', '>', 10000)
            ->where('total_price', '<', 50000000)
            ->first();
    }

    /**
     * スライダーの境界値（全体像）を計算
     * ※現在の範囲フィルタは適用しない
     */
    public function getMinMaxStats(?string $keyword = null, ?string $prefecture = null, array $filters = []): object
    {
        $query = Listing::query()->active();

        // 構造的な条件のみ適用
        if (!empty($filters['bike_model_id'])) {
            $query->where('bike_model_id', (int)$filters['bike_model_id']);
        } elseif (!empty($filters['manufacturer_id'])) {
            $query->whereHas('bikeModel', fn($q) => $q->where('manufacturer_id', (int)$filters['manufacturer_id']));
        }

        if ($prefecture || !empty($filters['prefecture'])) {
            $pref = $prefecture ?: ($filters['prefecture'] ?? null);
            $query->whereHas('shop', fn($sq) => $sq->where('prefecture', 'like', "{$pref}%"));
        }

        if (empty($filters['bike_model_id']) && empty($filters['manufacturer_id']) && $keyword) {
            $query->where('title', 'like', "%{$keyword}%");
        }

        return $query->select([
                DB::raw('MAX(total_price) as max_price'),
                DB::raw('MAX(mileage) as max_mileage'),
                DB::raw('MIN(model_year) as min_year'),
                DB::raw('MAX(model_year) as max_year'),
            ])
            ->where('total_price', '>', 10000)
            ->where('total_price', '<', 50000000)
            ->toBase()
            ->first();
    }

    /**
     * 車種ごとの有効な支払総額リストを取得
     */
    public function getValidTotalPricesByModelId(int $bikeModelId): Collection
    {
        return Listing::where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', false)
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->pluck('total_price')
            ->map(fn($p) => (int)$p);
    }
}