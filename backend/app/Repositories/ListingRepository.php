<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Listing;
use App\Models\Manufacturer;
use App\Models\BikeModel;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * バイクの出品情報に関するデータ操作を担当するリポジトリ
 */
final class ListingRepository
{
    /**
     * サイドバーのドロップダウン用：すべてのメーカーを取得（ID順）
     */
    public function getAllManufacturers(): Collection
    {
        return Manufacturer::orderBy('id', 'asc')->get();
    }

    /**
     * ドリルダウン用：特定のメーカーに紐づく車種一覧を取得
     */
    public function getModelsByManufacturer(int $manufacturerId): Collection
    {
        return BikeModel::where('manufacturer_id', $manufacturerId)
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * ページネーション付きの高度な検索
     */
    public function searchByKeyword(?string $keyword, ?string $prefecture, string $sort, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->baseSearchQuery($keyword, $prefecture, $filters);

        // 並び替えロジック（NULL値を除外してソートの精度を高める）
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
     * 現在の検索条件に基づいた「価格相場統計」を取得
     */
    public function getPriceStats(?string $keyword, ?string $prefecture, array $filters): object
    {
        $query = $this->baseSearchQuery($keyword, $prefecture, $filters);

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
     * 最小・最大統計値を取得（スライダーの初期境界値用）
     */
    public function getMinMaxStats(?string $keyword = null, ?string $prefecture = null): object
    {
        $query = Listing::query()
            ->where('is_sold_out', false)
            ->where('total_price', '<', 100000000)
            ->where('mileage', '<', 1000000)
            ->where('model_year', '<=', (int)date('Y') + 1);

        $this->applySearchFilters($query, $keyword, $prefecture);

        return $query->select([
                DB::raw('MAX(total_price) as max_price'),
                DB::raw('MAX(mileage) as max_mileage'),
                DB::raw('MIN(model_year) as min_year'),
                DB::raw('MAX(model_year) as max_year'),
            ])
            ->toBase()
            ->first();
    }

    /**
     * 有効な出品の総数を取得
     */
    public function countActiveListings(): int
    {
        return Listing::where('is_sold_out', false)->count();
    }

    /**
     * 検索の基本クエリ構築
     */
    private function baseSearchQuery(?string $keyword, ?string $prefecture = null, array $filters = []): Builder
    {
        $query = Listing::with(['bikeModel.manufacturer', 'shop', 'site'])
            ->where('is_sold_out', false);

        // 1. 基本フィルタ（キーワード、都道府県）
        $this->applySearchFilters($query, $keyword, $prefecture);

        // 2. ✨ 条件フィルタ修正：is_new カラムを condition カラムでの検索に書き換え
        if (isset($filters['is_new']) && $filters['is_new'] !== '') {
            // パラメータが '1' なら '新車'、'0' なら '中古車' として検索
            $conditionValue = ($filters['is_new'] === '1' || $filters['is_new'] === true) ? '新車' : '中古車';
            $query->where('condition', $conditionValue);
        }

        // 3. ✨ 追加フィルタ：修復歴（あり/なし）
        if (isset($filters['has_repair_history']) && $filters['has_repair_history'] !== '') {
            $query->where('has_repair_history', (bool)$filters['has_repair_history']);
        }

        // 4. ✨ 追加フィルタ：メーカー・車種ID
        if (!empty($filters['manufacturer_id'])) {
            $query->whereHas('bikeModel', function($q) use ($filters) {
                $q->where('manufacturer_id', $filters['manufacturer_id']);
            });
        }
        if (!empty($filters['bike_model_id'])) {
            $query->where('bike_model_id', $filters['bike_model_id']);
        }

        // 5. レンジフィルタ
        $this->applyRangeFilters($query, $filters, $keyword, $prefecture);

        return $query;
    }

    /**
     * 絞り込みロジックの共通化
     */
    private function applySearchFilters(Builder $query, ?string $keyword, ?string $prefecture): void
    {
        if ($keyword) {
            $query->where(function($lq) use ($keyword) {
                $lq->where('title', 'like', "%{$keyword}%")
                  ->orWhereHas('bikeModel', function($bq) use ($keyword) {
                      $bq->where('name', 'like', "%{$keyword}%")
                        ->orWhereHas('manufacturer', function($mq) use ($keyword) {
                            $mq->where('name', 'like', "%{$keyword}%");
                        });
                  });
            });
        }

        if ($prefecture) {
            $query->whereHas('shop', function($sq) use ($prefecture) {
                $sq->where('prefecture', 'like', "{$prefecture}%");
            });
        }
    }

    /**
     * 範囲指定フィルタの適用（UIのステップに合わせた端数処理）
     */
    private function applyRangeFilters(Builder $query, array $filters, ?string $keyword, ?string $prefecture): void
    {
        $stats = $this->getMinMaxStats($keyword, $prefecture);
        $uiMaxPrice = max(300, (int) ceil(($stats->max_price ?? 0) / 50000) * 5); 
        $uiMaxMileage = max(50000, (int) ceil(($stats->max_mileage ?? 0) / 1000) * 1000);

        if (!empty($filters['min_price']) && (int)$filters['min_price'] > 0) {
            $query->where('total_price', '>=', (int)$filters['min_price'] * 10000);
        }
        if (!empty($filters['max_price']) && (int)$filters['max_price'] < $uiMaxPrice) {
            $query->where('total_price', '<=', (int)$filters['max_price'] * 10000);
        }

        if (!empty($filters['min_mileage']) && (int)$filters['min_mileage'] > 0) {
            $query->where('mileage', '>=', (int)$filters['min_mileage']);
        }
        if (!empty($filters['max_mileage']) && (int)$filters['max_mileage'] < $uiMaxMileage) {
            $query->where('mileage', '<=', (int)$filters['max_mileage']);
        }

        if (!empty($filters['min_year'])) {
            $query->where('model_year', '>=', (int)$filters['min_year']);
        }
        if (!empty($filters['max_year'])) {
            $query->where('model_year', '<=', (int)$filters['max_year']);
        }
    }
}