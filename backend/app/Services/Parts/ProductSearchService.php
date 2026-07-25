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

    private const TIMEOUT = 8;

    /**
     * キーワードで楽天 + Yahoo を検索し、正規化した商品候補を返す。
     * どのモールが落ちても例外は投げず、取れた分だけ返す（最悪は空配列）。
     *
     * @return array<int, array{mall:string, product_id:string, name:string, image:string, price:int, url:string, shop:string}>
     */
    public function searchProducts(string $keyword, int $hits = 20): array
    {
        $keyword = trim($keyword);
        if ($keyword === '') {
            return [];
        }
        $hits = max(1, min($hits, 30));

        $cacheKey = 'garage_product_search_'.md5($keyword.'_'.$hits);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($keyword, $hits) {
            // 各モールAPIの関連度順を尊重してマージ（楽天→Yahoo）。
            // ★価格昇順ソートは廃止する：Yahoo はジャンル絞りが無く、最安の無関係品
            //   （チェーンクリップ¥5・ドレンパッキン¥15 等）が上位に浮上し、楽天の良質な該当商品を
            //   押しのけて「おすすめ商品」枠を汚染していた（本番のオイル/バッテリー枠で誤表示が発生）。
            //   関連度順なら各モールが該当商品を上位に返すため、先頭 slice がそのまま良質候補になる。
            return array_merge(
                $this->searchRakuten($keyword, $hits),
                $this->searchYahoo($keyword, $hits),
            );
        });
    }

    /**
     * 楽天検索（失敗時は []）。affiliateId を渡すと itemUrl がアフィリエイトURLになる。
     *
     * @return array<int, array<string, mixed>>
     */
    private function searchRakuten(string $keyword, int $hits): array
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
                return [];
            }

            return collect($response->json('Items') ?? [])->map(function ($wrapper) {
                $item = $wrapper['Item'] ?? $wrapper;

                return [
                    'mall' => 'rakuten',
                    'product_id' => (string) ($item['itemCode'] ?? ''),
                    'name' => (string) ($item['itemName'] ?? ''),
                    'image' => (string) ($item['mediumImageUrls'][0]['imageUrl'] ?? ''),
                    'price' => (int) ($item['itemPrice'] ?? 0),
                    'url' => (string) ($item['itemUrl'] ?? ''),
                    'shop' => (string) ($item['shopName'] ?? ''),
                ];
            })->filter(fn ($i) => $i['name'] !== '' && $i['url'] !== '')->values()->all();
        } catch (\Throwable $e) {
            Log::warning('ProductSearchService rakuten failed: '.$e->getMessage());

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
            Log::warning('ProductSearchService yahoo failed: '.$e->getMessage());

            return [];
        }
    }
}
