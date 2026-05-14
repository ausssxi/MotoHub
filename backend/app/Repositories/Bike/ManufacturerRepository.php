<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\Manufacturer;
use Illuminate\Support\Collection;

/**
 * メーカー情報のデータ操作を担当
 */
final class ManufacturerRepository
{
    /**
     * すべてのメーカーをID順に取得
     */
    public function getAll(): Collection
    {
        return Manufacturer::orderBy('id', 'asc')->get();
    }

    /**
     * 名前順で取得
     */
    public function getAllSortedByName(): Collection
    {
        return Manufacturer::orderBy('name', 'asc')->get();
    }

    /**
     * 全メーカーをID順で取得
     * (ホンダ=1, ヤマハ=2... のようなマスタ順になります)
     */
    public function getAllSortedById(): Collection
    {
        return Manufacturer::orderBy('id', 'asc')->get();
    }
    
    /**
     * 在庫数付きメーカー一覧（在庫0除外、人気順）
     */
    public function getAllWithListingCount(): Collection
    {
        return Manufacturer::withCount(['listings' => fn ($q) => $q->where('is_sold_out', false)])
            ->having('listings_count', '>', 0)
            ->orderByDesc('listings_count')
            ->get();
    }

    /**
     * 指定された名前リストに一致するメーカーを取得
     * トップページのクイックリンク用
     */
    public function getByNames(array $names): Collection
    {
        return Manufacturer::whereIn('name', $names)->get();
    }
}