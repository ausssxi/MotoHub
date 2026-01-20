<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Listing;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

/**
 * バイクの出品情報に関するデータ操作を担当するリポジトリ
 *
 * 検索・フィルタリング・ソートによる一覧取得のほか、
 * 価格相場や最小/最大値の統計取得、有効出品件数のカウントなどを行います。
 */
final class ListingRepository
{
    /**
     * ページネーション付き検索
     *
     * キーワード、都道府県、各種フィルター条件に基づいて出品情報を検索し、
     * 指定されたソート順で並び替えてページネーション付きの結果を返します。
     * 検索条件のクエリパラメータはページ送り時にも維持されます。
     *
     * @param string|null $keyword    検索キーワード（タイトル・車種名・メーカー名に部分一致）
     * @param string|null $prefecture 都道府県名（店舗の都道府県カラムで前方一致）
     * @param string      $sort       ソートキー（price_asc, price_desc, mileage_asc, year_desc など）
     * @param array       $filters    価格・走行距離・年式のフィルター条件
     * @param int         $perPage    1ページあたりの表示件数
     *
     * @return LengthAwarePaginator 検索結果のページネーター
     */
    public function searchByKeyword(?string $keyword, ?string $prefecture, string $sort, array $filters, int $perPage): LengthAwarePaginator
    {
        $query = $this->baseSearchQuery($keyword, $prefecture, $filters);

        $query = match ($sort) {
            'price_asc'   => $query->orderBy('total_price', 'asc'),
            'price_desc'  => $query->orderBy('total_price', 'desc'),
            'mileage_asc' => $query->orderBy('mileage', 'asc'),
            'year_desc'   => $query->orderBy('model_year', 'desc'),
            default       => $query->orderBy('created_at', 'desc'),
        };

        return $query->paginate($perPage)->withQueryString();
    }

    /**
     * 価格相場統計を取得
     *
     * 指定された検索条件に一致する出品情報について、価格の平均・最小・最大値、
     * および件数を集計して返します。異常値を除外するため、一定範囲外の価格は
     * 対象から外しています。
     *
     * @param string|null $keyword    検索キーワード
     * @param string|null $prefecture 都道府県名
     * @param array       $filters    価格・走行距離・年式のフィルター条件
     *
     * @return object 統計情報を持つオブジェクト（avg_price, min_price, max_price, count）
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
            ->where('total_price', '<', 10000000)
            ->first();
    }
    
    /**
     * 最小・最大統計値を取得
     *
     * 現在の検索条件に基づいて、価格・走行距離・年式それぞれの
     * 最小値および最大値を取得します。既知の異常値（極端に高い価格や距離）
     * は事前に除外した上で集計します。
     *
     * @param string|null $keyword    検索キーワード
     * @param string|null $prefecture 都道府県名
     *
     * @return object 統計情報を持つオブジェクト（max_price, max_mileage, min_year, max_year）
     */
    public function getMinMaxStats(?string $keyword = null, ?string $prefecture = null): object
    {
        $query = Listing::query()
            ->where('is_sold_out', false)
            ->where('total_price', '<', 10000000)
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
     * 有効な出品情報の総数を取得
     *
     * 売り切れでない出品レコードの総件数を返します。
     * サイト全体の掲載台数表示などに使用されます。
     *
     * @return int 有効な出品情報の総数
     */
    public function countActiveListings(): int
    {
        return Listing::where('is_sold_out', false)->count();
    }

    /**
     * 検索の基本クエリ構築（内部利用用）
     *
     * リレーション（車種・メーカー・店舗・サイト）を読み込んだ上で、
     * キーワード・都道府県・価格・走行距離・年式のフィルター条件を適用した
     * 共通のクエリビルダーを構築します。価格と走行距離の上限は、UIスライダーに
     * 合わせて 5万円単位・1,000km単位で切り上げて判定します。
     *
     * @param string|null $keyword    検索キーワード
     * @param string|null $prefecture 都道府県名
     * @param array       $filters    価格・走行距離・年式のフィルター条件
     *
     * @return Builder 検索条件が適用されたクエリビルダー
     */
    private function baseSearchQuery(?string $keyword, ?string $prefecture = null, array $filters = []): Builder
    {
        $query = Listing::with(['bikeModel.manufacturer', 'shop', 'site'])
            ->where('is_sold_out', false);

        $this->applySearchFilters($query, $keyword, $prefecture);

        // メタ統計の取得
        $stats = $this->getMinMaxStats($keyword, $prefecture);
        
        // --- 端数処理（切り上げ）の適用 ---
        // 価格は5万円単位、走行距離は1,000km単位でスライダーが動くため、その単位で切り上げる
        $uiMaxPrice = max(300, (int) ceil(($stats->max_price ?? 0) / 50000) * 5); 
        $uiMaxMileage = max(50000, (int) ceil(($stats->max_mileage ?? 0) / 1000) * 1000);

        if (!empty($filters['min_price']) && (int)$filters['min_price'] > 0) {
            $query->where('total_price', '>=', (int)$filters['min_price'] * 10000);
        }
        // 上限判定：切り上げた最大値より小さい場合のみフィルタを適用
        if (!empty($filters['max_price']) && (int)$filters['max_price'] < $uiMaxPrice) {
            $query->where('total_price', '<=', (int)$filters['max_price'] * 10000);
        }

        if (!empty($filters['min_mileage']) && (int)$filters['min_mileage'] > 0) {
            $query->where('mileage', '>=', (int)$filters['min_mileage']);
        }
        // 上限判定：切り上げた最大値より小さい場合のみフィルタを適用
        if (!empty($filters['max_mileage']) && (int)$filters['max_mileage'] < $uiMaxMileage) {
            $query->where('mileage', '<=', (int)$filters['max_mileage']);
        }

        if (!empty($filters['min_year'])) {
            $query->where('model_year', '>=', (int)$filters['min_year']);
        }
        if (!empty($filters['max_year'])) {
            $query->where('model_year', '<=', (int)$filters['max_year']);
        }

        return $query;
    }

    /**
     * 絞り込みロジックの共通化
     *
     * キーワードはタイトル・車種名・メーカー名に対して部分一致検索を行い、
     * 都道府県は店舗テーブルの都道府県カラムを前方一致で絞り込みます。
     *
     * @param Builder     $query      絞り込み対象のクエリビルダー
     * @param string|null $keyword    検索キーワード
     * @param string|null $prefecture 都道府県名
     *
     * @return void
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
}