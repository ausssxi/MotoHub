<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\BikeRepository;
use Illuminate\Database\Eloquent\Collection;

final class BikeService
{
    /**
     * @param BikeRepository $repository
     */
    public function __construct(
        private readonly BikeRepository $repository
    ) {}

    /**
     * トップページ用の人気車種データを取得
     *
     * @return Collection
     */
    public function getPopularBikesForTopPage(): Collection
    {
        // コンストラクタの引数名に合わせて $this->repository を使用
        return $this->repository->getTopBikesByCount();
    }
}