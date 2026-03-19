<?php

namespace App\Http\Controllers\Parts;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class PartsController extends Controller
{
    /**
     * パーツ検索ページ表示
     */
    public function index()
    {
        return view('parts.index');
    }

    /**
     * 楽天市場APIを叩いて結果を返す
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
            return response()->json([
                'error' => 'キーワードまたは車種名を入力してください',
                'items' => [],
            ], 422);
        }

        $keyword = $request->input('keyword');
        $bike = $request->input('bike');
        $category = $request->input('category');
        $page = $request->input('page', 1);

        // キーワードを組み立て
        $parts = ['バイク'];
        if ($keyword) {
            $parts[] = $keyword;
        }
        if ($bike) {
            $parts[] = $bike;
        }
        if ($category) {
            $parts[] = $category;
        }
        $searchQuery = implode(' ', $parts);

        $appId = config('services.rakuten.app_id');
        $accessKey = config('services.rakuten.access_key');

        if (!$appId || $appId === 'your_app_id_here' || !$accessKey || $accessKey === 'your_access_key_here') {
            return response()->json([
                'error' => '楽天APIキーが設定されていません。.envのRAKUTEN_APP_IDとRAKUTEN_ACCESS_KEYを設定してください。',
                'items' => [],
            ], 503);
        }

        // デバッグ：実際に送信しているヘッダーとパラメータをログに出力
        \Log::info('楽天APIリクエスト', [
            'url' => config('app.url'),
            'app_id' => config('services.rakuten.app_id'),
            'access_key_prefix' => substr(config('services.rakuten.access_key'), 0, 10),
            'page' => $page,
        ]);

        // キャッシュ（同じクエリ+ページは10分間保持）
        $cacheKey = 'rakuten_parts_' . md5($searchQuery . '_page_' . $page);
        $data = Cache::remember($cacheKey, 600, function () use ($appId, $accessKey, $searchQuery, $page) {
            $params = [
                'applicationId' => $appId,
                'accessKey'     => $accessKey,
                'keyword'       => $searchQuery,
                'hits'          => 30,
                'page'          => $page,
                'format'        => 'json',
            ];

            $affiliateId = config('services.rakuten.affiliate_id');
            if ($affiliateId) {
                $params['affiliateId'] = $affiliateId;
            }

            $response = Http::withHeaders([
                'Origin'     => 'https://motohub.jp',
                'Referer'    => 'https://motohub.jp',
                'User-Agent' => 'MotoHub',
            ])->timeout(10)->get('https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20220601', $params);

            if ($response->failed()) {
                $body = $response->json();
                \Illuminate\Support\Facades\Log::warning('Rakuten API error', [
                    'status' => $response->status(),
                    'body'   => $body,
                ]);
                return null;
            }

            return $response->json();
        });

        if ($data === null) {
            Cache::forget($cacheKey);
            return response()->json([
                'error' => '楽天APIからデータを取得できませんでした。',
                'items' => [],
            ], 502);
        }

        // 必要なフィールドだけ整形して返す
        $items = collect($data['Items'] ?? [])->map(function ($wrapper) {
            $item = $wrapper['Item'] ?? $wrapper;
            return [
                'name'        => $item['itemName'] ?? '',
                'price'       => $item['itemPrice'] ?? 0,
                'image'       => $item['mediumImageUrls'][0]['imageUrl'] ?? '',
                'url'         => $item['itemUrl'] ?? '',
                'shop'        => $item['shopName'] ?? '',
                'review_count'=> $item['reviewCount'] ?? 0,
                'review_avg'  => $item['reviewAverage'] ?? 0,
            ];
        });

        $totalCount = $data['count'] ?? 0;
        $totalPages = $data['pageCount'] ?? 1;

        return response()->json([
            'items'       => $items,
            'currentPage' => (int) $page,
            'totalPages'  => (int) $totalPages,
            'totalCount'  => (int) $totalCount,
            'hasMore'     => $page < $totalPages,
        ]);
    }
}
