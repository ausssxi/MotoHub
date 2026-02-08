<?php

declare(strict_types=1);

namespace App\Repositories\Bike;

use App\Models\Review;

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
}