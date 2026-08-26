<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CloudflareCacheService
{
    private ?string $zoneId;

    private ?string $apiToken;

    /**
     * 直近のパージ失敗理由（人間が読める形式）。成功時はnull。
     */
    private ?string $lastError = null;

    public function __construct()
    {
        $this->zoneId = config('blog.cloudflare.zone_id');
        $this->apiToken = config('blog.cloudflare.api_token');
    }

    /**
     * 直近のパージ失敗理由を返す（呼び出し側での表示用）。
     */
    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * 指定URLのキャッシュをパージ
     */
    public function purgeUrls(array $urls): bool
    {
        if (! $this->assertConfigured()) {
            return false;
        }

        return $this->send(['files' => array_values($urls)]);
    }

    /**
     * プレフィックスに基づいてキャッシュをパージ（全パージフォールバック）
     */
    public function purgeByPrefix(string $prefix): bool
    {
        if (! $this->assertConfigured()) {
            return false;
        }

        // Cloudflare Freeプランではprefix purgeが使えないため、全パージにフォールバック
        return $this->send(['purge_everything' => true]);
    }

    /**
     * Cloudflare purge_cache APIへ実際にリクエストを送信し、成否を返す。
     * 失敗時は $lastError に理由を格納し、ログにも記録する。
     */
    private function send(array $payload): bool
    {
        $this->lastError = null;
        $endpoint = "https://api.cloudflare.com/client/v4/zones/{$this->zoneId}/purge_cache";

        try {
            $response = Http::withToken($this->apiToken)->post($endpoint, $payload);
        } catch (\Throwable $e) {
            $this->lastError = 'Cloudflare APIへの接続に失敗しました: '.$e->getMessage();
            Log::error('Cloudflare cache purge error', ['message' => $e->getMessage()]);

            return false;
        }

        if ($response->successful() && ($response->json('success') === true)) {
            return true;
        }

        // Cloudflareはエラー詳細を errors[] で返す（例: 認証失敗=10000, ゾーン不正=1003）
        $errors = $response->json('errors') ?? [];
        $summary = collect($errors)
            ->map(fn ($e) => '['.($e['code'] ?? '?').'] '.($e['message'] ?? ''))
            ->implode('; ');

        $this->lastError = sprintf(
            'HTTP %d%s',
            $response->status(),
            $summary !== '' ? ' — '.$summary : ' — '.$response->body()
        );

        Log::warning('Cloudflare cache purge failed', [
            'status' => $response->status(),
            'errors' => $errors,
            'body' => $response->body(),
        ]);

        return false;
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

    public function isConfigured(): bool
    {
        return ! empty($this->zoneId) && ! empty($this->apiToken);
    }

    /**
     * 設定済みか確認し、未設定なら $lastError に不足しているキーを記録する。
     */
    private function assertConfigured(): bool
    {
        $missing = [];
        if (empty($this->zoneId)) {
            $missing[] = 'CLOUDFLARE_ZONE_ID';
        }
        if (empty($this->apiToken)) {
            $missing[] = 'CLOUDFLARE_API_TOKEN';
        }

        if (! empty($missing)) {
            $this->lastError = 'Cloudflare設定が未設定です（.envに不足）: '.implode(', ', $missing);
            Log::warning('Cloudflare cache purge skipped: not configured', ['missing' => $missing]);

            return false;
        }

        return true;
    }
}
