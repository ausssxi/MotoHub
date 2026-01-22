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
     * PHP 8.1+ のコンストラクタプロパティプロモーションを使用した依存注入
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
     * 強化されたフィルタ条件（メーカー・車種・コンディション・修復歴・地域）に対応し、
     * UI構築に必要なマスターデータ（メーカー一覧等）を合わせて提供します。
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

        // 2. フィルター条件の抽出（拡張版）
        $filters = [
            'min_price'          => $request->query('min_price'),
            'max_price'          => $request->query('max_price'),
            'min_mileage'        => $request->query('min_mileage'),
            'max_mileage'        => $request->query('max_mileage'),
            'min_year'           => $request->query('min_year'),
            'max_year'           => $request->query('max_year'),
            // ✨ 追加されたフィルタ項目
            'manufacturer_id'    => $request->query('manufacturer_id'),
            'bike_model_id'      => $request->query('bike_model_id'),
            'is_new'             => $request->query('is_new'),
            'has_repair_history' => $request->query('has_repair_history'),
            'prefecture'         => $request->query('prefecture'),
        ];

        // モバイル版モーダルからの「件数のみ取得」リクエストへの対応 (機能維持)
        if ($request->has('count_only')) {
            $count = $this->listingSearchService->getFilteredCount($keyword, $prefecture, $filters);
            return response()->json(['total' => $count]);
        }

        // 3. 検索実行
        // Service内でキーワードからのメーカー推論や車種IDからの逆算補完が行われます
        $result = $this->listingSearchService->search($keyword, $prefecture, $sort, $filters);
        
        // 4. スライダーの境界値を現在のキーワードに合わせて取得 (機能維持)
        $searchMeta = $this->listingSearchService->getSearchMetadata($keyword, $prefecture);
        
        // 5. サイト全体の有効掲載台数 (機能維持)
        $totalListingsCount = $this->listingSearchService->getActiveCount();

        return view('bikes.search', [
            'items'              => $result['items'],
            'pagination'         => $result['pagination'],
            'stats'              => $result['stats'],
            'manufacturers'      => $result['manufacturers'], // サイドバー用：全メーカー
            'models'             => $result['models'],        // サイドバー用：選択中メーカーの車種
            'prefectures'        => $result['prefectures'],   // サイドバー用：都道府県
            'keyword'            => $keyword,
            'prefecture'         => $prefecture,
            'sort'               => $sort,
            'filters'            => $result['filters'],       // 補完されたフィルタ条件（重要）
            'meta'               => $searchMeta,
            'totalListingsCount' => $totalListingsCount,
        ]);
    }

    /**
     * 車種取得API (JavaScriptからのFetch用)
     * サイドバーのドリルダウン（メーカー選択時に車種リストを更新）で使用します。
     *
     * @param int $manufacturerId メーカーID
     * @return JsonResponse 車種一覧のJSON
     */
    public function getModels(int $manufacturerId): JsonResponse
    {
        $models = $this->listingSearchService->getModelsByManufacturer($manufacturerId);
        return response()->json($models);
    }

    /**
     * トップページの表示 (機能維持)
     */
    public function index(): View
    {
        $popularBikes = $this->bikeService->getPopularBikesForTopPage();
        $regions = config('bike.regions', []);
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('bikes.index', compact('popularBikes', 'regions', 'totalListingsCount'));
    }

    /**
     * 検索候補の取得 (機能維持)
     */
    public function suggest(Request $request): JsonResponse
    {
        $keyword = $request->query('keyword');
        if (empty($keyword)) return response()->json([]);
        $suggestions = $this->bikeService->getSearchSuggestions($keyword);
        return response()->json($suggestions);
    }

    /**
     * 車種一覧ページの表示 (機能維持)
     */
    public function models(): View
    {
        $data = $this->bikeService->getAllModelsForIndex();
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('bikes.models', array_merge($data, ['totalListingsCount' => $totalListingsCount]));
    }

    /**
     * お気に入り一覧ページの表示 (機能維持)
     */
    public function wishlist(): View
    {
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('pages.wishlist', compact('totalListingsCount'));
    }

    /**
     * お気に入りデータの非同期取得API (機能維持)
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