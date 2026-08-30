<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\BikeModel;
use App\Services\Parts\PartsCodeExtractor;
use App\Support\RakutenKeyword;
use App\Support\RakutenRateGate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BikePartsService
{
    // 楽天商品検索APIのURLは config('services.rakuten.item_search_url') に一本化（バージョン廃止時に1箇所で切替）。

    private const CACHE_TTL = 86400; // 24時間（fetchFlat用）

    // カテゴリ別パーツ(v2)のTTL。parts:refresh のローリング取得で全在庫車種を
    // カバーしきる間キャッシュが生き続けるよう長め。空結果は別途24時間で再試行。
    private const PARTS_CACHE_TTL = 604800; // 7日

    // refreshForModel の取得結果を3状態で区別する（呼び出し側＝parts:refresh が判断に使う）。
    // 「商品あり」「確定的に商品なし」「未取得（休止中・枠取得失敗＝聞きに行けなかった）」。
    // ★未取得を「商品なし」と混同して空をキャッシュすると、429ブレーカー中に全車種の空を
    //   24時間固める事故になる（本バグの原因）。未取得はキャッシュしない。
    public const RESULT_HAS_ITEMS = 'has_items';

    public const RESULT_EMPTY = 'empty';

    public const RESULT_UNFETCHED = 'unfetched';

    private const CATEGORIES = [
        'バッテリー' => 'battery',
        'タイヤ' => 'tire',
        'チェーン' => 'chain',
        'ブレーキパッド' => 'brake',
        'オイル' => 'oil',
        'プラグ' => 'plug',
        'マフラー' => 'muffler',
        'その他' => 'other',
    ];

    /**
     * カテゴリ別パーツのキャッシュキー（v2）
     */
    public static function cacheKey(BikeModel $model): string
    {
        return "parts:bike_model:{$model->id}:v2";
    }

    /**
     * カテゴリ別パーツをキャッシュから読むだけ（render用・read-only）
     *
     * ミス時は [] を返し、楽天APIへは一切アクセスしない。
     * 実fetchは parts:refresh コマンド（refreshForModel）が裏方で担う。
     *
     * @return array<string, array{name: string, items: array}>
     */
    public function getForModel(BikeModel $model): array
    {
        return Cache::get(self::cacheKey($model), []);
    }

    /**
     * 楽天APIからカテゴリ別パーツを取得しキャッシュへ書き込む（ジョブ用）
     *
     * 8カテゴリ直列fetch。render pathからは呼ばない（parts:refresh専用）。
     *
     * ★戻り値は「取得を試みた結果」を3状態で返す（RESULT_HAS_ITEMS/RESULT_EMPTY/RESULT_UNFETCHED）。
     *   1カテゴリでも「未取得（休止中・枠取得失敗・エラー応答＝聞きに行けなかった）」があれば、
     *   部分結果を全件として固めず、モデル全体を未取得扱いにしてキャッシュへ書かない。
     *   → 次アクセス/次バッチで取り直せる。確定的に全カテゴリ空のときだけ空をキャッシュ（24時間）。
     *
     * @return array{status: string, parts: array<string, array{name: string, items: array}>}
     */
    public function refreshForModel(BikeModel $model): array
    {
        $appId = config('services.rakuten.app_id');
        $accessKey = config('services.rakuten.access_key');

        if (! $appId || ! $accessKey) {
            // 認証情報が無い＝そもそも取得を試みていない。空をキャッシュしない。
            return ['status' => self::RESULT_UNFETCHED, 'parts' => []];
        }

        $affiliateId = config('services.rakuten.affiliate_id');
        $result = [];
        $unfetched = false;

        foreach (self::CATEGORIES as $categoryName => $categoryKey) {
            if ($categoryKey === 'other') {
                $keyword = 'バイク '.$model->name;
                $hits = 6;
            } else {
                $keyword = $model->name.' '.$categoryName;
                $hits = 4;
            }

            $outcome = $this->searchRakuten($appId, $accessKey, $affiliateId, $keyword, $hits);

            if (! $outcome['ok']) {
                // 「聞きに行けなかった」カテゴリが1つでもあれば、この時点で打ち切る。
                // 残りを叩いても休止中なら同じ結果で、部分結果を全件と誤認する危険もあるため。
                $unfetched = true;
                break;
            }

            if (! empty($outcome['items'])) {
                $result[$categoryKey] = [
                    'name' => $categoryName,
                    'items' => $outcome['items'],
                ];
            }

            // カテゴリ間隔は共有ゲート（RakutenRateGate）が全経路で1.1秒以上を保証するため sleep 不要。
        }

        if ($unfetched) {
            // ★未取得はキャッシュに書かない（429ブレーカー中の空を24時間固める事故を防ぐ）。
            return ['status' => self::RESULT_UNFETCHED, 'parts' => $result];
        }

        // 全カテゴリを取得できた。商品ありは7日、確定した空は24時間で再試行。
        $ttl = empty($result) ? self::CACHE_TTL : self::PARTS_CACHE_TTL;
        Cache::put(self::cacheKey($model), $result, $ttl);

        return [
            'status' => empty($result) ? self::RESULT_EMPTY : self::RESULT_HAS_ITEMS,
            'parts' => $result,
        ];
    }

    /**
     * フラット配列でパーツを取得（ranking/news等の後方互換用）
     *
     * @return array<int, array>
     */
    public function fetchFlat(BikeModel $model, int $limit = 6): array
    {
        $appId = config('services.rakuten.app_id');
        $accessKey = config('services.rakuten.access_key');

        if (! $appId || ! $accessKey) {
            return [];
        }

        $cacheKey = "parts:bike_model:{$model->id}:flat";

        try {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }

            $affiliateId = config('services.rakuten.affiliate_id');
            $outcome = $this->searchRakuten($appId, $accessKey, $affiliateId, 'バイク '.$model->name, $limit);

            // 未取得（休止中・枠取得失敗・エラー応答）はキャッシュしない。次回取り直す。
            // ★以前は Cache::remember が「聞きに行けなかった空」も24時間固めていた（本バグと同根）。
            if (! $outcome['ok']) {
                return [];
            }

            Cache::put($cacheKey, $outcome['items'], self::CACHE_TTL);

            return $outcome['items'];
        } catch (\Throwable $e) {
            // キャッシュ層など searchRakuten の外側で落ちた場合の保険。無言にはしない。
            Log::warning('BikePartsService fetchFlat failed: '.$e->getMessage(), ['bike_model_id' => $model->id]);

            return [];
        }
    }

    /**
     * 楽天API呼び出し共通処理
     *
     * ★戻り値で「聞きに行けたか」を区別する:
     *   ['ok' => true,  'items' => [...]] … 取得成功（items は空＝確定的に商品なしを含む）
     *   ['ok' => false, 'items' => []]    … 未取得（休止中・枠取得失敗・エラー応答・例外）
     *   呼び出し側はこの ok を見て、未取得を「商品なし」として固めないようにする。
     *
     * @return array{ok: bool, items: array}
     */
    private function searchRakuten(string $appId, string $accessKey, ?string $affiliateId, string $keyword, int $hits): array
    {
        // 楽天に渡す直前でキーワードを正規化。半角英数1文字の語（"r"/"s" 等）は
        // keyword is not valid（400）の原因になるため除去し、有効語が残らなければ叩かずスキップ。
        $normalized = RakutenKeyword::normalize($keyword);
        if ($normalized === null) {
            // 有効語なし＝叩いても必ず空になる確定結果。ok=true の空として扱ってよい（再試行しても同じ）。
            return ['ok' => true, 'items' => []];
        }
        $keyword = $normalized;

        $gate = app(RakutenRateGate::class);

        // 楽天のレート枠は全経路の共有。休止中はここでも叩かない（迂回はしない）＝未取得。
        if ($gate->isPaused()) {
            Log::warning('BikePartsService rakuten がエラー応答', [
                'status' => 0,
                'keyword' => $keyword,
                'body' => $gate->pausedReason(),
            ]);

            return ['ok' => false, 'items' => []];
        }

        // 間隔制御の枠を取れなければ叩かずに未取得を返す（CLI=60秒/Web=0.5秒はゲートが判定）。
        if (! $gate->acquireSlot()) {
            Log::warning('BikePartsService rakuten がエラー応答', [
                'status' => 0,
                'keyword' => $keyword,
                'body' => $gate->waitExceededReason(),
            ]);

            return ['ok' => false, 'items' => []];
        }

        $params = [
            'applicationId' => $appId,
            'accessKey' => $accessKey,
            'keyword' => $keyword,
            'hits' => $hits,
            'sort' => '-reviewCount',
            'format' => 'json',
        ];

        if ($affiliateId) {
            $params['affiliateId'] = $affiliateId;
        }

        try {
            $response = Http::withHeaders([
                'Origin' => 'https://motohub.jp',
                'Referer' => 'https://motohub.jp',
                'User-Agent' => 'MotoHub',
            ])->timeout(5)->get(config('services.rakuten.item_search_url'), $params);

            if (! $response->successful()) {
                // かつては失敗を無言で [] にしていた。429を「0件」と誤読すると障害が見えないため、
                // ProductSearchService と同じ書式（status/keyword/本文先頭200文字）でログに残す。
                $gate->logErrorResponse('BikePartsService', $response->status(), $keyword, $response->body());

                if ($response->status() === 429) {
                    $gate->pause($keyword, 'BikePartsService');
                }

                return ['ok' => false, 'items' => []]; // エラー応答＝未取得（空として固めない）
            }

            $data = $response->json();

            $items = collect($data['Items'] ?? [])->map(function ($wrapper) {
                $item = $wrapper['Item'] ?? $wrapper;
                $codes = PartsCodeExtractor::extract(
                    $item['itemName'] ?? '',
                    $item['itemCaption'] ?? ''
                );

                return [
                    'name' => $item['itemName'] ?? '',
                    'price' => $item['itemPrice'] ?? 0,
                    'image' => $item['mediumImageUrls'][0]['imageUrl'] ?? '',
                    'url' => $item['itemUrl'] ?? '',
                    'jan_code' => $codes['jan'],
                    'part_number' => $codes['partNumber'],
                    'shopName' => $item['shopName'] ?? '',
                    'reviewCount' => $item['reviewCount'] ?? 0,
                    'reviewAverage' => $item['reviewAverage'] ?? 0,
                    'postageFlag' => $item['postageFlag'] ?? 0,
                    'pointRate' => $item['pointRate'] ?? 1,
                ];
            })->all();

            // 200応答＝聞きに行けた。items が空なら「確定的に商品なし」（ok=true の空）。
            return ['ok' => true, 'items' => $items];
        } catch (\Throwable $e) {
            Log::warning('BikePartsService rakuten failed: '.$e->getMessage(), ['keyword' => $keyword]);

            return ['ok' => false, 'items' => []]; // 例外＝未取得
        }
    }
}
