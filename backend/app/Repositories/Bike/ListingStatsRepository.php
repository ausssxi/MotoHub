<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\Listing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

/**
 * バイク出品情報の「統計・集計」操作を担当
 * （Meilisearchの速度を落とさないよう、重いMySQLクエリを極限まで排除した爆速版）
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
     * ★爆速化チューニング：広範な条件での重い集計をスキップし、車種指定時のみ計算する
     */
    public function getPriceStats(?string $keyword, ?string $prefecture, array $filters): object
    {
        // 「ホンダ全体」や「キーワード検索のみ」のような広範な条件で平均価格を計算しても、
        // ユーザーにとって意味が薄く、かつMySQLのフルスキャンが発生し致命的な遅延を招きます。
        // そのため、「特定の車種（bike_model_id）」が選ばれている時だけ相場を計算します。
        
        if (empty($filters['bike_model_id'])) {
            return (object)[
                'avg_price' => 0,
                'min_price' => 0,
                'max_price' => 0,
                'count'     => 0,
            ];
        }

        // 車種が特定されていれば（対象が数百件程度に絞られるため）、MySQLでも一瞬(0.01秒以下)で計算可能です。
        $query = Listing::query()->active()
            ->where('bike_model_id', (int)$filters['bike_model_id']);

        if ($prefecture || !empty($filters['prefecture'])) {
             $query->byPrefecture($prefecture ?: ($filters['prefecture'] ?? null));
        }
        if (!empty($filters['is_new'])) {
            $query->byCondition($filters['is_new']);
        }
        if (!empty($filters['has_repair_history'])) {
            $query->byRepairHistory($filters['has_repair_history']);
        }

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
     * スライダーの境界値（全体像）を取得
     * ★爆速化チューニング：動的計算を廃止し、固定のスケール幅を返す
     */
    public function getMinMaxStats(?string $keyword = null, ?string $prefecture = null, array $filters = []): object
    {
        // 以前は検索条件が変わるたびにMySQLの全データをスキャンしてMAX/MINを計算していましたが、
        // これが 0.5秒以上の遅延を引き起こす最大の原因でした。
        // スライダーの目盛り幅は、条件によってコロコロ変わるよりも固定幅の方がUX的にも優れているため、
        // ここでは即座に固定のスケールを返し、DBへの負荷を「ゼロ」にします。

        return (object)[
            'max_price'   => 5000000,    // 価格スライダーの最大値: 500万円
            'max_mileage' => 100000,     // 走行距離スライダーの最大値: 10万km
            'min_year'    => 1990,       // 年式スライダーの最小値: 1990年
            'max_year'    => (int)date('Y'), // 年式スライダーの最大値: 今年
        ];
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