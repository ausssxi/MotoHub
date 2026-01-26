<?php

declare(strict_types=1);

namespace App\Repositories\Shop;

use App\Models\Shop;

final class ShopRepository
{
    /**
     * IDで店舗を取得
     */
    public function find(int $id): ?Shop
    {
        return Shop::find($id);
    }
}