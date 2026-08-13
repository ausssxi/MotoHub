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
     * 楽天への最小リクエスト間隔（秒）。
     *
     * 本番の429の本文は {"statusCode":429,"message":"Rate limit is exceeded. Try again in 1 seconds."}
     * ＝日次クォータではなく秒単位の制限。1.0ちょうどだと境界で弾かれるため少し余裕を持たせる。
     */
    private const RAKUTEN_MIN_INTERVAL = 1.1;

    /**
     * 順番待ちの上限（秒）。これを超えるならAPIを叩かずに空を返す。
     * 車種ページのタイヤ枠は render の中で同期的に呼ばれるため、待ち行列が伸びると
     * ページの応答時間がそのまま伸びる。商品枠が消えるほうが、ページが詰まるよりましと判断した。
     */
    private const RAKUTEN_GATE_MAX_WAIT = 3.0;

    /** 「次に楽天を叩いてよい時刻」（UNIX秒・float）を置く共有キー。 */
    private const GATE_NEXT_KEY = 'parts:rakuten:next_call_at';

    /** 上のキーを read-modify-write するあいだだけ持つ短命ミューテックス。 */
    private const GATE_MUTEX_KEY = 'parts:rakuten:gate_mutex';

    /**
     * 429 を受けたあとにモール単位で呼び出しを止める時間（秒）。
     *
     * 楽天の指示は「1秒後に再試行」だが、429が出ている時点で他ワーカーも詰まっているので、
     * 1秒では足りない。かといって長すぎると商品枠が消えたままになるため30秒とした。
     * 間隔制御（上）が効いていれば本来ここには来ない。来たら「制御しきれていない」印。
     */
    private const BREAKER_TTL = 30;

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
     * $respectBreaker=false のときだけ、429後の休止（ブレーカー）を無視して叩きにいく。
     * 明示的な再試行（fitment:probe のバックオフ）専用。画面からの呼び出しは既定の true のまま。
     *
     * @return array<int, array{mall:string, product_id:string, name:string, image:string, price:int, url:string, shop:string, description?:string}>
     */
    public function searchProducts(string $keyword, int $hits = 20, bool $withDescription = false, bool $rakutenOnly = false, bool $respectBreaker = true): array
    {
        // 1文字トークンの結合はここ（サービスの入口）で行う。全呼び出し元に効かせるため、
        // かつキャッシュキーも正規化後の文字列で引くため（"axis z" と "axisz" を同じ枠にする）。
        $keyword = $this->sanitizeKeyword($keyword);
        if (mb_strlen($keyword) < 2) {
            // 楽天は1文字の検索語を受け付けない（400 keyword is not valid）。叩かずに空を返す。
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
            $this->searchRakuten($keyword, $hits, $withDescription, $respectBreaker),
            // $rakutenOnly=true のときは Yahoo を叩かない。適合抽出の測定は楽天だけを集計するため、
            // 呼ぶだけ無駄なリクエストになり、楽天のレート制限に当たりやすくなる。
            $rakutenOnly ? [] : $this->searchYahoo($keyword, $hits, $respectBreaker),
        );

        // キャッシュの焼き方は3通りに分ける。
        //  1) エラー無し          → 通常TTL
        //  2) エラーあるが結果あり → 短いTTL（片方のモールだけ失敗。使えるデータはある）
        //  3) エラーありで結果空   → 焼かない
        //     焼くと「取得失敗」を「0件」として配り続けるうえ、呼び出し側の再試行が
        //     キャッシュに阻まれて必ず空を返すようになる（再試行が無意味になる）。
        //     ★ここで焼かないぶんの歯止めは「空配列を焼く」ではなくブレーカー（休止フラグ）が持つ。
        //       結果をキャッシュするやり方は「取得失敗」を「0件」として配ってしまうため使わない。
        if ($this->lastErrors === []) {
            Cache::put($cacheKey, $results, self::CACHE_TTL);
        } elseif ($results !== []) {
            Cache::put($cacheKey, $results, self::CACHE_TTL_ON_ERROR);
        }

        return $results;
    }

    /**
     * 検索語を楽天が受け付ける形に整える。
     *
     * 楽天は1文字のトークンを含む検索語を 400 で弾く
     * （本番実測: keyword="axis z バッテリー" → {"error":"wrong_parameter","error_description":"keyword is not valid"}）。
     *
     * 1文字トークンは「除去」ではなく「隣へ結合」する。"axis z" の "z" は車種名の一部であり、
     * 落とすと AXIS（別車種）の商品が返ってしまうため。結合すれば "axisz" となり、
     * 楽天の全文検索は表記ゆれを吸収するので該当商品に届く。
     *
     * 結合先は原則ひとつ前のトークン。先頭が1文字のときだけ次のトークンへ寄せる（"z axis" → "zaxis"）。
     * 判定は文字種を問わない（"PCX 用 オイル" → "PCX用 オイル"）。日本語1文字だけ別扱いにする根拠が
     * 実測に無いため、規則を分けずに揃えている。
     */
    private function sanitizeKeyword(string $keyword): string
    {
        $tokens = preg_split('/[\s\x{3000}]+/u', trim($keyword), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $out = [];
        foreach ($tokens as $token) {
            if (mb_strlen($token) === 1 && $out !== []) {
                $out[count($out) - 1] .= $token;

                continue;
            }
            $out[] = $token;
        }

        // 先頭が1文字のまま残った場合は次のトークンへ結合する。
        if (count($out) >= 2 && mb_strlen($out[0]) === 1) {
            $out[1] = $out[0].$out[1];
            array_shift($out);
        }

        return implode(' ', $out);
    }

    /**
     * 楽天を叩く「枠」を1つ取る。取れたら true、待ち時間の上限を超えたら false。
     *
     * ■ 方式: 共有キャッシュに「次に叩いてよい時刻」を持ち、Cache::add() の原子性で更新を直列化する。
     *
     * ■ なぜ Cache::lock() ではなくこの方式か:
     *   Cache::lock() の TTL は整数秒しか指定できず、必要な 1.1 秒を表現できない。
     *   TTL=2 に丸めるとスループットが半分（0.5 req/s）になり、TTL=1 だと楽天の秒境界に張り付いて
     *   429 が残る。時刻を持てば秒未満の間隔をそのまま表現できる。
     *
     * ■ なぜ Cache::lock() ではなく Cache::add() をミューテックスに使うか:
     *   Cache::lock() は LockProvider を実装していないストアで BadMethodCallException を投げる。
     *   Repository::add() は全ストアで使え、file ストアでは LockableFile の flock で排他されるため
     *   （FileStore::add）、php-fpm ワーカー間で原子的に効く。本番は CACHE_STORE=file。
     *   ミューテックスは TTL 1秒。プロセスが握ったまま死んでも1秒で自動的に解放される。
     */
    private function acquireRakutenSlot(): bool
    {
        $deadline = microtime(true) + self::RAKUTEN_GATE_MAX_WAIT;

        while (true) {
            $waitUntil = null;

            if (Cache::add(self::GATE_MUTEX_KEY, 1, 1)) {
                try {
                    $now = microtime(true);
                    $nextAt = (float) Cache::get(self::GATE_NEXT_KEY, 0.0);

                    if ($nextAt <= $now) {
                        // いまの枠は空いている。取ったうえで次の枠を予約しておく。
                        Cache::put(self::GATE_NEXT_KEY, $now + self::RAKUTEN_MIN_INTERVAL, 60);

                        return true;
                    }

                    $waitUntil = $nextAt;
                } finally {
                    Cache::forget(self::GATE_MUTEX_KEY);
                }
            }

            // ミューテックスを取れなかった＝他ワーカーが更新中。少しだけ空けて取り直す。
            $waitUntil ??= microtime(true) + 0.02;

            if ($waitUntil >= $deadline) {
                return false;
            }

            $sleepMicros = (int) (($waitUntil - microtime(true)) * 1_000_000);
            if ($sleepMicros > 0) {
                usleep($sleepMicros);
            }
        }
    }

    private function breakerKey(string $mall): string
    {
        return 'parts:breaker:'.$mall;
    }

    /**
     * 429 を受けたモールを BREAKER_TTL 秒だけ休止させる。
     * 立てるときだけログに残す（休止中のスキップまでログすると、抑えたはずの行数が戻ってしまう）。
     */
    private function tripBreaker(string $mall, string $keyword): void
    {
        Cache::put($this->breakerKey($mall), true, self::BREAKER_TTL);
        Log::warning("ProductSearchService {$mall} を".self::BREAKER_TTL.'秒休止します（レート制限）', [
            'keyword' => $keyword,
        ]);
    }

    /**
     * 楽天検索（失敗時は []）。affiliateId を渡すと itemUrl がアフィリエイトURLになる。
     *
     * $withDescription=true のときだけ itemCaption を 'description' として付与する。
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchRakuten(string $keyword, int $hits, bool $withDescription = false, bool $respectBreaker = true): array
    {
        $appId = config('services.rakuten.app_id');
        $accessKey = config('services.rakuten.access_key');
        if (! $appId || ! $accessKey) {
            return [];
        }

        // 休止中はAPIを叩かず空を返す。結果はキャッシュしない（休止が明けたら普通に取り直す）。
        if ($respectBreaker && Cache::get($this->breakerKey('rakuten'), false)) {
            $this->lastErrors['rakuten'] = '休止中（429を受けたため'.self::BREAKER_TTL.'秒間の休止）';

            return [];
        }

        // 間隔制御はブレーカーを迂回する呼び出しにも効かせる。429を出さないための本体はこちらで、
        // ブレーカーは「それでも出てしまったとき」の後始末にすぎないため。
        if (! $this->acquireRakutenSlot()) {
            $this->lastErrors['rakuten'] = '間隔制御の順番待ちが'.self::RAKUTEN_GATE_MAX_WAIT.'秒を超えたため中止';

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

                // 休止させるのは429だけ。400（検索語が不正）は休んでも直らないし、
                // 検索語ごとの問題なので他の検索まで止めてしまうと害のほうが大きい。
                if ($response->status() === 429) {
                    $this->tripBreaker('rakuten', $keyword);
                }

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
    private function searchYahoo(string $keyword, int $hits, bool $respectBreaker = true): array
    {
        $clientId = config('services.yahoo_shopping.client_id');
        if (! $clientId) {
            return [];
        }

        // ブレーカーはモール単位。楽天が休止していても Yahoo は叩く（逆も同じ）。
        // なお間隔制御は楽天だけに入れている。実測で429が出ているのが楽天のみのため。
        if ($respectBreaker && Cache::get($this->breakerKey('yahoo'), false)) {
            $this->lastErrors['yahoo'] = '休止中（429を受けたため'.self::BREAKER_TTL.'秒間の休止）';

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

                if ($response->status() === 429) {
                    $this->tripBreaker('yahoo', $keyword);
                }

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
