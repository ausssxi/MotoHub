<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Listing;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;

/**
 * バイクの出品情報に関するデータ操作を担当するリポジトリ
 * 
 * 出品情報の検索、フィルタリング、ソート、統計情報の取得などの
 * データベース操作を提供します。
 */
final class ListingRepository
{
    /**
     * 検索、フィルタリング、および並び替えを実行
     * 
     * キーワード、都道府県、各種フィルター条件に基づいて出品情報を検索し、
     * 指定されたソート順で並び替えてページネーション付きで返します。
     * 
     * @param string|null $keyword 検索キーワード（車種名、メーカー名、タイトルに部分一致）
     * @param string|null $prefecture 都道府県名（店舗の住所で絞り込み）
     * @param string $sort ソート順（latest, price_asc, price_desc, mileage_asc, mileage_desc, year_asc, year_desc）
     * @param array $filters フィルター条件（min_price, max_price, min_mileage, max_mileage, min_year, max_year）
     * @param int $perPage 1ページあたりの表示件数（デフォルト: 30）
     * @return LengthAwarePaginator ページネーション付きの検索結果
     * @throws \Exception 検索処理中にエラーが発生した場合
     */
    public function searchByKeyword(
        ?string $keyword, 
        ?string $prefecture = null, 
        string $sort = 'latest', 
        array $filters = [], 
        int $perPage = 30
    ): LengthAwarePaginator {
        try {
            $query = $this->baseSearchQuery($keyword, $prefecture, $filters);

            switch ($sort) {
                case 'price_asc':
                    $query->orderByRaw('total_price IS NULL ASC, total_price ASC');
                    break;
                case 'price_desc':
                    $query->orderByRaw('total_price IS NULL DESC, total_price DESC');
                    break;
                case 'mileage_asc':
                    $query->orderByRaw('mileage IS NULL ASC, mileage ASC');
                    break;
                case 'mileage_desc':
                    $query->orderByRaw('mileage IS NULL DESC, mileage DESC');
                    break;
                case 'year_desc':
                    $query->orderBy('model_year', 'desc');
                    break;
                case 'year_asc':
                    $query->orderBy('model_year', 'asc');
                    break;
                default:
                    $query->orderBy('created_at', 'desc');
                    break;
            }

            return $query->paginate($perPage)->withQueryString();

        } catch (\Exception $e) {
            Log::error("Search Error: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * 現在の検索条件に基づいた最小・最大統計値を取得
     * 
     * 指定されたキーワードと都道府県の条件に基づいて、価格、走行距離、年式の
     * 最小値・最大値を取得します。既知の異常値（マジックナンバー）を除外して
     * 正確な統計値を算出します。
     * 
     * @param string|null $keyword 検索キーワード（指定された場合はその条件で絞り込み）
     * @param string|null $prefecture 都道府県名（指定された場合はその条件で絞り込み）
     * @return object 統計情報を含むオブジェクト（max_price, max_mileage, min_year, max_year）
     */
    public function getMinMaxStats(?string $keyword = null, ?string $prefecture = null): object
    {
        $query = Listing::query()
            ->where('is_sold_out', false)
            // 既知のマジックナンバー（ASK=998万, 不明=99万km）と未来の年式を排除
            ->where('total_price', '<', 9000000)
            ->where('mileage', '<', 900000)
            ->where('model_year', '<=', (int)date('Y') + 1);

        // 検索条件を適用
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
     * 売り切れでない出品情報の総件数を返します。
     * 
     * @return int 有効な出品情報の総数
     */
    public function countActiveListings(): int
    {
        return Listing::where('is_sold_out', false)->count();
    }

    /**
     * 検索の基本クエリ構築
     * 
     * キーワード、都道府県、各種フィルター条件を適用した
     * 検索クエリビルダーを構築します。リレーション（車種、メーカー、店舗）も
     * 事前に読み込みます。
     * 
     * @param string|null $keyword 検索キーワード
     * @param string|null $prefecture 都道府県名
     * @param array $filters フィルター条件（min_price, max_price, min_mileage, max_mileage, min_year, max_year）
     * @return Builder 検索条件が適用されたクエリビルダー
     */
    private function baseSearchQuery(?string $keyword, ?string $prefecture = null, array $filters = [])
    {
        $query = Listing::with(['bikeModel.manufacturer', 'shop'])
            ->where('is_sold_out', false);

        // キーワードと地域条件を適用
        $this->applySearchFilters($query, $keyword, $prefecture);

        // スライダーの上限判定用のメタ統計（この条件での最大値）
        $stats = $this->getMinMaxStats($keyword, $prefecture);
        $uiMaxPrice = max(300, (int) ceil(($stats->max_price ?? 0) / 10000));
        $uiMaxMileage = max(50000, (int) ($stats->max_mileage ?? 0));

        // 価格フィルタ
        if (!empty($filters['min_price']) && (int)$filters['min_price'] > 0) {
            $query->where('total_price', '>=', (int)$filters['min_price'] * 10000);
        }
        if (!empty($filters['max_price'])) {
            // 上限なし（右端）でない場合のみ、絞り込みを適用
            if ((int)$filters['max_price'] < $uiMaxPrice) {
                $query->where('total_price', '<=', (int)$filters['max_price'] * 10000);
            }
        }

        // 走行距離フィルタ
        if (!empty($filters['min_mileage']) && (int)$filters['min_mileage'] > 0) {
            $query->where('mileage', '>=', (int)$filters['min_mileage']);
        }
        if (!empty($filters['max_mileage'])) {
            if ((int)$filters['max_mileage'] < $uiMaxMileage) {
                $query->where('mileage', '<=', (int)$filters['max_mileage']);
            }
        }

        // 年式
        if (!empty($filters['min_year'])) {
            $query->where('model_year', '>=', (int)$filters['min_year']);
        }
        if (!empty($filters['max_year'])) {
            $query->where('model_year', '<=', (int)$filters['max_year']);
        }

        return $query;
    }

    /**
     * キーワードと地域による絞り込みロジックを共通化
     * 
     * キーワード検索はタイトル、車種名、メーカー名に対して部分一致検索を実行します。
     * 都道府県による絞り込みは店舗の住所で前方一致検索を実行します。
     * 
     * @param Builder $query クエリビルダー
     * @param string|null $keyword 検索キーワード
     * @param string|null $prefecture 都道府県名
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
                $sq->where('address', 'like', "{$prefecture}%");
            });
        }
    }
}