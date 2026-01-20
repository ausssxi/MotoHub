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
 * 検索結果画面・トップページ・車種一覧・検索候補・お気に入り一覧などの
 * 表示と関連APIを担当し、検索結果には価格相場レポート（stats）も付与します。
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

    /**
     * 検索結果の表示
     *
     * リクエストパラメータからキーワード・都道府県・ソート順・各種フィルター条件を受け取り、
     * 出品情報を検索して検索結果画面を表示します。`count_only` パラメータが指定された場合は、
     * 一覧の代わりに件数のみを JSON 形式で返します。
     *
     * @param Request $request 検索条件を含むリクエスト
     * @return View|JsonResponse 検索結果ビュー、または件数のみを含むJSONレスポンス
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

        // モバイル版モーダルからの「件数のみ取得」リクエストへの対応 (機能維持)
        if ($request->has('count_only')) {
            $count = $this->listingSearchService->getFilteredCount($keyword, $prefecture, $filters);
            return response()->json(['total' => $count]);
        }

        // 3. 検索実行
        // result には [items, pagination, stats] が含まれます
        $result = $this->listingSearchService->search($keyword, $prefecture, $sort, $filters);
        
        // 4. スライダーの境界値を現在のキーワードに合わせて取得 (機能維持)
        $searchMeta = $this->listingSearchService->getSearchMetadata($keyword, $prefecture);
        
        // 5. サイト全体の有効掲載台数 (機能維持)
        $totalListingsCount = $this->listingSearchService->getActiveCount();

        return view('bikes.search', [
            'items'              => $result['items'],
            'pagination'         => $result['pagination'],
            'stats'              => $result['stats'], // ✨ 新規追加：価格相場データをビューへ
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
     * 人気車種一覧・地域情報・サイト全体の有効掲載台数を取得し、
     * トップページのビューを返します。
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
     * 入力されたキーワードに基づいて車種名の検索候補を取得し、
     * JSON 形式で返します。キーワードが空の場合は空配列を返します。
     *
     * @param Request $request 検索キーワードを含むリクエスト
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
     * メーカーごとにグループ化された全車種情報と有効掲載台数を取得し、
     * 車種一覧ページのビューを返します。
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
     * サイト全体の有効掲載台数を取得し、アバウトページのビューを返します。
     *
     * @return View アバウトページのビュー
     */
    public function about(): View
    {
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('pages.about', compact('totalListingsCount'));
    }

    /**
     * お気に入り一覧ページの表示
     *
     * サイト全体の有効掲載台数を取得し、お気に入り一覧ページのビューを返します。
     *
     * @return View お気に入り一覧ページのビュー
     */
    public function wishlist(): View
    {
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('pages.wishlist', compact('totalListingsCount'));
    }

    /**
     * お気に入りデータの非同期取得API
     *
     * クエリパラメータ `ids` で指定されたIDリスト（カンマ区切り）に基づいて
     * 有効な出品情報を取得し、UI表示用に整形して JSON 形式で返します。
     * ID が空の場合は空配列を返します。
     *
     * @param Request $request お気に入りIDリストを含むリクエスト（ids）
     * @return JsonResponse 有効な出品情報の配列を含むJSONレスポンス
     */
    public function fetchWishlist(Request $request): JsonResponse
    {
        $ids = explode(',', $request->query('ids', ''));
        if (empty($ids) || $ids[0] === '') return response()->json([]);

        $items = \App\Models\Listing::with(['bikeModel', 'shop', 'site'])
            ->whereIn('id', $ids)
            ->where('is_sold_out', false)
            ->get()
            ->map(fn($l) => [
                'id' => $l->id,
                'name' => $l->title ?? $l->bikeModel?->name,
                'price' => number_format((float)($l->total_price / 10000), 1),
                'image' => !empty($l->local_image_paths) ? \Illuminate\Support\Facades\Storage::url($l->local_image_paths[0]) : null,
                'url' => $l->source_url,
                'store' => $l->shop?->name,
            ]);

        return response()->json($items);
    }
}