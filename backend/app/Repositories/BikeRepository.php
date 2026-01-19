<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\BikeModel;
use App\Models\Manufacturer;
use Illuminate\Database\Eloquent\Collection;

/**
 * バイク車種情報のデータアクセスを担当するリポジトリ
 * 
 * バイク車種の検索、ランキング取得、メーカー別グループ化などの
 * データベース操作を提供します。
 */
final class BikeRepository
{
    /**
     * 出品数でソートされた人気のバイク車種を取得
     * 
     * 設定ファイルで除外指定された車種名を除き、出品数の多い順に
     * バイク車種を取得します。制限数が指定されない場合は設定値を使用します。
     * 
     * @param int|null $limit 取得する件数（指定がない場合は設定値を使用）
     * @return Collection 出品数でソートされたバイク車種のコレクション
     */
    public function getTopBikesByCount(?int $limit = null): Collection
    {
        // Get values from config/bike.php
        $limit = $limit ?? config('bike.ranking.top_page_limit', 16);
        $excludedNames = config('bike.ranking.excluded_names', ['他車種']);

        return BikeModel::query()
            // Exclude specific model names based on config
            ->whereNotIn('name', $excludedNames)
            ->withCount('listings')
            // Sort by the aggregated listings_count
            ->orderBy('listings_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * サジェスト用に名前で検索
     * 
     * キーワードに部分一致するバイク車種名を検索し、有効な出品数の多い順に
     * ソートして返します。検索候補の表示に使用されます。
     * 
     * @param string $keyword 検索キーワード
     * @param int $limit 取得する件数（デフォルト: 10）
     * @return Collection キーワードに一致するバイク車種のコレクション
     */
    public function searchNamesByKeyword(string $keyword, int $limit = 10): Collection
    {
        return BikeModel::query()
            ->where('name', 'like', "%{$keyword}%")
            ->withCount(['listings' => fn($q) => $q->where('is_sold_out', false)])
            ->orderBy('listings_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 全車種をメーカーごとに取得する
     * 
     * 全メーカーをID昇順で取得し、各メーカーに紐づく車種を
     * 出品数の多い順にソートして返します。車種一覧ページの表示に使用されます。
     * 
     * @return Collection メーカーごとにグループ化された車種情報のコレクション
     */
    public function getAllModelsGroupedByManufacturer(): Collection
    {
        return Manufacturer::query()
            ->with(['bikeModels' => function($query) {
                $query->withCount('listings')->orderBy('listings_count', 'desc');
            }])
            ->withCount('bikeModels')
            ->orderBy('id', 'asc') // 名前順(name)からID順に変更
            ->get();
    }

    /**
     * 全車種の総数を取得
     * 
     * データベースに登録されている全バイク車種の件数を返します。
     * 
     * @return int 全車種の総数
     */
    public function countAllModels(): int
    {
        return BikeModel::count();
    }
}