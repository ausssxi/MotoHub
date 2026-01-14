<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ListingRepository;
use Illuminate\Support\Facades\Storage;

/**
 * バイク出品情報の検索ロジックを担当
 */
final class ListingSearchService
{
    public function __construct(
        private readonly ListingRepository $repository
    ) {}

    /**
     * 検索を実行し、結果と総件数を返す
     */
    public function search(string $keyword): array
    {
        $listings = $this->repository->searchByKeyword($keyword);
        $totalCount = $this->repository->countByKeyword($keyword);

        $formattedItems = $listings->map(fn($item) => [
            'id' => $item->id,
            'source_id' => strtolower($item->site->name ?? 'other'),
            'source' => $item->site->name ?? '不明',
            // 修正：リレーションが null の場合に備えて null安全演算子 (?->) を使用
            'maker' => $item->bikeModel?->manufacturer?->name ?? '不明',
            'name' => $item->title ?? $item->bikeModel?->name ?? '車種名不明',
            'year' => $item->model_year ? "{$item->model_year}年" : '年式不明',
            'mileage' => $item->mileage ? number_format($item->mileage) . 'km' : '走行不明',
            'displacement' => $item->bikeModel?->displacement ? "{$item->bikeModel->displacement}cc" : '-',
            'total_price' => $item->total_price ? number_format((float)($item->total_price / 10000), 1) : '-',
            'base_price' => $item->price ? number_format((float)($item->price / 10000), 1) : '-',
            'store_name' => $item->shop->name ?? '個人出品等',
            'store_address' => $item->shop->prefecture ?? '',
            'url' => $item->source_url,
            'images' => $this->resolveImageUrls($item->local_image_paths),
        ])->toArray();

        return [
            'items' => $formattedItems,
            'total' => $totalCount
        ];
    }

    public function getActiveCount(): int
    {
        return $this->repository->countActiveListings();
    }

    private function resolveImageUrls(?array $paths): array
    {
        if (empty($paths)) return [];
        return array_map(function ($path) {
            return Storage::disk('public')->url(ltrim($path, '/'));
        }, $paths);
    }
}