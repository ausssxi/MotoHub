<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\ListingRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * バイク出品情報の検索ロジックを担当し、UI向けのデータ整形を行うサービス
 * 
 * リポジトリ層から取得した出品データを、検索・フィルタリング・並び替えしつつ
 * 画面表示やAPIレスポンスで利用しやすい形に変換します。
 */
final class ListingSearchService
{
    /**
     * @param ListingRepository $repository 出品情報のデータアクセスを担当するリポジトリ
     */
    public function __construct(
        private readonly ListingRepository $repository
    ) {}

    /**
     * フィルター条件を含めて検索を実行し、結果を整形して返す
     * 
     * キーワード、都道府県、各種フィルター条件に基づいて出品情報を検索し、
     * ページネーション情報と合わせて、UI表示向けの配列形式に整形して返します。
     * 
     * @param string|null $keyword 検索キーワード（タイトル・車種名・メーカー名に部分一致）
     * @param string|null $prefecture 都道府県名（店舗住所の前方一致で絞り込み）
     * @param string $sort ソート順（latest, price_asc, price_desc, mileage_asc, mileage_desc, year_asc, year_desc）
     * @param array $filters フィルター条件（min_price, max_price, min_mileage, max_mileage, min_year, max_year）
     * @param int $perPage 1ページあたりの件数
     * @return array 検索結果とページネーション情報（'items', 'pagination' キーを含む）
     */
    public function search(?string $keyword, ?string $prefecture = null, string $sort = 'latest', array $filters = [], int $perPage = 30): array
    {
        $paginated = $this->repository->searchByKeyword($keyword, $prefecture, $sort, $filters, $perPage);

        $formattedItems = $paginated->getCollection()->map(fn($item) => [
            'id' => $item->id,
            'source' => $this->resolveSourceDisplayName($item->site?->name ?? ''),
            'source_domain' => $this->resolveSourceDomain($item->site?->name ?? ''),
            'maker' => $item->bikeModel?->manufacturer?->name ?? '不明',
            'name' => $item->title ?? $item->bikeModel?->name ?? '車種名不明',
            'model_year' => $item->model_year ? "{$item->model_year}年" : '不明',
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

    /**
     * 現在のフィルター条件下での合計ヒット件数を取得
     * 
     * 検索結果の実データは不要で件数のみ知りたい場合に使用します。
     * モバイル向けの条件変更時の件数プレビューなどで利用されます。
     * 
     * @param string|null $keyword 検索キーワード
     * @param string|null $prefecture 都道府県名
     * @param array $filters フィルター条件
     * @return int 条件に一致する出品件数
     */
    public function getFilteredCount(?string $keyword, ?string $prefecture, array $filters): int
    {
        $paginated = $this->repository->searchByKeyword($keyword, $prefecture, 'latest', $filters, 1);
        return (int) $paginated->total();
    }

    /**
     * 絞り込みスライダーの境界値（最小・最大）を取得
     * 
     * 指定されたキーワード・都道府県条件に基づいて、価格・走行距離・年式の
     * 最小値および最大値を計算し、UIスライダーで使用する範囲情報として返します。
     * 価格と走行距離については、最低値（300万円 / 50,000km）を保証します。
     * 
     * @param string|null $keyword 検索キーワード
     * @param string|null $prefecture 都道府県名
     * @return array スライダー用メタデータ（'price', 'mileage', 'year' の各範囲情報）
     */
    public function getSearchMetadata(?string $keyword = null, ?string $prefecture = null): array
    {
        // 検索条件に一致する範囲内での統計を取得
        $stats = $this->repository->getMinMaxStats($keyword, $prefecture);

        // 異常値チェック後の数値を使用
        $dbMaxPrice = (int) ceil(($stats->max_price ?? 0) / 10000);
        $dbMaxMileage = (int) ($stats->max_mileage ?? 0);

        return [
            'price' => [
                'min' => 0,
                // 指定されたキーワードでの最高価格に基づき、最低300万円を保証
                'max' => max(300, $dbMaxPrice),
            ],
            'mileage' => [
                'min' => 0,
                // 指定されたキーワードでの最高距離に基づき、最低50,000kmを保証
                'max' => max(50000, $dbMaxMileage),
            ],
            'year' => [
                'min' => (int) ($stats->min_year ?? 1990),
                'max' => (int) ($stats->max_year ?? (int) date('Y')),
            ]
        ];
    }

    /**
     * 有効な出品の総数を取得
     * 
     * 売り切れでない全ての出品情報の件数を返します。
     * サイト全体の掲載台数表示に使用されます。
     * 
     * @return int 有効な出品の総数
     */
    public function getActiveCount(): int
    {
        return $this->repository->countActiveListings();
    }

    /**
     * ページネーション情報をUI向けに整形
     * 
     * Laravelのページネータから、現在ページ・総ページ数・前後ページURL・
     * 中央のページリンク（省略記号含む）などを抽出し、ビューで扱いやすい
     * 配列形式に変換します。
     * 
     * @param LengthAwarePaginator $paginated 検索結果のページネータ
     * @return array ページネーション情報の配列
     */
    private function formatPagination(LengthAwarePaginator $paginated): array
    {
        $currentPage = $paginated->currentPage();
        $lastPage = $paginated->lastPage();
        $range = 1; 
        $pages = [];
        
        $pages[] = $this->makePageItem(1, $paginated);
        if ($currentPage - $range > 2) {
            $pages[] = ['is_dot' => true];
        }
        for ($i = max(2, $currentPage - $range); $i <= min($lastPage - 1, $currentPage + $range); $i++) {
            $pages[] = $this->makePageItem($i, $paginated);
        }
        if ($currentPage + $range < $lastPage - 1) {
            $pages[] = ['is_dot' => true];
        }
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

    /**
     * ページ番号リンク1件分の情報を作成
     * 
     * ラベル、URL、アクティブ状態などを含むページリンク要素を生成します。
     * 
     * @param int $page ページ番号
     * @param LengthAwarePaginator $paginated ページネータ
     * @return array 単一ページリンクの情報
     */
    private function makePageItem(int $page, LengthAwarePaginator $paginated): array
    {
        return [
            'label'     => $page,
            'url'       => $paginated->url($page),
            'is_active' => $page === $paginated->currentPage(),
            'is_dot'    => false,
        ];
    }

    /**
     * 出品元サイト名を表示用の名称に変換
     * 
     * 内部的なサイト識別名を、日本語を含むユーザー向けの表示名へ変換します。
     * 対応する名称がない場合は元の名称、もしくは「不明」を返します。
     * 
     * @param string $sourceName 内部的なサイト名
     * @return string 表示用サイト名
     */
    private function resolveSourceDisplayName(string $sourceName): string
    {
        $normalized = strtolower(trim($sourceName));
        return match ($normalized) {
            'goobike'           => 'グーバイク',
            'bds', 'bikesensor' => 'BDSバイクセンサー',
            'webike'            => 'Webike',
            default             => ($sourceName ?: '不明'),
        };
    }

    /**
     * 出品元サイト名からドメインを解決
     * 
     * サイト識別名に対応する公式ドメインを返します。
     * 対応表に存在しない場合はデフォルトで google.com を返します。
     * 
     * @param string $sourceName 内部的なサイト名
     * @return string サイトのドメイン名
     */
    private function resolveSourceDomain(string $sourceName): string
    {
        $normalized = strtolower(trim($sourceName));
        $domains = [
            'goobike'    => 'goobike.com',
            'bds'        => 'bds-bikesensor.net',
            'bikesensor' => 'bds-bikesensor.net',
            'webike'     => 'www.webike.net'
        ];
        return $domains[$normalized] ?? 'google.com';
    }

    /**
     * ストレージ上の画像パス配列をURL配列に変換
     * 
     * publicディスク上に保存された画像パスの配列から、アクセス可能なURLの配列を生成します。
     * パス配列が空またはnullの場合は空配列を返します。
     * 
     * @param array<string>|null $paths ストレージ上の画像パス配列
     * @return array 画像URLの配列
     */
    private function resolveImageUrls(?array $paths): array
    {
        if (empty($paths)) return [];
        return array_map(function ($path) {
            return Storage::disk('public')->url(ltrim($path, '/'));
        }, $paths);
    }
}