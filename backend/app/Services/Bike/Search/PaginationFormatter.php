<?php

declare(strict_types=1);

namespace App\Services\Bike\Search;

use Illuminate\Contracts\Pagination\Paginator; // ★ インターフェースに変更

/**
 * ページネーションの表示用データを整形するクラス
 * simplePaginate (Paginator) と paginate (LengthAwarePaginator) 両方に対応
 */
final class PaginationFormatter
{
    public function format(Paginator $paginated): array
    {
        // ★修正ポイント：現在の検索条件（keyword=TW など）を次ページのURLに完全に引き継ぐ
        if (method_exists($paginated, 'withQueryString')) {
            $paginated->withQueryString();
        } elseif (method_exists($paginated, 'appends')) {
            $paginated->appends(request()->query());
        }

        $currentPage = $paginated->currentPage();
        
        // simplePaginate の場合は lastPage() が存在しないため、存在チェックを行う
        $lastPage = method_exists($paginated, 'lastPage') ? $paginated->lastPage() : $currentPage;
        
        // 総件数も同様にチェック（Service側で後ほど上書き補完されます）
        $total = method_exists($paginated, 'total') ? $paginated->total() : 0;

        $pages = [];
        
        // 1ページ目の項目作成
        $pages[] = $this->makePageItem(1, $paginated);
        
        // 中間の「...」とページ番号のロジック
        if ($currentPage - 1 > 2) {
            $pages[] = ['is_dot' => true, 'label' => '...', 'url' => null, 'is_active' => false];
        }
        
        for ($i = max(2, $currentPage - 1); $i <= min($lastPage - 1, $currentPage + 1); $i++) {
            $pages[] = $this->makePageItem($i, $paginated);
        }
        
        if ($currentPage + 1 < $lastPage - 1) {
            $pages[] = ['is_dot' => true, 'label' => '...', 'url' => null, 'is_active' => false];
        }
        
        if ($lastPage > 1) {
            $pages[] = $this->makePageItem($lastPage, $paginated);
        }

        return [
            'total'        => $total,
            'current_page' => $currentPage,
            'last_page'    => $lastPage,
            'prev_url'     => $paginated->previousPageUrl(),
            'next_url'     => $paginated->nextPageUrl(),
            'pages'        => $pages,
        ];
    }

    /**
     * 各ページアイテムの作成
     * LengthAwarePaginator 以外では url($page) が無効な場合があるため安全に処理
     */
    private function makePageItem(int $page, Paginator $paginated): array
    {
        return [
            'label'     => $page,
            'url'       => method_exists($paginated, 'url') ? $paginated->url($page) : null,
            'is_active' => $page === $paginated->currentPage(),
            'is_dot'    => false,
        ];
    }
}