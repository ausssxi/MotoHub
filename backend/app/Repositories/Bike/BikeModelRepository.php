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
     * 特定のメーカーに紐づく車種一覧をID順で取得
     */
    public function getByManufacturerId(int $manufacturerId): Collection
    {
        return BikeModel::where('manufacturer_id', $manufacturerId)
            // ★変更: Listingモデルの active() スコープを利用して一元管理
            ->withCount(['listings' => fn($q) => $q->active()])
            ->orderBy('id', 'asc')
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
            // ★変更: active() スコープを利用
            ->withCount(['listings' => fn($q) => $q->active()])
            ->orderBy('listings_count', 'desc')
            ->limit($limit)
            ->get();
    }
}