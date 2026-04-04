<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\BikeModelVideo;
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
        // Bot/Crawlerはキャッシュも含めスキップ（クォータ節約）
        $userAgent = request()->userAgent() ?? '';
        if (!empty($userAgent) && preg_match('/bot|crawl|spider|slurp|facebookexternalhit/i', $userAgent)) {
            return [];
        }

        // 1. DB検索（bike_model_idがある場合）
        if ($modelId) {
            $dbVideos = BikeModelVideo::where('bike_model_id', $modelId)
                ->orderBy('sort_order')
                ->get();

            if ($dbVideos->isNotEmpty()) {
                return $dbVideos->map(fn($v) => [
                    'video_id'  => $v->video_id,
                    'title'     => $v->title,
                    'channel'   => $v->channel_name,
                    'thumbnail' => $v->thumbnail_url,
                    'date'      => $v->published_at?->format('Y/m/d') ?? '',
                ])->values()->all();
            }
        }

        // 2. DBにない場合はAPI呼び出し（フォールバック）
        return $this->fetchFromApi($query, $limit, $modelId);
    }

    private function fetchFromApi(string $query, int $limit, ?int $modelId): array
    {
        $apiKey = config('services.youtube.api_key');
        if (!$apiKey) {
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

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($query, $limit, $apiKey, $errorCacheKey, $modelId) {
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

                $videos = collect($data['items'] ?? [])->map(function ($item) {
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
                        'published_at' => $snippet['publishedAt'] ?? null,
                    ];
                })->filter(fn($v) => !empty($v['video_id']))->values();

                // API経由で取得した場合はDBにも保存
                if ($modelId && $videos->isNotEmpty()) {
                    foreach ($videos as $index => $v) {
                        BikeModelVideo::updateOrCreate(
                            [
                                'bike_model_id' => $modelId,
                                'video_id' => $v['video_id'],
                            ],
                            [
                                'title' => $v['title'],
                                'thumbnail_url' => $v['thumbnail'],
                                'channel_name' => $v['channel'],
                                'published_at' => $v['published_at']
                                    ? \Carbon\Carbon::parse($v['published_at'])
                                    : null,
                                'sort_order' => $index,
                            ]
                        );
                    }
                }

                return $videos->map(fn($v) => collect($v)->except('published_at')->all())->all();
            } catch (\Throwable $e) {
                Log::warning('YouTube API exception', ['message' => $e->getMessage()]);
                Cache::put($errorCacheKey, true, self::ERROR_CACHE_TTL);
                return [];
            }
        });
    }
}
