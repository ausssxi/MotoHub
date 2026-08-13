<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\BikeModel;
use App\Services\Parts\PartsCodeExtractor;
use App\Support\RakutenRateGate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BikePartsService
{
    private const API_URL = 'https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20220601';

    private const CACHE_TTL = 86400; // 24時間（fetchFlat用）

    // カテゴリ別パーツ(v2)のTTL。parts:refresh のローリング取得で全在庫車種を
    // カバーしきる間キャッシュが生き続けるよう長め。空結果は別途24時間で再試行。
    private const PARTS_CACHE_TTL = 604800; // 7日

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
     * 8カテゴリ直列fetch＋sleep。render pathからは呼ばない（parts:refresh専用）。
     *
     * @return array<string, array{name: string, items: array}>
     */
    public function refreshForModel(BikeModel $model): array
    {
        $appId = config('services.rakuten.app_id');
        $accessKey = config('services.rakuten.access_key');

        if (! $appId || ! $accessKey) {
            return [];
        }

        $affiliateId = config('services.rakuten.affiliate_id');
        $result = [];

        foreach (self::CATEGORIES as $categoryName => $categoryKey) {
            if ($categoryKey === 'other') {
                $keyword = 'バイク '.$model->name;
                $hits = 6;
            } else {
                $keyword = $model->name.' '.$categoryName;
                $hits = 4;
            }

            $items = $this->searchRakuten($appId, $accessKey, $affiliateId, $keyword, $hits);

            if (! empty($items)) {
                $result[$categoryKey] = [
                    'name' => $categoryName,
                    'items' => $items,
                ];
            }

            // かつては sleep(1) でカテゴリ間隔を空けていたが削除した。
            // 楽天呼び出しは共有ゲート（RakutenRateGate）を通り、ゲートが全経路で
            // 1.1秒以上の間隔を保証する。sleep(1) はそれより短く、ゲート導入後は
            // 律速にならないうえ、ゲートの待機と二重に効いて無駄に遅くなるだけのため。
        }

        // 空結果（取得失敗/在庫無し）は24時間で再試行。取得できた分は7日保持。
        $ttl = empty($result) ? self::CACHE_TTL : self::PARTS_CACHE_TTL;
        Cache::put(self::cacheKey($model), $result, $ttl);

        return $result;
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

        try {
            return Cache::remember("parts:bike_model:{$model->id}:flat", self::CACHE_TTL, function () use ($model, $appId, $accessKey, $limit) {
                $affiliateId = config('services.rakuten.affiliate_id');

                return $this->searchRakuten($appId, $accessKey, $affiliateId, 'バイク '.$model->name, $limit);
            });
        } catch (\Throwable $e) {
            // キャッシュ層など searchRakuten の外側で落ちた場合の保険。無言にはしない。
            Log::warning('BikePartsService fetchFlat failed: '.$e->getMessage(), ['bike_model_id' => $model->id]);

            return [];
        }
    }

    /**
     * 楽天API呼び出し共通処理
     */
    private function searchRakuten(string $appId, string $accessKey, ?string $affiliateId, string $keyword, int $hits): array
    {
        $gate = app(RakutenRateGate::class);

        // 楽天のレート枠は全経路の共有。休止中はここでも叩かない（迂回はしない）。
        if ($gate->isPaused()) {
            Log::warning('BikePartsService rakuten がエラー応答', [
                'status' => 0,
                'keyword' => $keyword,
                'body' => $gate->pausedReason(),
            ]);

            return [];
        }

        // 間隔制御の枠を取れなければ叩かずに空を返す（CLI=5秒/Web=0.5秒はゲートが判定）。
        if (! $gate->acquireSlot()) {
            Log::warning('BikePartsService rakuten がエラー応答', [
                'status' => 0,
                'keyword' => $keyword,
                'body' => $gate->waitExceededReason(),
            ]);

            return [];
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
            ])->timeout(5)->get(self::API_URL, $params);

            if (! $response->successful()) {
                // かつては失敗を無言で [] にしていた。429を「0件」と誤読すると障害が見えないため、
                // ProductSearchService と同じ書式（status/keyword/本文先頭200文字）でログに残す。
                $gate->logErrorResponse('BikePartsService', $response->status(), $keyword, $response->body());

                if ($response->status() === 429) {
                    $gate->pause($keyword, 'BikePartsService');
                }

                return [];
            }

            $data = $response->json();

            return collect($data['Items'] ?? [])->map(function ($wrapper) {
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
        } catch (\Throwable $e) {
            Log::warning('BikePartsService rakuten failed: '.$e->getMessage(), ['keyword' => $keyword]);

            return [];
        }
    }
}
