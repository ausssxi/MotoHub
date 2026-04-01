<?php

declare(strict_types=1);

namespace App\Services\Bike;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BikeYouTubeService
{
    private const CACHE_TTL = 604800;       // 7日間
    private const ERROR_CACHE_TTL = 86400;  // エラー時24時間

    /**
     * @param string      $query      検索クエリ
     * @param int         $limit      取得件数
     * @param int|null    $modelId    bike_model_id（車種単位キャッシュ用）
     */
    public function fetch(string $query, int $limit = 5, ?int $modelId = null): array
    {
        $apiKey = config('services.youtube.api_key');
        if (!$apiKey) {
            return [];
        }

        // Bot/Crawlerはキャッシュも含めスキップ（クォータ節約）
        $userAgent = request()->userAgent() ?? '';
        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit/i', $userAgent)) {
            return [];
        }

        // bike_model_id があれば車種単位、なければクエリ単位でキャッシュ
        $cacheKey = $modelId
            ? "youtube_model_{$modelId}"
            : 'youtube_' . str($query)->slug();

        // エラーキャッシュがあればAPI呼び出しをスキップ
        $errorCacheKey = "{$cacheKey}_error";
        if (Cache::has($errorCacheKey)) {
            return [];
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($query, $limit, $apiKey, $errorCacheKey) {
            try {
                $response = Http::timeout(5)->get('https://www.googleapis.com/youtube/v3/search', [
                    'part'       => 'snippet',
                    'q'          => $query,
                    'type'       => 'video',
                    'order'      => 'relevance',
                    'maxResults' => $limit,
                    'hl'         => 'ja',
                    'regionCode' => 'JP',
                    'key'        => $apiKey,
                ]);

                if ($response->failed()) {
                    Log::warning('YouTube API error', [
                        'status' => $response->status(),
                        'body'   => $response->json(),
                    ]);
                    Cache::put($errorCacheKey, true, self::ERROR_CACHE_TTL);
                    return [];
                }

                $data = $response->json();

                return collect($data['items'] ?? [])->map(function ($item) {
                    $snippet = $item['snippet'] ?? [];
                    return [
                        'video_id'  => $item['id']['videoId'] ?? '',
                        'title'     => $snippet['title'] ?? '',
                        'channel'   => $snippet['channelTitle'] ?? '',
                        'thumbnail' => $snippet['thumbnails']['high']['url']
                                    ?? $snippet['thumbnails']['medium']['url']
                                    ?? $snippet['thumbnails']['default']['url']
                                    ?? '',
                        'date'      => isset($snippet['publishedAt'])
                                    ? date('Y/m/d', strtotime($snippet['publishedAt']))
                                    : '',
                    ];
                })->filter(fn($v) => !empty($v['video_id']))->values()->all();
            } catch (\Throwable $e) {
                Log::warning('YouTube API exception', ['message' => $e->getMessage()]);
                Cache::put($errorCacheKey, true, self::ERROR_CACHE_TTL);
                return [];
            }
        });
    }
}
