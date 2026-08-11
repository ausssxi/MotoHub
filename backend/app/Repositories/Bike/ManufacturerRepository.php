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
     * 全メーカーを掲載台数（active）の多い順で取得（コンボボックス用）
     * 0台メーカーも検索で選べるよう残し、台数→名前の順で並べる。
     */
    public function getAllSortedByListingCount(): Collection
    {
        return Manufacturer::withCount(['listings' => fn ($q) => $q->active()])
            ->orderByDesc('listings_count')
            ->orderBy('name', 'asc')
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