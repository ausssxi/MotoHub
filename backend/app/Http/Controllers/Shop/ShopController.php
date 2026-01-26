<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Repositories\Shop\ShopRepository;
use App\Repositories\Bike\ListingRepository;
use App\Services\Bike\Search\PaginationFormatter;
use App\Http\Resources\Bike\ListingResource;
use Illuminate\Contracts\View\View;

final class ShopController extends Controller
{
    public function __construct(
        private readonly ShopRepository $shopRepo,
        private readonly ListingRepository $listingRepo,
        private readonly PaginationFormatter $paginator
    ) {}

    /**
     * 店舗詳細・在庫一覧ページ
     */
    public function show(int $id): View
    {
        $shop = $this->shopRepo->find($id);

        if (!$shop) {
            abort(404, '指定された店舗は見つかりませんでした。');
        }

        // 店舗の在庫を取得
        $paginated = $this->listingRepo->getByShopId($id);

        return view('shops.show', [
            'shop' => $shop,
            'items' => ListingResource::collection($paginated->getCollection())->resolve(),
            'pagination' => $this->paginator->format($paginated),
        ]);
    }
}