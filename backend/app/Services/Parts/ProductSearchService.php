<?php

declare(strict_types=1);

namespace App\Services\Parts;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 楽天 / Yahoo 商品検索（愛車ガレージ カスタム記録の商品連携・第2段階2a）。
 *
 * 設計の肝:
 *  - 必ずグレースフル: 各モールAPIを try/catch し、失敗時は空配列にフォールバックする。
 *    呼び出し元（エンドポイント）は絶対に 5xx を出さない
 *    （旧 PartsController の「Rakuten失敗時 return 502」は踏襲しない）。
 *  - 外部APIは「商品を探す」押下時のみ。入力中（キーストローク）では叩かない。
 *  - 10分キャッシュ・短timeout で負荷とコストを抑える。
 *  - 返す URL はアフィリエイト済み
 *    （Rakuten=affiliateId 付きの itemUrl, Yahoo=ValueCommerce ラップ）。
 *
 * 既存 PartsController の private メソッドは流用せず、責務分離のため本サービスに集約する
 * （PartsController 側の置き換えは今回スコープ外＝リスク低減）。
 */
final class ProductSearchService
{
    private const RAKUTEN_URL = 'https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20220601';

    private const YAHOO_URL = 'https://shopping.yahooapis.jp/ShoppingWebService/V3/itemSearch';

    private const RAKUTEN_BIKE_GENRE = 200305; // バイク用品

    private const CACHE_TTL = 600; // 10分

    /**
     * どちらかのモールが失敗した回のキャッシュTTL（秒）。
     * 通常TTLで焼くと、一時的な429やタイムアウトの「空配列」が10分間そのまま返り続け、
     * 「0件だった」のか「取れなかった」のか誰にも分からなくなる。かといってキャッシュしないと
     * 相手APIが不調なときにアクセスのたび叩いて追い打ちをかけるため、短めに焼く。
     */
    private const CACHE_TTL_ON_ERROR = 60;

    private const TIMEOUT = 8;

    /**
     * 直近の searchProducts() で発生したモール別のエラー。['rakuten' => 'HTTP 429', ...]。
     * 空配列なら「エラー無し」。キャッシュヒット時も空配列（今回HTTPしていないため）。
     *
     * グレースフルに空配列を返す設計は維持しつつ、呼び出し側が
     * 「0件」と「取得失敗」を区別できるようにするための出口。
     *
     * @var array<string, string>
     */
    private array $lastErrors = [];

    /**
     * 直近の searchProducts() のモール別エラー。空配列ならエラー無し。
     *
     * @return array<string, string>
     */
    public function lastErrors(): array
    {
        return $this->lastErrors;
    }

    /**
     * キーワードで楽天 + Yahoo を検索し、正規化した商品候補を返す。
     * どのモールが落ちても例外は投げず、取れた分だけ返す（最悪は空配列）。
     *
     * $withDescription=true のときだけ、楽天の商品説明文（itemCaption）を 'description' として
     * 追加で返す。既定 false の場合の戻り値は従来と完全に同一（キーの数・順序も変えていない）。
     * 適合データ抽出の歩留まり測定（fitment:probe）専用のフラグで、通常の商品検索
     * （愛車ガレージのカスタム記録）では使わない。説明文は数KBあり、通常用途には不要なため。
     *
     * @return array<int, array{mall:string, product_id:string, name:string, image:string, price:int, url:string, shop:string, description?:string}>
     */
    public function searchProducts(string $keyword, int $hits = 20, bool $withDescription = false, bool $rakutenOnly = false): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }
        $hits = max(1, min($hits, 30));

        // 説明文つき／楽天のみは payload の形・中身が違うため、キャッシュキーを分ける。
        // (どちらも false のときのキーは従来と同一文字列＝既存のキャッシュがそのまま効く)
        $cacheKey = 'garage_product_search_'.md5(
            $keyword.'_'.$hits.($withDescription ? '_desc' : '').($rakutenOnly ? '_rkt' : '')
        );

        $this->lastErrors = [];

        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            // キャッシュヒット＝今回はHTTPしていないので lastErrors は空のまま
            return $cached;
        }

        // 各モールAPIの関連度順を尊重してマージ（楽天→Yahoo）。
        // ★価格昇順ソートは廃止する：Yahoo はジャンル絞りが無く、最安の無関係品
        //   （チェーンクリップ¥5・ドレンパッキン¥15 等）が上位に浮上し、楽天の良質な該当商品を
        //   押しのけて「おすすめ商品」枠を汚染していた（本番のオイル/バッテリー枠で誤表示が発生）。
        //   関連度順なら各モールが該当商品を上位に返すため、先頭 slice がそのまま良質候補になる。
        $results = array_merge(
            $this->searchRakuten($keyword, $hits, $withDescription),
            // $rakutenOnly=true のときは Yahoo を叩かない。適合抽出の測定は楽天だけを集計するため、
            // 呼ぶだけ無駄なリクエストになり、楽天のレート制限に当たりやすくなる。
            $rakutenOnly ? [] : $this->searchYahoo($keyword, $hits),
        );

        // キャッシュの焼き方は3通りに分ける。
        //  1) エラー無し          → 通常TTL
        //  2) エラーあるが結果あり → 短いTTL（片方のモールだけ失敗。使えるデータはある）
        //  3) エラーありで結果空   → 焼かない
        //     焼くと「取得失敗」を「0件」として配り続けるうえ、呼び出し側の再試行が
        //     キャッシュに阻まれて必ず空を返すようになる（再試行が無意味になる）。
        if ($this->lastErrors === []) {
            Cache::put($cacheKey, $results, self::CACHE_TTL);
        } elseif ($results !== []) {
            Cache::put($cacheKey, $results, self::CACHE_TTL_ON_ERROR);
        }

        return $results;
    }

    /**
     * 楽天検索（失敗時は []）。affiliateId を渡すと itemUrl がアフィリエイトURLになる。
     *
     * $withDescription=true のときだけ itemCaption を 'description' として付与する。
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchRakuten(string $keyword, int $hits, bool $withDescription = false): array
    {
        $appId = config('services.rakuten.app_id');
        $accessKey = config('services.rakuten.access_key');
        if (! $appId || ! $accessKey) {
            return [];
        }

        try {
            $params = [
                'applicationId' => $appId,
                'accessKey' => $accessKey,
                'keyword' => $keyword,
                'hits' => $hits,
                'format' => 'json',
                'genreId' => self::RAKUTEN_BIKE_GENRE,
            ];
            $affiliateId = config('services.rakuten.affiliate_id');
            if ($affiliateId) {
                $params['affiliateId'] = $affiliateId;
            }

            $response = Http::withHeaders([
                'Origin' => 'https://motohub.jp',
                'Referer' => 'https://motohub.jp',
                'User-Agent' => 'MotoHub',
            ])->timeout(self::TIMEOUT)->get(self::RAKUTEN_URL, $params);

            if (! $response->successful()) {
                // 握りつぶさない。429（レート制限）を「0件」と誤読すると、
                // 検索語が悪いのかAPIが断ったのかが区別できなくなる。
                $this->lastErrors['rakuten'] = 'HTTP '.$response->status();
                Log::warning('ProductSearchService rakuten がエラー応答', [
                    'status' => $response->status(),
                    'keyword' => $keyword,
                    // 400 の原因（不正な検索語・パラメータ等）は本文にしか出ないため残す。
                    // 画面には出さない（測定の出力を汚さないため）。
                    'body' => mb_substr($response->body(), 0, 200),
                ]);

                return [];
            }

            return collect($response->json('Items') ?? [])->map(function ($wrapper) use ($withDescription) {
                $item = $wrapper['Item'] ?? $wrapper;

                return [
                    'mall' => 'rakuten',
                    'product_id' => (string) ($item['itemCode'] ?? ''),
                    'name' => (string) ($item['itemName'] ?? ''),
                    'image' => (string) ($item['mediumImageUrls'][0]['imageUrl'] ?? ''),
                    'price' => (int) ($item['itemPrice'] ?? 0),
                    'url' => (string) ($item['itemUrl'] ?? ''),
                    'shop' => (string) ($item['shopName'] ?? ''),
                    // 適合抽出の測定用。既定では含めない（キャプションは数KBあり通常用途では不要）。
                    // PartsController::formatRakutenItems と同じく strip_tags のみ通す。
                    ...($withDescription ? ['description' => strip_tags((string) ($item['itemCaption'] ?? ''))] : []),
                ];
            })->filter(fn ($i) => $i['name'] !== '' && $i['url'] !== '')->values()->all();
        } catch (\Throwable $e) {
            $this->lastErrors['rakuten'] = '例外: '.$e->getMessage();
            Log::warning('ProductSearchService rakuten failed: '.$e->getMessage(), ['keyword' => $keyword]);

            return [];
        }
    }

    /**
     * Yahoo検索（失敗時は []）。URL は ValueCommerce で自前ラップしてアフィリエイト化する。
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchYahoo(string $keyword, int $hits): array
    {
        $clientId = config('services.yahoo_shopping.client_id');
        if (! $clientId) {
            return [];
        }

        try {
            // sort は指定しない＝Yahoo 既定の関連度（-score）順。
            // ★以前の '+price'（価格昇順）は最安の無関係品を上位に押し上げ、おすすめ枠を汚染していた。
            $response = Http::timeout(self::TIMEOUT)->get(self::YAHOO_URL, [
                'appid' => $clientId,
                'query' => $keyword,
                'results' => $hits,
            ]);

            if (! $response->successful()) {
                // 楽天と同じ理由で握りつぶさない（0件と取得失敗を区別できるようにする）。
                $this->lastErrors['yahoo'] = 'HTTP '.$response->status();
                Log::warning('ProductSearchService yahoo がエラー応答', [
                    'status' => $response->status(),
                    'keyword' => $keyword,
                ]);

                return [];
            }

            $vcSid = config('services.yahoo_shopping.valuecommerce_sid');
            $vcPid = config('services.yahoo_shopping.valuecommerce_pid');

            return collect($response->json('hits') ?? [])->map(function ($item) use ($vcSid, $vcPid) {
                $url = (string) ($item['url'] ?? '');
                if ($vcSid && $vcPid && $url !== '') {
                    $url = 'https://ck.jp.ap.valuecommerce.com/servlet/referral?sid='.$vcSid
                        .'&pid='.$vcPid
                        .'&vc_url='.urlencode($url);
                }

                return [
                    'mall' => 'yahoo',
                    'product_id' => (string) ($item['code'] ?? ''),
                    'name' => (string) ($item['name'] ?? ''),
                    'image' => (string) ($item['image']['medium'] ?? $item['image']['small'] ?? ''),
                    'price' => (int) ($item['price'] ?? 0),
                    'url' => $url,
                    'shop' => (string) ($item['seller']['name'] ?? ''),
                ];
            })->filter(fn ($i) => $i['name'] !== '' && $i['url'] !== '')->values()->all();
        } catch (\Throwable $e) {
            $this->lastErrors['yahoo'] = '例外: '.$e->getMessage();
            Log::warning('ProductSearchService yahoo failed: '.$e->getMessage(), ['keyword' => $keyword]);

            return [];
        }
    }
}
