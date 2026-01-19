<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\BikeService;
use App\Services\ListingSearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;

/**
 * バイク検索・表示機能を提供するコントローラー
 * 
 * トップページ、検索結果、車種一覧、検索候補、アバウトページなどの
 * 各種ビューの表示とAPIエンドポイントを担当します。
 */
final class BikeController extends Controller
{
    /**
     * @param BikeService $bikeService バイク情報を取得するサービス
     * @param ListingSearchService $listingSearchService 出品情報の検索を担当するサービス
     */
    public function __construct(
        private readonly BikeService $bikeService,
        private readonly ListingSearchService $listingSearchService
    ) {}

    // index, suggest, models, about は省略（既存のまま）

    /**
     * 検索結果の表示
     * 
     * キーワード、都道府県、ソート順、各種フィルター条件に基づいて
     * バイク出品情報を検索し、結果を表示します。
     * count_onlyパラメータが指定された場合は、件数のみをJSON形式で返します。
     * 
     * @param Request $request 検索条件を含むリクエスト（keyword, prefecture, sort, min_price, max_price, min_mileage, max_mileage, min_year, max_year, count_only）
     * @return View|JsonResponse 通常時は検索結果ビュー、count_only指定時は件数のみを含むJSONレスポンス
     */
    public function search(Request $request): View|JsonResponse
    {
        // 1. 基本パラメータの取得
        $keyword = $request->query('keyword');
        $prefecture = $request->query('prefecture');
        $sort = (string) $request->query('sort', 'latest');

        // 2. フィルター条件の抽出
        $filters = [
            'min_price'   => $request->query('min_price'),
            'max_price'   => $request->query('max_price'),
            'min_mileage' => $request->query('min_mileage'),
            'max_mileage' => $request->query('max_mileage'),
            'min_year'    => $request->query('min_year'),
            'max_year'    => $request->query('max_year'),
        ];

        // モバイル版モーダルからの「件数のみ取得」リクエストへの対応
        if ($request->has('count_only')) {
            $count = $this->listingSearchService->getFilteredCount($keyword, $prefecture, $filters);
            return response()->json(['total' => $count]);
        }

        // 3. 検索実行
        $result = $this->listingSearchService->search($keyword, $prefecture, $sort, $filters);
        
        // 4. 【修正】スライダーの境界値を「現在の検索キーワード」に合わせて取得
        $searchMeta = $this->listingSearchService->getSearchMetadata($keyword, $prefecture);
        
        // 5. サイト全体の有効掲載台数
        $totalListingsCount = $this->listingSearchService->getActiveCount();

        return view('bikes.search', [
            'items'              => $result['items'],
            'pagination'         => $result['pagination'],
            'keyword'            => $keyword,
            'prefecture'         => $prefecture,
            'sort'               => $sort,
            'filters'            => $filters,
            'meta'               => $searchMeta,
            'totalListingsCount' => $totalListingsCount,
        ]);
    }

    /**
     * トップページの表示
     * 
     * 人気のバイク車種、地域情報、サイト全体の有効掲載台数を取得して
     * トップページを表示します。
     * 
     * @return View トップページのビュー
     */
    public function index(): View
    {
        $popularBikes = $this->bikeService->getPopularBikesForTopPage();
        $regions = config('bike.regions', []);
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('bikes.index', compact('popularBikes', 'regions', 'totalListingsCount'));
    }

    /**
     * 検索候補の取得
     * 
     * 入力されたキーワードに基づいて、検索候補となる車種名のリストを
     * JSON形式で返します。キーワードが空の場合は空配列を返します。
     * 
     * @param Request $request 検索キーワードを含むリクエスト（keyword）
     * @return JsonResponse 検索候補の配列を含むJSONレスポンス
     */
    public function suggest(Request $request): JsonResponse
    {
        $keyword = $request->query('keyword');
        if (empty($keyword)) return response()->json([]);
        $suggestions = $this->bikeService->getSearchSuggestions($keyword);
        return response()->json($suggestions);
    }

    /**
     * 車種一覧ページの表示
     * 
     * 全車種をメーカー別にグループ化したデータと、サイト全体の有効掲載台数を
     * 取得して車種一覧ページを表示します。
     * 
     * @return View 車種一覧ページのビュー
     */
    public function models(): View
    {
        $data = $this->bikeService->getAllModelsForIndex();
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('bikes.models', array_merge($data, ['totalListingsCount' => $totalListingsCount]));
    }

    /**
     * アバウトページの表示
     * 
     * サイト全体の有効掲載台数を取得してアバウトページを表示します。
     * 
     * @return View アバウトページのビュー
     */
    public function about(): View
    {
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('pages.about', compact('totalListingsCount'));
    }
}