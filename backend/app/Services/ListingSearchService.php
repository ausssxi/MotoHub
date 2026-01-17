<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ListingRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;

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
            'source_id' => strtolower(trim($item->site?->name ?? 'other')),
            'source' => $this->resolveSourceDisplayName($item->site?->name ?? ''),
            'source_domain' => $this->resolveSourceDomain(strtolower(trim($item->site?->name ?? ''))),
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
            'pagination' => $this->formatPagination($paginated)
        ];
    }

    public function getActiveCount(): int
    {
        return $this->repository->countActiveListings();
    }

    /**
     * ページネーション情報をUI向けに整形
     */
    private function formatPagination(LengthAwarePaginator $paginated): array
    {
        $currentPage = $paginated->currentPage();
        $lastPage = $paginated->lastPage();
        
        // 【修正】現在のページの左右に表示する数を 2 から 1 に変更（計3ページ分にする）
        $range = 1; 

        $pages = [];
        
        // 常に1ページ目を追加
        $pages[] = $this->makePageItem(1, $paginated);

        // 前方の「...」判定
        if ($currentPage - $range > 2) {
            $pages[] = ['is_dot' => true];
        }

        // 中間のページ番号
        for ($i = max(2, $currentPage - $range); $i <= min($lastPage - 1, $currentPage + $range); $i++) {
            $pages[] = $this->makePageItem($i, $paginated);
        }

        // 後方の「...」判定
        if ($currentPage + $range < $lastPage - 1) {
            $pages[] = ['is_dot' => true];
        }

        // 最後のページを追加
        if ($lastPage > 1) {
            $pages[] = $this->makePageItem($lastPage, $paginated);
        }

        return [
            'total'        => $paginated->total(),
            'current_page' => $currentPage,
            'last_page'    => $lastPage,
            'from'         => $paginated->firstItem(),
            'to'           => $paginated->lastItem(),
            'prev_url'     => $paginated->previousPageUrl(),
            'next_url'     => $paginated->nextPageUrl(),
            'pages'        => $pages,
            'display_text' => "全 {$lastPage} ページ中 {$currentPage} ページ"
        ];
    }

    private function makePageItem(int $page, LengthAwarePaginator $paginated): array
    {
        return [
            'label'     => $page,
            'url'       => $paginated->url($page),
            'is_active' => $page === $paginated->currentPage(),
            'is_dot'    => false,
        ];
    }

    private function resolveSourceDisplayName(string $sourceName): string
    {
        $normalized = strtolower(trim($sourceName));
        return match ($normalized) {
            'goobike'           => 'グーバイク DD',
            'bds', 'bikesensor' => 'BDSバイクセンサー DD',
            'webike'            => 'Webike DD',
            default             => ($sourceName ?: '不明') . ' DD',
        };
    }

    private function resolveSourceDomain(string $sourceName): string
    {
        $domains = [
            'goobike'    => 'goobike.com',
            'bds'        => 'bds-bikesensor.net',
            'bikesensor' => 'bds-bikesensor.net',
            'webike'     => 'www.webike.net'
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