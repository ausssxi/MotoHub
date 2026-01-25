<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\Listing;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
/**
 * バイクの出品情報に関する検索・統計操作を担当
 */
final class ListingRepository
{
    /**
     * メイン検索
     */
    public function searchByKeyword(?string $keyword, ?string $prefecture, string $sort, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->buildFilteredQuery($keyword, $prefecture, $filters);

        // ソート適用（現状のロジック維持）
        $query = match ($sort) {
            'price_asc'    => $query->whereNotNull('total_price')->orderBy('total_price', 'asc'),
            'price_desc'   => $query->whereNotNull('total_price')->orderBy('total_price', 'desc'),
            'mileage_asc'  => $query->whereNotNull('mileage')->orderBy('mileage', 'asc'),
            'mileage_desc' => $query->whereNotNull('mileage')->orderBy('mileage', 'desc'),
            'year_desc'    => $query->orderBy('model_year', 'desc'),
            'year_asc'     => $query->orderBy('model_year', 'asc'),
            default        => $query->orderBy('created_at', 'desc'),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * 相場統計の取得
     */
    public function getPriceStats(?string $keyword, ?string $prefecture, array $filters): object
    {
        return $this->buildFilteredQuery($keyword, $prefecture, $filters)
            ->select([
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
     * スライダーの境界値を計算
     * ✨ 修正：現在の「価格」「走行距離」などの範囲指定は絶対に含めない
     */
    public function getMinMaxStats(?string $keyword = null, ?string $prefecture = null, array $filters = []): object
    {
        // 1. 真っさらなクエリを作成
        $query = Listing::query()->active();

        // 2. 「構造的」な条件のみを適用（これらはスライダーの「器」を決める）
        if (!empty($filters['bike_model_id'])) {
            $query->where('bike_model_id', (int)$filters['bike_model_id']);
        } elseif (!empty($filters['manufacturer_id'])) {
            $query->whereHas('bikeModel', fn($q) => $q->where('manufacturer_id', (int)$filters['manufacturer_id']));
        }

        // 3. 地域条件
        if ($prefecture || !empty($filters['prefecture'])) {
            $pref = $prefecture ?: ($filters['prefecture'] ?? null);
            $query->whereHas('shop', fn($sq) => $sq->where('prefecture', 'like', "{$pref}%"));
        }

        // 4. キーワード条件（ID指定がない場合のみ反映）
        if (empty($filters['bike_model_id']) && empty($filters['manufacturer_id']) && $keyword) {
            $query->where('title', 'like', "%{$keyword}%");
        }

        // ❌ 重要：ここで $filters['max_price'] などは絶対に適用しない！
        // これを適用してしまうと、カブ(35万)からPCX(65万)へ切り替えた時に 0件ヒット(NULL)になる。

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
     * 全有効台数
     */
    public function countActiveListings(): int
    {
        return Listing::active()->count();
    }

    /**
     * 共通のフィルタリングクエリを構築
     * スコープをチェーンすることで、以前の if 文の塊を解消しました。
     */
    private function buildFilteredQuery(?string $keyword, ?string $prefecture, array $filters): Builder
    {
        $meta = $this->getMinMaxStats($keyword, $prefecture, $filters);
        $uiMaxPrice = max(300, (int) ceil(($meta->max_price ?? 0) / 50000) * 5); 
        $uiMaxMileage = max(50000, (int) ceil(($meta->max_mileage ?? 0) / 1000) * 1000);

        return Listing::query()
            ->with(['bikeModel.manufacturer', 'shop', 'site'])
            ->active()
            // Model Scope を活用
            ->withKeyword($keyword, !empty($filters['bike_model_id']))
            ->byPrefecture($prefecture ?: ($filters['prefecture'] ?? null))
            ->byModel($filters['manufacturer_id'] ?? null, $filters['bike_model_id'] ?? null)
            ->byCondition($filters['is_new'] ?? null)
            ->byRepairHistory($filters['has_repair_history'] ?? null)
            ->priceBetween($filters['min_price'] ?? null, $filters['max_price'] ?? null, $uiMaxPrice)
            ->mileageBetween($filters['min_mileage'] ?? null, $filters['max_mileage'] ?? null, $uiMaxMileage)
            ->yearBetween($filters['min_year'] ?? null, $filters['max_year'] ?? null);
    }

    /**
     * 車種ごとの有効な支払総額リストを取得
     * * @param int $bikeModelId
     * @return Collection<int>
     */
    public function getValidTotalPricesByModelId(int $bikeModelId): Collection
    {
        return Listing::where('bike_model_id', $bikeModelId)
            ->where('is_sold_out', false)
            ->whereNotNull('total_price')
            ->where('total_price', '>', 0)
            ->pluck('total_price') // 価格の配列のみ取得
            ->map(fn($p) => (int)$p);
    }
}