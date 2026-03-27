<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareCacheService
{
    private ?string $zoneId;
    private ?string $apiToken;

    public function __construct()
    {
        $this->zoneId = config('blog.cloudflare.zone_id');
        $this->apiToken = config('blog.cloudflare.api_token');
    }

    /**
     * 指定URLのキャッシュをパージ
     */
    public function purgeUrls(array $urls): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::withToken($this->apiToken)
                ->post("https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/purge_cache", [
                    'files' => $urls,
                ]);

            if (!$response->successful()) {
                Log::warning('Cloudflare cache purge failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Cloudflare cache purge error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * プレフィックスに基づいてキャッシュをパージ（全パージフォールバック）
     */
    public function purgeByPrefix(string $prefix): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            // Cloudflare Freeプランではprefix purgeが使えないため、全パージにフォールバック
            $response = Http::withToken($this->apiToken)
                ->post("https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/purge_cache", [
                    'purge_everything' => true,
                ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Cloudflare cache purge error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * 記事公開/更新時にキャッシュをパージ
     */
    public function purgePostCache(string $slug): bool
    {
        $baseUrl = rtrim(config('app.url'), '/');
        $urls = [
            "{$baseUrl}/blog",
            "{$baseUrl}/blog/{$slug}",
            "{$baseUrl}/blog/feed",
        ];

        return $this->purgeUrls($urls);
    }

    private function isConfigured(): bool
    {
        return !empty($this->zoneId) && !empty($this->apiToken);
    }
}
