<?php

namespace App\Services;

use App\Models\BlogPost;
use Illuminate\Support\Collection;

class BlogRelatedPostService
{
    /**
     * 関連記事を取得
     * 1. 同じタグを持つ記事をスコアリング（共通タグ数 = スコア）
     * 2. 同シリーズの記事は除外
     * 3. スコア上位N件
     * 4. マッチ0件の場合は最新記事をフォールバック
     */
    public function getRelatedPosts(BlogPost $post, ?int $limit = null): Collection
    {
        $limit = $limit ?? config('blog.related_posts_count', 3);
        $tagIds = $post->tags->pluck('id')->toArray();

        if (!empty($tagIds)) {
            $related = BlogPost::published()
                ->where('blog_posts.id', '!=', $post->id)
                ->when($post->series_id, function ($q) use ($post) {
                    $q->where(function ($q2) use ($post) {
                        $q2->whereNull('series_id')
                           ->orWhere('series_id', '!=', $post->series_id);
                    });
                })
                ->whereHas('tags', function ($q) use ($tagIds) {
                    $q->whereIn('blog_tags.id', $tagIds);
                })
                ->withCount(['tags as score' => function ($q) use ($tagIds) {
                    $q->whereIn('blog_tags.id', $tagIds);
                }])
                ->orderByDesc('score')
                ->orderByDesc('published_at')
                ->limit($limit)
                ->get();

            if ($related->isNotEmpty()) {
                return $related;
            }
        }

        // フォールバック: 最新記事
        return BlogPost::published()
            ->where('id', '!=', $post->id)
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }
}
