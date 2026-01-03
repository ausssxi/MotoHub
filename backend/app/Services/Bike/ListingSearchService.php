<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Repositories\Bike\ListingRepository;

/**
 * バイク出品情報の検索ロジックを担当
 */
final class ListingSearchService
{
    /**
     * @param ListingRepository $repository
     */
    public function __construct(
        private readonly ListingRepository $repository
    ) {}

    /**
     * 検索を実行し、View用の配列形式に変換
     *
     * @param string $keyword
     * @return array
     */
    public function search(string $keyword): array
    {
        $listings = $this->repository->searchByKeyword($keyword);

        return $listings->map(fn($item) => [
            'id' => $item->id,
            'source_id' => strtolower($item->site->name ?? 'other'),
            'source' => $item->site->name ?? '不明',
            'maker' => $item->bikeModel->manufacturer->name ?? '不明',
            'name' => $item->title ?? $item->bikeModel->name,
            'year' => $item->model_year ? "{$item->model_year}年" : '年式不明',
            'mileage' => $item->mileage ? number_format($item->mileage) . 'km' : '走行不明',
            'displacement' => $item->bikeModel->displacement ? "{$item->bikeModel->displacement}cc" : '-',
            'total_price' => $item->total_price ? number_format($item->total_price / 10000, 1) : '-',
            'base_price' => $item->price ? number_format($item->price / 10000, 1) : '-',
            'store_name' => $item->shop->name ?? '個人出品等',
            'store_address' => $item->shop->prefecture ?? '',
            'url' => $item->source_url,
            'images' => $item->image_urls,
        ])->toArray();
    }
}