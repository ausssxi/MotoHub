<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\Category;
use Illuminate\Support\Collection;

final class CategoryRepository
{
    /**
     * 全カテゴリを表示順で取得
     */
    public function getAllSorted(): Collection
    {
        return Category::orderBy('sort_order', 'asc')->get();
    }

    /**
     * ★追加: 画像が設定されているカテゴリのみを表示順で取得
     * (icon_url または local_icon_path が存在するレコード)
     */
    public function getWithImagesSorted(): Collection
    {
        return Category::query()
            ->where(function ($query) {
                $query->whereNotNull('icon_url')
                      ->where('icon_url', '!=', '')
                      ->orWhere(function ($q) {
                          $q->whereNotNull('local_icon_path')
                            ->where('local_icon_path', '!=', '');
                      });
            })
            ->orderBy('sort_order', 'asc')
            ->get();
    }
}