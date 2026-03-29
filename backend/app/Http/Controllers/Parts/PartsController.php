<?php

namespace App\Http\Controllers\Parts;

use App\Http\Controllers\Controller;
use App\Services\Parts\PartsCodeExtractor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class PartsController extends Controller
{
    /**
     * キーワード組み立て（共通）
     */
    private function buildSearchQuery(Request $request): string
    {
        $parts = ['バイク'];
        if ($request->filled('keyword')) $parts[] = $request->input('keyword');
        if ($request->filled('bike')) $parts[] = $request->input('bike');
        if ($request->filled('category')) $parts[] = $request->input('category');
        return implode(' ', $parts);
    }

    /**
     * 楽天API呼び出し（共通）
     */
    private function fetchRakuten(string $searchQuery, int $page = 1, int $hits = 30): ?array
    {
        $appId = config('services.rakuten.app_id');
        $accessKey = config('services.rakuten.access_key');
        if (!$appId || !$accessKey) return null;

        $cacheKey = 'rakuten_parts_' . md5($searchQuery . '_page_' . $page . '_hits_' . $hits);
        return Cache::remember($cacheKey, 600, function () use ($appId, $accessKey, $searchQuery, $page, $hits) {
            $params = [
                'applicationId' => $appId,
                'accessKey'     => $accessKey,
                'keyword'       => $searchQuery,
                'hits'          => $hits,
                'page'          => $page,
                'format'        => 'json',
            ];
            $affiliateId = config('services.rakuten.affiliate_id');
            if ($affiliateId) $params['affiliateId'] = $affiliateId;

            $response = Http::withHeaders([
                'Origin'     => 'https://motohub.jp',
                'Referer'    => 'https://motohub.jp',
                'User-Agent' => 'MotoHub',
            ])->timeout(10)->get('https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20220601', $params);

            return $response->successful() ? $response->json() : null;
        });
    }

    /**
     * 楽天レスポンス整形（共通）
     * $withCodes=true の場合、itemCaption からJAN/品番を抽出して付与
     */
    private function formatRakutenItems(array $data, bool $withCodes = false): array
    {
        return collect($data['Items'] ?? [])->map(function ($wrapper) use ($withCodes) {
            $item = $wrapper['Item'] ?? $wrapper;
            $result = [
                'name'        => $item['itemName'] ?? '',
                'price'       => $item['itemPrice'] ?? 0,
                'image'       => $item['mediumImageUrls'][0]['imageUrl'] ?? '',
                'url'         => $item['itemUrl'] ?? '',
                'shop'        => $item['shopName'] ?? '',
                'review_count'=> $item['reviewCount'] ?? 0,
                'review_avg'  => $item['reviewAverage'] ?? 0,
            ];
            if ($withCodes) {
                $codes = PartsCodeExtractor::extract(
                    $item['itemName'] ?? '',
                    $item['itemCaption'] ?? ''
                );
                $result['jan_code']    = $codes['jan'];
                $result['part_number'] = $codes['partNumber'];
            }
            return $result;
        })->all();
    }

    /**
     * Yahoo API呼び出し（共通）
     */
    private function fetchYahoo(string $searchQuery, int $page = 1, int $results = 20): ?array
    {
        $clientId = config('services.yahoo_shopping.client_id');
        if (!$clientId) return null;

        $cacheKey = 'yahoo_parts_' . md5($searchQuery . '_page_' . $page . '_results_' . $results);
        return Cache::remember($cacheKey, 600, function () use ($clientId, $searchQuery, $page, $results) {
            $start = ($page - 1) * $results + 1;
            $response = Http::timeout(10)->get('https://shopping.yahooapis.jp/ShoppingWebService/V3/itemSearch', [
                'appid'   => $clientId,
                'query'   => $searchQuery,
                'results' => $results,
                'start'   => $start,
                'sort'    => '+price',
            ]);
            return $response->successful() ? $response->json() : null;
        });
    }

    /**
     * Yahooレスポンス整形（共通）
     */
    private function formatYahooItems(array $data): array
    {
        $vcSid = config('services.yahoo_shopping.valuecommerce_sid');
        $vcPid = config('services.yahoo_shopping.valuecommerce_pid');

        return collect($data['hits'] ?? [])->map(function ($item) use ($vcSid, $vcPid) {
            $productUrl = $item['url'] ?? '';
            if ($vcSid && $vcPid && $productUrl) {
                $productUrl = 'https://ck.jp.ap.valuecommerce.com/servlet/referral?sid=' . $vcSid
                    . '&pid=' . $vcPid
                    . '&vc_url=' . urlencode($productUrl);
            }
            $image = $item['image']['medium'] ?? $item['image']['small'] ?? '';
            return [
                'name'         => $item['name'] ?? '',
                'price'        => $item['price'] ?? 0,
                'image'        => $image,
                'url'          => $productUrl,
                'shop'         => $item['seller']['name'] ?? '',
                'review_count' => $item['review']['count'] ?? 0,
                'review_avg'   => $item['review']['rate'] ?? 0,
            ];
        })->all();
    }

    /**
     * パーツ検索ページ表示（人気カテゴリのプリロード結果付き）
     */
    public function index()
    {
        $popularCategories = ['マフラー', 'タイヤ', 'チェーン'];
        $popularItems = [];

        foreach ($popularCategories as $category) {
            $cacheKey = 'parts_popular_' . $category;
            $items = Cache::remember($cacheKey, 3600, function () use ($category) {
                $data = $this->fetchRakuten('バイク ' . $category, 1, 4);
                return $data ? $this->formatRakutenItems($data) : [];
            });
            $popularItems[$category] = $items;
        }

        return view('parts.index', compact('popularItems'));
    }

    /**
     * 楽天市場APIを叩いて結果を返す（JAN/品番付き）
     */
    public function search(Request $request)
    {
        $request->validate([
            'keyword'  => 'nullable|string|max:200',
            'bike'     => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'page'     => 'nullable|integer|min:1|max:100',
        ]);

        if (!$request->filled('keyword') && !$request->filled('bike')) {
            return response()->json(['error' => 'キーワードまたは車種名を入力してください', 'items' => []], 422);
        }

        $searchQuery = $this->buildSearchQuery($request);
        $page = $request->input('page', 1);
        $data = $this->fetchRakuten($searchQuery, $page);

        if ($data === null) {
            return response()->json(['error' => '楽天APIからデータを取得できませんでした。', 'items' => []], 502);
        }

        return response()->json([
            'items'       => $this->formatRakutenItems($data, withCodes: true),
            'currentPage' => (int) $page,
            'totalPages'  => (int) ($data['pageCount'] ?? 1),
            'totalCount'  => (int) ($data['count'] ?? 0),
            'hasMore'     => $page < ($data['pageCount'] ?? 1),
        ]);
    }

    /**
     * Yahoo!ショッピングAPIを叩いて結果を返す
     */
    public function searchYahoo(Request $request)
    {
        $request->validate([
            'keyword'  => 'nullable|string|max:200',
            'bike'     => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'page'     => 'nullable|integer|min:1|max:100',
        ]);

        if (!$request->filled('keyword') && !$request->filled('bike')) {
            return response()->json(['error' => 'キーワードまたは車種名を入力してください', 'items' => []], 422);
        }

        $searchQuery = $this->buildSearchQuery($request);
        $page = $request->input('page', 1);
        $data = $this->fetchYahoo($searchQuery, $page);

        if ($data === null) {
            return response()->json(['error' => 'Yahoo!ショッピングAPIからデータを取得できませんでした。', 'items' => []], 502);
        }

        $totalCount = $data['totalResultsAvailable'] ?? 0;
        $totalPages = $totalCount > 0 ? (int) ceil($totalCount / 20) : 1;

        return response()->json([
            'items'       => $this->formatYahooItems($data),
            'currentPage' => (int) $page,
            'totalPages'  => min($totalPages, 100),
            'totalCount'  => (int) $totalCount,
            'hasMore'     => $page < min($totalPages, 100),
        ]);
    }

    /**
     * 価格比較ページ（JAN/品番/キーワードで楽天・Yahoo並列取得）
     */
    public function compare(Request $request)
    {
        $request->validate([
            'jan'     => 'nullable|string|size:13',
            'partnum' => 'nullable|string|max:50',
            'keyword' => 'nullable|string|max:300',
        ]);

        $jan     = $request->input('jan');
        $partnum = $request->input('partnum');
        $keyword = $request->input('keyword');

        // 検索クエリ決定（優先順位: JAN > 品番 > キーワード）
        if ($jan) {
            $searchQuery = $jan;
            $searchType  = 'jan';
        } elseif ($partnum) {
            $searchQuery = $partnum;
            $searchType  = 'partnum';
        } elseif ($keyword) {
            $searchQuery = $keyword;
            $searchType  = 'keyword';
        } else {
            abort(422, '検索条件が指定されていません');
        }

        // 表示用タイトル
        $displayTitle = $keyword ?: $partnum ?: $jan;

        $cacheKey = 'parts_compare_' . md5($searchQuery);
        $cached = Cache::remember($cacheKey, 600, function () use ($searchQuery) {
            $responses = Http::pool(fn ($pool) => [
                $pool->as('rakuten')->withHeaders([
                    'Origin' => 'https://motohub.jp', 'Referer' => 'https://motohub.jp', 'User-Agent' => 'MotoHub',
                ])->timeout(10)->get('https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20220601', array_filter([
                    'applicationId' => config('services.rakuten.app_id'),
                    'accessKey'     => config('services.rakuten.access_key'),
                    'keyword'       => $searchQuery,
                    'hits'          => 10,
                    'page'          => 1,
                    'format'        => 'json',
                    'affiliateId'   => config('services.rakuten.affiliate_id'),
                ])),
                $pool->as('yahoo')->timeout(10)->get('https://shopping.yahooapis.jp/ShoppingWebService/V3/itemSearch', [
                    'appid'   => config('services.yahoo_shopping.client_id'),
                    'query'   => $searchQuery,
                    'results' => 10,
                    'start'   => 1,
                    'sort'    => '+price',
                ]),
            ]);

            return [
                'rakuten' => isset($responses['rakuten']) && $responses['rakuten']->successful()
                    ? $responses['rakuten']->json() : null,
                'yahoo'   => isset($responses['yahoo']) && $responses['yahoo']->successful()
                    ? $responses['yahoo']->json() : null,
            ];
        });

        $rakutenItems = $cached['rakuten'] ? $this->formatRakutenItems($cached['rakuten']) : [];
        $yahooItems   = $cached['yahoo']   ? $this->formatYahooItems($cached['yahoo']) : [];

        // 価格安い順ソート
        usort($rakutenItems, fn ($a, $b) => $a['price'] - $b['price']);
        usort($yahooItems, fn ($a, $b) => $a['price'] - $b['price']);

        // 最安値を算出
        $allItems = array_merge(
            array_map(fn ($i) => array_merge($i, ['source' => 'rakuten']), $rakutenItems),
            array_map(fn ($i) => array_merge($i, ['source' => 'yahoo']), $yahooItems),
        );
        usort($allItems, fn ($a, $b) => $a['price'] - $b['price']);
        $best = $allItems[0] ?? null;

        // Amazon検索URL（JAN or 品番 or keyword）
        $amazonQuery = $jan ?: $partnum ?: $keyword;
        $amazonTag = config('services.amazon.associate_tag');
        $amazonUrl = 'https://www.amazon.co.jp/s?k=' . urlencode($amazonQuery);
        if ($amazonTag) $amazonUrl .= '&tag=' . urlencode($amazonTag);

        return view('parts.compare', [
            'displayTitle'  => $displayTitle,
            'jan'           => $jan,
            'partNumber'    => $partnum,
            'searchType'    => $searchType,
            'rakutenItems'  => $rakutenItems,
            'yahooItems'    => $yahooItems,
            'best'          => $best,
            'amazonUrl'     => $amazonUrl,
        ]);
    }
}
