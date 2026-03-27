<?php

namespace App\Observers;

use App\Models\BlogPost;
use App\Services\CloudflareCacheService;
use Illuminate\Support\Str;

class BlogPostObserver
{
    /**
     * 記事の更新時: 読了時間と抜粋の再計算 + キャッシュパージ
     */
    public function updated(BlogPost $post): void
    {
        // 公開記事のキャッシュパージ
        if ($post->status === 'published') {
            $this->purgeCacheIfConfigured($post->slug);
        }
    }

    /**
     * 記事の削除時: キャッシュパージ
     */
    public function deleted(BlogPost $post): void
    {
        if ($post->status === 'published') {
            $this->purgeCacheIfConfigured($post->slug);
        }
    }

    private function purgeCacheIfConfigured(string $slug): void
    {
        if (config('blog.cloudflare.zone_id')) {
            app(CloudflareCacheService::class)->purgePostCache($slug);
        }
    }
}
