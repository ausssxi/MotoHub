<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\Review;
use Illuminate\Support\Collection;

/**
 * レビューデータの操作を担当
 */
final class ReviewRepository
{
    /**
     * レビューを新規作成
     */
    public function create(array $data): Review
    {
        return Review::create($data);
    }

    /**
     * 最新のレビューを取得（車種情報付き）
     */
    public function getLatest(int $limit = 6): Collection
    {
        return Review::with(['bikeModel.manufacturer'])
            ->where('is_approved', true)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
}