<?php

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Services\Bike\ListingSearchService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * バイク出品情報の検
 * 索リクエストを処理
 */
final class ListingSearchController extends Controller
{
    /**
     * @param ListingSearchService $searchService
     */
    public function __construct(
        private readonly ListingSearchService $searchService
    ) {}

    /**
     * 検索結果一覧を表示
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        // クエリパラメータからキーワードを取得（デフォルトは空文字または代表的な車種）
        $keyword = (string) $request->query('keyword', 'Rebel 250');

        // 整形済みのデータをサービスから取得
        $bikes = $this->searchService->search($keyword);

        // ビューへキーワードと検索結果を渡す
        return view('bikes.index', compact('bikes', 'keyword'));
    }
}