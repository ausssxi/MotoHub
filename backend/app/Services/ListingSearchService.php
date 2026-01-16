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
     * 検索を実行し、結果とページネーション情報を返す
     */
    public function search(?string $keyword, ?string $prefecture = null, string $sort = 'latest', int $perPage = 30): array
    {
        $paginated = $this->repository->searchByKeyword($keyword, $prefecture, $sort, $perPage);

        $formattedItems = $paginated->getCollection()->map(fn($item) => [
            'id' => $item->id,
            'source_id' => strtolower($item->site?->name ?? 'other'),
            'source' => $item->site?->name ?? '不明',
            'source_domain' => $this->resolveSourceDomain(strtolower($item->site?->name ?? '')),
            'maker' => $item->bikeModel?->manufacturer?->name ?? '不明',
            'name' => $item->title ?? $item->bikeModel?->name ?? '車種名不明',
            'model_year' => $item->model_year ? "{$item->model_year}年" : '不明',
            'first_registration' => $item->first_registration ? "{$item->first_registration}年" : '不明',
            'mileage' => $item->mileage !== null ? number_format($item->mileage) . 'km' : '走行不明',
            'displacement' => $item->bikeModel?->displacement ? "{$item->bikeModel->displacement}cc" : '-',
            'repair_history' => $item->has_repair_history ? 'あり' : 'なし',
            'condition' => $item->is_new ? '新車' : '中古車',
            'total_price' => $item->total_price ? number_format((float)($item->total_price / 10000), 1) : '-',
            'base_price' => $item->price ? number_format((float)($item->price / 10000), 1) : '-',
            'store_name' => $item->shop?->name ?? '個人出品等',
            'url' => $item->source_url,
            'images' => $this->resolveImageUrls($item->local_image_paths),
        ])->toArray();

        return [
            'items' => $formattedItems,
            'pagination' => [
                'total'        => $paginated->total(),
                'current_page' => $paginated->currentPage(),
                'last_page'    => $paginated->lastPage(),
                'from'         => $paginated->firstItem(),
                'to'           => $paginated->lastItem(),
            ]
        ];
    }

    public function getActiveCount(): int
    {
        return $this->repository->countActiveListings();
    }

    private function resolveSourceDomain(string $sourceName): string
    {
        $domains = [
            'goobike' => 'goobike.com',
            'bds' => 'bds-bikesensor.net',
            'bikesensor' => 'bds-bikesensor.net'
        ];
        return $domains[$sourceName] ?? 'google.com';
    }

    private function resolveImageUrls(?array $paths): array
    {
        if (empty($paths)) return [];
        return array_map(function ($path) {
            return Storage::disk('public')->url(ltrim($path, '/'));
        }, $paths);
    }
}