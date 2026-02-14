<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\SavedSearch;

/**
 * 検索条件保存に関するデータ操作を担当
 */
final class SavedSearchRepository
{
    /**
     * 指定したユーザーの保存済み検索条件数を取得
     */
    public function countByUser(int $userId): int
    {
        return SavedSearch::where('user_id', $userId)->count();
    }

    /**
     * 検索条件を新規作成
     */
    public function create(array $data): SavedSearch
    {
        return SavedSearch::create($data);
    }
}