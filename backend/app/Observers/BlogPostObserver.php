<?php

namespace App\Observers;

use App\Models\BlogPost;
use App\Services\CloudflareCacheService;
use Illuminate\Support\Facades\Storage;

class BlogPostObserver
{
    /**
     * 記事の更新時: 読了時間と抜粋の再計算 + キャッシュパージ
     */
    public function updated(BlogPost $post): void
    {
        // OGP画像キャッシュを削除（タイトル変更時に再生成させる）
        $this->deleteOgpCache($post->slug);

        // 公開記事のキャッシュパージ
        if ($post->status === 'published') {
            $this->purgeCacheIfConfigured($post->slug);
        }
    }

    /**
     * 記事の削除時: キャッシュパージ + OGP画像削除
     */
    public function deleted(BlogPost $post): void
    {
        $this->deleteOgpCache($post->slug);

        if ($post->status === 'published') {
            $this->purgeCacheIfConfigured($post->slug);
        }
    }

    private function deleteOgpCache(string $slug): void
    {
        Storage::disk('public')->delete("ogp/{$slug}.png");
    }

    private function purgeCacheIfConfigured(string $slug): void
    {
        if (config('blog.cloudflare.zone_id')) {
            app(CloudflareCacheService::class)->purgePostCache($slug);
        }
    }
}
