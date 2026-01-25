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
     * IDから特定の車種を取得する
     */
    public function find(int $id): ?BikeModel
    {
        return BikeModel::find($id);
    }

    /**
     * 特定のメーカーに紐づく車種一覧をID順で取得
     * ✨ 修正: listings_count (出品数) を取得するように変更
     */
    public function getByManufacturerId(int $manufacturerId): Collection
    {
        return BikeModel::where('manufacturer_id', $manufacturerId)
            // 販売中の車両（is_sold_out = false）の数をカウントして listings_count に入れる
            ->withCount(['listings' => fn($q) => $q->where('is_sold_out', false)])
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
            ->withCount(['listings' => fn($q) => $q->where('is_sold_out', false)])
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
            ->withCount(['listings' => fn($q) => $q->where('is_sold_out', false)])
            ->orderBy('listings_count', 'desc')
            ->limit($limit)
            ->get();
    }
}