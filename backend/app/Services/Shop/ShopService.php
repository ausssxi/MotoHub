<?php

declare(strict_types=1);

namespace App\Services\Shop;

use App\Models\Shop;
use App\Models\Listing;
use App\Models\Manufacturer;
use App\Repositories\Shop\ShopRepository;
use App\Repositories\Bike\ListingRepository;
use App\Services\Bike\Search\PaginationFormatter;
use App\Http\Resources\Bike\ListingResource;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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
        $pagination = $this->paginator->format($paginated);

        // ★追加: PaginationFormatterがtotalを落としてしまう問題の対策
        // ページネーターが total() メソッドを持っていれば、正確な「全在庫数」を強制上書きする
        if (method_exists($paginated, 'total')) {
            $pagination['total'] = $paginated->total();
        } elseif (!isset($pagination['total']) || $pagination['total'] === 0) {
            // simplePaginate等の場合は、とりあえず今取得できている件数を入れる
            $pagination['total'] = $paginated->count();
        }

        // 取扱メーカー集計（在庫がある車両のメーカーを台数順で取得）
        $manufacturers = Listing::where('shop_id', $shopId)
            ->where('is_sold_out', false)
            ->join('bike_models', 'listings.bike_model_id', '=', 'bike_models.id')
            ->join('manufacturers', 'bike_models.manufacturer_id', '=', 'manufacturers.id')
            ->select('manufacturers.id', 'manufacturers.name', 'manufacturers.slug', DB::raw('COUNT(*) as stock_count'))
            ->groupBy('manufacturers.id', 'manufacturers.name', 'manufacturers.slug')
            ->orderByDesc('stock_count')
            ->get();

        return [
            'shop' => $shop,
            'items' => ListingResource::collection($paginated->getCollection())->resolve(),
            'pagination' => $pagination,
            'manufacturers' => $manufacturers,
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