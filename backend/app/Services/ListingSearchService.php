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
            // システム用の識別キー（小文字・トリム済み）
            'source_id' => strtolower(trim($item->site?->name ?? 'other')),
            // 画面表示用のサイト名
            'source' => $this->resolveSourceDisplayName($item->site?->name ?? ''),
            // ファビコン取得用のドメイン
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

    /**
     * 表示用のサイト名を解決する
     * データベースの値が 'WEBIKE', 'webike', 'Webike ' などであっても正規化して判定します
     */
    private function resolveSourceDisplayName(string $sourceName): string
    {
        $normalized = strtolower(trim($sourceName));

        return match ($normalized) {
            'goobike'    => 'グーバイク',
            'bds', 'bikesensor' => 'BDSバイクセンサー',
            'webike'     => 'Webike', // 大文字小文字を正しく指定
            default      => $sourceName ?: '不明',
        };
    }

    /**
     * ファビコン用のドメインを解決する
     */
    private function resolveSourceDomain(string $sourceName): string
    {
        $domains = [
            'goobike'    => 'goobike.com',
            'bds'        => 'bds-bikesensor.net',
            'bikesensor' => 'bds-bikesensor.net',
            'webike'     => 'www.webike.net' // wwwありの方がアイコン取得率が高いため修正
        ];
        return $domains[$sourceName] ?? 'google.com';
    }

    /**
     * ローカルパスを公開URLに変換する
     */
    private function resolveImageUrls(?array $paths): array
    {
        if (empty($paths)) return [];
        return array_map(function ($path) {
            return Storage::disk('public')->url(ltrim($path, '/'));
        }, $paths);
    }
}