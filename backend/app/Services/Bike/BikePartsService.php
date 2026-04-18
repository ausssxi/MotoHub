<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\BikeModel;
use App\Services\Parts\PartsCodeExtractor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BikePartsService
{
    private const API_URL = 'https://openapi.rakuten.co.jp/ichibams/api/IchibaItem/Search/20220601';
    private const CACHE_TTL = 86400; // 24時間

    private const CATEGORIES = [
        'バッテリー' => 'battery',
        'タイヤ'     => 'tire',
        'チェーン'   => 'chain',
        'ブレーキパッド' => 'brake',
        'オイル'     => 'oil',
        'プラグ'     => 'plug',
        'マフラー'   => 'muffler',
        'その他'     => 'other',
    ];

    /**
     * カテゴリ別にパーツを取得（model_detail用）
     *
     * @return array<string, array{name: string, items: array}>
     */
    public function fetchForModel(BikeModel $model): array
    {
        $appId = config('services.rakuten.app_id');
        $accessKey = config('services.rakuten.access_key');

        if (!$appId || !$accessKey) {
            return [];
        }

        try {
            return Cache::remember("parts:bike_model:{$model->id}:v2", self::CACHE_TTL, function () use ($model, $appId, $accessKey) {
                $affiliateId = config('services.rakuten.affiliate_id');
                $result = [];

                foreach (self::CATEGORIES as $categoryName => $categoryKey) {
                    if ($categoryKey === 'other') {
                        $keyword = 'バイク ' . $model->name;
                        $hits = 6;
                    } else {
                        $keyword = $model->name . ' ' . $categoryName;
                        $hits = 4;
                    }

                    $items = $this->searchRakuten($appId, $accessKey, $affiliateId, $keyword, $hits);

                    if (!empty($items)) {
                        $result[$categoryKey] = [
                            'name'  => $categoryName,
                            'items' => $items,
                        ];
                    }

                    // レート制限対策
                    sleep(1);
                }

                return $result;
            });
        } catch (\Throwable) {
            return [];
        }
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

        if (!$appId || !$accessKey) {
            return [];
        }

        try {
            return Cache::remember("parts:bike_model:{$model->id}:flat", self::CACHE_TTL, function () use ($model, $appId, $accessKey, $limit) {
                $affiliateId = config('services.rakuten.affiliate_id');
                return $this->searchRakuten($appId, $accessKey, $affiliateId, 'バイク ' . $model->name, $limit);
            });
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * 楽天API呼び出し共通処理
     */
    private function searchRakuten(string $appId, string $accessKey, ?string $affiliateId, string $keyword, int $hits): array
    {
        $params = [
            'applicationId' => $appId,
            'accessKey'     => $accessKey,
            'keyword'       => $keyword,
            'hits'          => $hits,
            'sort'          => '-reviewCount',
            'format'        => 'json',
        ];

        if ($affiliateId) {
            $params['affiliateId'] = $affiliateId;
        }

        try {
            $response = Http::withHeaders([
                'Origin'     => 'https://motohub.jp',
                'Referer'    => 'https://motohub.jp',
                'User-Agent' => 'MotoHub',
            ])->timeout(5)->get(self::API_URL, $params);

            if ($response->failed()) {
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
                    'name'           => $item['itemName'] ?? '',
                    'price'          => $item['itemPrice'] ?? 0,
                    'image'          => $item['mediumImageUrls'][0]['imageUrl'] ?? '',
                    'url'            => $item['itemUrl'] ?? '',
                    'jan_code'       => $codes['jan'],
                    'part_number'    => $codes['partNumber'],
                    'shopName'       => $item['shopName'] ?? '',
                    'reviewCount'    => $item['reviewCount'] ?? 0,
                    'reviewAverage'  => $item['reviewAverage'] ?? 0,
                    'postageFlag'    => $item['postageFlag'] ?? 0,
                    'pointRate'      => $item['pointRate'] ?? 1,
                ];
            })->all();
        } catch (\Throwable) {
            return [];
        }
    }
}
