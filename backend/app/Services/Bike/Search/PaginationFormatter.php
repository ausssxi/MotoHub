<?php

declare(strict_types=1);

namespace App\Services\Bike\Search;

use Illuminate\Pagination\LengthAwarePaginator;

/**
 * ページネーションの表示用データを整形するクラス
 */
final class PaginationFormatter
{
    public function format(LengthAwarePaginator $paginated): array
    {
        $currentPage = $paginated->currentPage();
        $lastPage = $paginated->lastPage();
        $pages = [];
        
        $pages[] = $this->makePageItem(1, $paginated);
        if ($currentPage - 1 > 2) $pages[] = ['is_dot' => true, 'label' => '...', 'url' => null, 'is_active' => false];
        
        for ($i = max(2, $currentPage - 1); $i <= min($lastPage - 1, $currentPage + 1); $i++) {
            $pages[] = $this->makePageItem($i, $paginated);
        }
        
        if ($currentPage + 1 < $lastPage - 1) $pages[] = ['is_dot' => true, 'label' => '...', 'url' => null, 'is_active' => false];
        if ($lastPage > 1) $pages[] = $this->makePageItem($lastPage, $paginated);

        return [
            'total'        => $paginated->total(),
            'current_page' => $currentPage,
            'last_page'    => $lastPage,
            'prev_url'     => $paginated->previousPageUrl(),
            'next_url'     => $paginated->nextPageUrl(),
            'pages'        => $pages,
        ];
    }

    private function makePageItem(int $page, LengthAwarePaginator $paginated): array
    {
        return [
            'label' => $page,
            'url' => $paginated->url($page),
            'is_active' => $page === $paginated->currentPage(),
            'is_dot' => false,
        ];
    }
}