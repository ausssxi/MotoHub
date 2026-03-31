<?php

declare(strict_types=1);

namespace App\Services\Bike;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class BikeYouTubeService
{
    public function fetch(string $query, int $limit = 5): array
    {
        $apiKey = config('services.youtube.api_key');
        if (!$apiKey) {
            return [];
        }

        $slug = str($query)->slug();
        $cacheKey = "youtube_{$slug}";

        return Cache::remember($cacheKey, 86400, function () use ($query, $limit, $apiKey) {
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
            } catch (\Throwable) {
                return [];
            }
        });
    }
}
