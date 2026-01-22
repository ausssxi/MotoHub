<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use App\Services\Bike\BikeService;
use App\Services\Bike\ListingSearchService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Storage;
use App\Models\Listing;

/**
 * バイク検索・表示機能を提供するメインコントローラー
 */
final class BikeController extends Controller
{
    /**
     * @param BikeService $bikeService バイクマスタ関連サービス
     * @param ListingSearchService $listingSearchService 出品検索関連サービス
     */
    public function __construct(
        private readonly BikeService $bikeService,
        private readonly ListingSearchService $listingSearchService
    ) {}

    /**
     * トップページの表示
     */
    public function index(): View
    {
        $popularBikes = $this->bikeService->getPopularBikesForTopPage();
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        
        // 都道府県別の地域分けデータ
        $regions = [
            '北海道・東北' => ['北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県'],
            '関東' => ['茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県'],
            '中部' => ['新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県', '静岡県', '愛知県'],
            '近畿' => ['三重県', '滋賀県', '京都府', '大阪府', '兵庫県', '奈良県', '和歌山県'],
            '中国・四国' => ['鳥取県', '島根県', '岡山県', '広島県', '山口県', '徳島県', '香川県', '愛媛県', '高知県'],
            '九州・沖縄' => ['福岡県', '佐賀県', '長崎県', '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県'],
        ];

        return view('bikes.index', compact('popularBikes', 'totalListingsCount', 'regions'));
    }

    /**
     * 検索結果ページの表示
     */
    public function search(Request $request): View|JsonResponse
    {
        // 1. パラメータの取得
        $keyword = $request->query('keyword');
        $prefecture = $request->query('prefecture');
        $sort = (string) $request->query('sort', 'latest');

        // 2. フィルタ条件の整理
        $filters = [
            'min_price'          => $request->query('min_price'),
            'max_price'          => $request->query('max_price'),
            'min_mileage'        => $request->query('min_mileage'),
            'max_mileage'        => $request->query('max_mileage'),
            'min_year'           => $request->query('min_year'),
            'max_year'           => $request->query('max_year'),
            'manufacturer_id'    => $request->query('manufacturer_id'),
            'bike_model_id'      => $request->query('bike_model_id'),
            'is_new'             => $request->query('is_new'),
            'has_repair_history' => $request->query('has_repair_history'),
            'prefecture'         => $request->query('prefecture'),
        ];

        // モバイル版：件数のみ取得リクエストへの対応
        if ($request->has('count_only')) {
            $count = $this->listingSearchService->getFilteredCount($keyword, $prefecture, $filters);
            return response()->json(['total' => $count]);
        }

        // 3. 検索実行（Service内で推論ロジック等が走る）
        $result = $this->listingSearchService->search($keyword, $prefecture, $sort, $filters);
        
        // 4. 付加情報の取得
        $searchMeta = $this->listingSearchService->getSearchMetadata($keyword, $prefecture);
        $totalListingsCount = $this->listingSearchService->getActiveCount();

        return view('bikes.search', array_merge($result, [
            'keyword'            => $keyword,
            'prefecture'         => $prefecture,
            'sort'               => $sort,
            'meta'               => $searchMeta,
            'totalListingsCount' => $totalListingsCount,
        ]));
    }

    /**
     * 車種取得API (サイドバーのドリルダウン用)
     */
    public function getModels(int $manufacturerId): JsonResponse
    {
        $models = $this->listingSearchService->getModelsByManufacturer($manufacturerId);
        return response()->json($models);
    }

    /**
     * 検索サジェスト用API
     */
    public function suggest(Request $request): JsonResponse
    {
        $keyword = (string) $request->query('keyword', '');
        if (mb_strlen($keyword) < 1) {
            return response()->json([]);
        }

        $suggestions = $this->bikeService->getSearchSuggestions($keyword);
        return response()->json($suggestions);
    }

    /**
     * 車種一覧ページの表示
     */
    public function models(): View
    {
        $data = $this->bikeService->getAllModelsForIndex();
        $totalListingsCount = $this->listingSearchService->getActiveCount();

        return view('bikes.models', array_merge($data, [
            'totalListingsCount' => $totalListingsCount
        ]));
    }

    /**
     * お気に入り一覧ページの表示
     */
    public function wishlist(): View
    {
        $totalListingsCount = $this->listingSearchService->getActiveCount();
        return view('pages.wishlist', compact('totalListingsCount'));
    }

    /**
     * お気に入りデータの非同期取得API
     */
    public function fetchWishlist(Request $request): JsonResponse
    {
        $ids = explode(',', $request->query('ids', ''));
        if (empty($ids) || $ids[0] === '') {
            return response()->json([]);
        }

        $items = Listing::with(['bikeModel', 'shop', 'site'])
            ->whereIn('id', $ids)
            ->where('is_sold_out', false)
            ->get()
            ->map(fn($l) => [
                'id'    => $l->id,
                'name'  => $l->title ?? $l->bikeModel?->name,
                'price' => $l->total_price ? number_format((float)($l->total_price / 10000), 1) : '-',
                'image' => !empty($l->local_image_paths) ? Storage::disk('public')->url($l->local_image_paths[0]) : null,
                'url'   => $l->source_url,
                'store' => $l->shop?->name ?? '販売店情報なし',
            ]);

        return response()->json($items);
    }
}