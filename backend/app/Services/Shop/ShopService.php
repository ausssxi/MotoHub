<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\Shop;
use App\Repositories\Shop\ShopRepository;
use App\Repositories\Bike\ListingRepository;
use App\Services\Bike\Search\PaginationFormatter;
use App\Http\Resources\Bike\ListingResource;
use Illuminate\Database\Eloquent\Collection;

/**
 * 店舗関連のビジネスロジック
 */
final class ShopService
{
    public function __construct(
        private readonly ShopRepository $shopRepo,
        private readonly ListingRepository $listingRepo,
        private readonly PaginationFormatter $paginator
    ) {}

    /**
     * 店舗詳細ページ用のデータを取得
     */
    public function getShopDetailWithListings(int $shopId): array
    {
        $shop = $this->shopRepo->findOrFail($shopId);

        $paginated = $this->listingRepo->getByShopId($shopId, 20);

        return [
            'shop' => $shop,
            'items' => ListingResource::collection($paginated->getCollection())->resolve(),
            'pagination' => $this->paginator->format($paginated),
        ];
    }

    /**
     * 地図エリア検索用の店舗データを取得
     */
    public function getShopsInArea(array $coords): Collection
    {
        return $this->shopRepo->findInBounds(
            (float)$coords['sw_lat'],
            (float)$coords['sw_lng'],
            (float)$coords['ne_lat'],
            (float)$coords['ne_lng']
        );
    }
}