<?php

namespace App\Http\Controllers\Parts;

use App\Http\Controllers\Controller;
use App\Models\BikeModel;
use App\Services\Parts\PartsCodeExtractor;
use App\Support\RakutenRateGate;
use Carbon\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PartsController extends Controller
{
    /** 価格比較の取得結果キャッシュTTL（秒） */
    private const COMPARE_CACHE_TTL = 600;

    /**
     * 両モールとも取得できなかったときのキャッシュTTL（秒）。
     * 通常TTLで焼くと一時的なタイムアウトで10分間ずっと空ページになる。かといって
     * キャッシュしないと相手APIが不調なときにアクセスのたび叩いて追い打ちをかける。
     * 短めに焼いて、次の1分後の閲覧で再取得されるようにする。
     */
    private const COMPARE_CACHE_TTL_ON_FAILURE = 60;

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
     * $genreId を指定するとジャンル絞り込み（200305=バイク用品）
     */
    private function fetchRakuten(string $searchQuery, int $page = 1, int $hits = 30, ?int $genreId = null): ?array
    {
        $appId = config('services.rakuten.app_id');
        $accessKey = config('services.rakuten.access_key');
        if (!$appId || !$accessKey) return null;

        $cacheKey = 'rakuten_parts_' . md5($searchQuery . '_page_' . $page . '_hits_' . $hits . '_genre_' . ($genreId ?? 0));
        return Cache::remember($cacheKey, 600, function () use ($appId, $accessKey, $searchQuery, $page, $hits, $genreId) {
            $gate = app(RakutenRateGate::class);

            // 楽天のレート枠は全経路の共有。休止中はここでも叩かない（迂回はしない）。
            if ($gate->isPaused()) {
                Log::warning('PartsController rakuten がエラー応答', [
                    'status'  => 0,
                    'keyword' => $searchQuery,
                    'body'    => $gate->pausedReason(),
                ]);
                return null;
            }

            // 間隔制御の枠を取れなければ叩かずに null を返す（CLI=5秒/Web=0.5秒はゲートが判定）。
            if (! $gate->acquireSlot()) {
                Log::warning('PartsController rakuten がエラー応答', [
                    'status'  => 0,
                    'keyword' => $searchQuery,
                    'body'    => $gate->waitExceededReason(),
                ]);
                return null;
            }

            $params = [
                'applicationId' => $appId,
                'accessKey'     => $accessKey,
                'keyword'       => $searchQuery,
                'hits'          => $hits,
                'page'          => $page,
                'format'        => 'json',
            ];
            if ($genreId) $params['genreId'] = $genreId;
            $affiliateId = config('services.rakuten.affiliate_id');
            if ($affiliateId) $params['affiliateId'] = $affiliateId;

            $response = Http::withHeaders([
                'Origin'     => 'https://motohub.jp',
                'Referer'    => 'https://motohub.jp',
                'User-Agent' => 'MotoHub',
            ])->timeout(10)->get('https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20220601', $params);

            if (! $response->successful()) {
                // かつては失敗を無言で null にしていた。429を「取得できず」と混同すると障害が見えないため、
                // ProductSearchService と同じ書式（status/keyword/本文先頭200文字）でログに残す。
                $gate->logErrorResponse('PartsController', $response->status(), $searchQuery, $response->body());

                if ($response->status() === 429) {
                    $gate->pause($searchQuery, 'PartsController');
                }

                return null;
            }

            return $response->json();
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
                'name'         => $item['itemName'] ?? '',
                'price'        => $item['itemPrice'] ?? 0,
                'image'        => $item['mediumImageUrls'][0]['imageUrl'] ?? '',
                'url'          => $item['itemUrl'] ?? '',
                'shop'         => $item['shopName'] ?? '',
                'review_count' => $item['reviewCount'] ?? 0,
                'review_avg'   => $item['reviewAverage'] ?? 0,
                'caption'      => mb_substr(strip_tags($item['itemCaption'] ?? ''), 0, 200),
                'caption_full' => strip_tags($item['itemCaption'] ?? ''),
                'postage_flag' => (int) ($item['postageFlag'] ?? 0),
                'point_rate'   => (int) ($item['pointRate'] ?? 1),
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
        $popularCategories = [
            'マフラー'  => 'マフラー',
            'タイヤ'    => 'タイヤ',
            'チェーン'  => 'ドライブチェーン',
        ];
        $popularItems = [];

        foreach ($popularCategories as $label => $query) {
            $cacheKey = 'parts_popular_' . md5($query . '_genre_200305');
            $items = Cache::remember($cacheKey, 3600, function () use ($query) {
                $data = $this->fetchRakuten($query, 1, 4, genreId: 200305);
                return $data ? $this->formatRakutenItems($data) : [];
            });
            $popularItems[$label] = $items;
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
        $data = $this->fetchRakuten($searchQuery, $page, 30, genreId: 200305);

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
     * カテゴリ別ランディングページ
     */
    public function category(string $slug)
    {
        $categories = config('parts-categories', []);
        $category = collect($categories)->firstWhere('slug', $slug);

        if (!$category) {
            abort(404);
        }

        $searchQuery = 'バイク ' . $category['name'];
        $cacheKey = 'parts_category_' . $slug;

        $items = Cache::remember($cacheKey, 86400, function () use ($searchQuery, $category) {
            $data = $this->fetchRakuten($searchQuery, 1, 10, genreId: $category['rakuten_genre_id']);
            return $data ? $this->formatRakutenItems($data) : [];
        });

        $otherCategories = collect($categories)->where('slug', '!=', $slug)->values();

        // 実勢価格統計（parts_category_price_stats）。無ければ config の price_range にフォールバック。
        // テーブル未作成でもページを落とさないよう try/catch。
        $priceStats = null;
        try {
            $priceStats = DB::table('parts_category_price_stats')->where('category_slug', $slug)->first();
        } catch (\Throwable) {
            $priceStats = null;
        }

        // 分布バーの幾何（最安〜最高を全幅とした Q1/中央値/Q3 の位置％）と日付ラベルをここで用意する。
        // ※blade 側にインライン @php を足さないため、計算はコントローラに寄せる。
        $priceBand = null;
        if ($priceStats) {
            $span = max(1, (int) $priceStats->price_max - (int) $priceStats->price_min);
            $pct = fn ($v) => round(((int) $v - (int) $priceStats->price_min) / $span * 100, 1);
            $priceBand = [
                'q1_pct' => $pct($priceStats->price_q1),
                'q3_pct' => $pct($priceStats->price_q3),
                'median_pct' => $pct($priceStats->price_median),
                'date_full' => Carbon::parse($priceStats->computed_at)->format('Y年n月j日'),
                'date_ym' => Carbon::parse($priceStats->computed_at)->format('Y年n月'),
            ];
        }

        return view('parts.category', [
            'category' => $category,
            'items' => $items,
            'otherCategories' => $otherCategories,
            'priceStats' => $priceStats,
            'priceBand' => $priceBand,
        ]);
    }

    /**
     * Http::pool の結果から、そのモールのJSONを取り出す。取れなければ null。
     *
     * ⚠️ pool は「接続できなかったリクエスト」に対して Response ではなく
     *    Illuminate\Http\Client\ConnectionException を **返す**（投げない）。
     *    PendingRequest::makePromise() の otherwise が
     *      return $exception;
     *    としているため。したがって isset() だけ確認して ->successful() を呼ぶと
     *      Call to undefined method Illuminate\Http\Client\ConnectionException::successful()
     *    で落ちる（本番で発生。cURL error 28 のタイムアウトと同時刻）。
     *    ここで型を確かめ、Response 以外は「そのモールは結果なし」として扱う。
     *
     * 接続失敗を握りつぶさないよう、モール名と理由は必ずログに残す。
     */
    private function poolJson(array $responses, string $mall): ?array
    {
        $result = $responses[$mall] ?? null;

        if ($result instanceof Response) {
            if (! $result->successful()) {
                Log::warning("parts:compare {$mall} がエラー応答を返しました", [
                    'status' => $result->status(),
                ]);

                return null;
            }

            $json = $result->json();

            return is_array($json) ? $json : null;
        }

        if ($result instanceof \Throwable) {
            // 接続自体が確立していない（cURL 28 のタイムアウト等）のでレスポンスは存在しない。
            Log::warning("parts:compare {$mall} へ接続できませんでした", [
                'error' => $result->getMessage(),
            ]);
        }

        return null;
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
        $cached = Cache::get($cacheKey);

        if ($cached === null) {
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

            $cached = [
                'rakuten' => $this->poolJson($responses, 'rakuten'),
                'yahoo'   => $this->poolJson($responses, 'yahoo'),
            ];

            // 片方でも取れていれば通常TTL。両方だめなら短いTTLで焼いて早めに再試行する。
            Cache::put(
                $cacheKey,
                $cached,
                ($cached['rakuten'] !== null || $cached['yahoo'] !== null)
                    ? self::COMPARE_CACHE_TTL
                    : self::COMPARE_CACHE_TTL_ON_FAILURE
            );
        }

        $rakutenItems = $cached['rakuten'] ? $this->formatRakutenItems($cached['rakuten'], withCodes: true) : [];
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

        // カテゴリ代表画像（キャッシュ済みデータの1件目）
        $categories = config('parts-categories', []);
        $categoryImages = [];
        foreach ($categories as $cat) {
            $cachedItems = Cache::get('parts_category_' . $cat['slug']);
            $categoryImages[$cat['slug']] = $cachedItems[0]['image'] ?? null;
        }

        // 人気車種の画像
        $popularBikeNames = ['CBR250RR', 'PCX', 'レブル250', 'スーパーカブ', 'モンキー125',
            'CT125', 'YZF-R25', 'Ninja250', 'Z900RS', 'MT-07'];
        $bikeCards = BikeModel::with('representativeListing')
            ->whereIn('name', $popularBikeNames)
            ->get()
            ->map(fn ($bike) => ['name' => $bike->name, 'image_url' => $bike->image_url])
            ->sortBy(fn ($b) => array_search($b['name'], $popularBikeNames))
            ->values()
            ->all();

        return view('parts.compare', [
            'displayTitle'   => $displayTitle,
            'jan'            => $jan,
            'partNumber'     => $partnum,
            'searchType'     => $searchType,
            'rakutenItems'   => $rakutenItems,
            'yahooItems'     => $yahooItems,
            'best'           => $best,
            'amazonUrl'      => $amazonUrl,
            'categoryImages' => $categoryImages,
            'bikeCards'      => $bikeCards,
        ]);
    }
}
