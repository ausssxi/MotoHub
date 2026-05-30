<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\BikeModel;
use Illuminate\Support\Collection;

/**
 * 車種マスタ情報のデータ操作を担当
 */
final class BikeModelRepository
{
    /**
     * IDで車種を検索（メーカー情報付き）
     */
    public function findWithManufacturer(int $id): ?BikeModel
    {
        return BikeModel::with('manufacturer')->find($id);
    }
    
    /**
     * IDから特定の車種を取得する
     */
    public function find(int $id): ?BikeModel
    {
        return BikeModel::find($id);
    }

    /**
     * IDから詳細情報（メーカー・カテゴリ付き）を取得する
     * 見つからない場合は例外を投げる
     */
    public function findDetailOrFail(int $id): BikeModel
    {
        return BikeModel::with(['manufacturer', 'categoryData'])->findOrFail($id);
    }

    /**
     * 特定のメーカーに紐づく車種一覧を掲載台数（active）の多い順で取得
     * 人気車種が上に来るようにし、同数は名前順で安定させる。
     */
    public function getByManufacturerId(int $manufacturerId): Collection
    {
        return BikeModel::where('manufacturer_id', $manufacturerId)
            ->with('representativeListing')
            ->withCount(['listings' => fn($q) => $q->active()])
            ->orderByDesc('listings_count')
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * キーワードで車種名を検索（サジェストや推論用）
     */
    public function searchByName(string $keyword, int $limit = 10): Collection
    {
        return BikeModel::query()
            ->where('name', 'like', "%{$keyword}%")
            // ★変更: active() スコープを利用
            ->withCount(['listings' => fn($q) => $q->active()])
            ->orderBy('listings_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 登録台数が多い順に車種を取得（トップページ等）
     */
    public function getTopModels(int $limit = 16): Collection
    {
        return BikeModel::query()
            ->with(['manufacturer', 'representativeListing'])
            ->withCount(['listings' => fn($q) => $q->active()])
            ->orderBy('listings_count', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * 急上昇トレンド（閲覧数＋お気に入り数に基づく熱量スコア順）車種一覧ページで使用
     */
    public function getTrendingModels(int $limit = 10): Collection
    {
        return BikeModel::query()
            ->with('representativeListing')
            ->withCount(['listings' => fn($q) => $q->active()])
            // その車種に属する有効な車両の「本日の閲覧数」の合計を取得
            ->withSum(['listings as today_views' => fn($q) => $q->active()], 'view_count_today')
            // その車種に属する有効な車両の「お気に入り数」の合計を取得
            ->withSum(['listings as total_favorites' => fn($q) => $q->active()], 'favorite_count')
            // 【熱量スコアの計算】 (今日の閲覧数 × 1) + (お気に入り数 × 5) でソート
            ->orderByRaw('(COALESCE(today_views, 0) + (COALESCE(total_favorites, 0) * 5)) DESC')
            // スコアが同じ場合は、在庫の選択肢が多い（掲載台数が多い）ものを優先
            ->orderBy('listings_count', 'desc')
            ->limit($limit)
            ->get();
    }
}