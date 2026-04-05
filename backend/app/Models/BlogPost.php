<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BlogPost extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'author_id',
        'title',
        'slug',
        'body',
        'excerpt',
        'eyecatch_image',
        'series_id',
        'series_order',
        'status',
        'published_at',
        'reading_time_minutes',
        'meta_title',
        'meta_description',
        'og_image',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'series_order' => 'integer',
        'reading_time_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (BlogPost $post) {
            // Zennスタイル: ランダム英数字14桁のslug自動生成
            if (empty($post->slug)) {
                $post->slug = Str::lower(Str::random(14));
            }

            // 読了時間の自動算出（日本語: 約500文字/分）
            $post->reading_time_minutes = max(1, (int) ceil(mb_strlen(strip_tags($post->body)) / 500));

            // 抜粋の自動生成（未入力時は本文先頭150文字、Markdown記号除去）
            if (empty($post->excerpt) && !empty($post->body)) {
                $post->excerpt = Str::limit(self::stripMarkdown($post->body), 150);
            }
        });

        static::updating(function (BlogPost $post) {
            if ($post->isDirty('body')) {
                $post->reading_time_minutes = max(1, (int) ceil(mb_strlen(strip_tags($post->body)) / 500));

                if (empty($post->excerpt)) {
                    $post->excerpt = Str::limit(self::stripMarkdown($post->body), 150);
                }
            }
        });
    }

    // ---- Relationships ----

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function series(): BelongsTo
    {
        return $this->belongsTo(BlogSeries::class, 'series_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag');
    }

    // ---- Scopes ----

    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->where('published_at', '<=', now());
    }

    public function scopeScheduled($query)
    {
        return $query->where('status', 'scheduled')
                     ->where('published_at', '<=', now());
    }

    /**
     * Markdown記号を除去してプレーンテキストにする
     */
    public static function stripMarkdown(string $text): string
    {
        $text = strip_tags($text);
        $text = preg_replace('/^#+\s*/m', '', $text);           // # 見出し
        $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text); // **太字**
        $text = preg_replace('/\*([^*]+)\*/', '$1', $text);     // *斜体*
        $text = preg_replace('/`{3}[\s\S]*?`{3}/m', '', $text); // ```コードブロック```
        $text = preg_replace('/`([^`]+)`/', '$1', $text);       // `インラインコード`
        $text = preg_replace('/^[-*]\s+/m', '', $text);         // リスト記号
        $text = preg_replace('/^\d+\.\s+/m', '', $text);        // 番号付きリスト
        $text = preg_replace('/^>\s*/m', '', $text);             // 引用
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text); // [リンク](url)
        $text = preg_replace('/^→\s*/m', '', $text);            // →リンク行
        $text = preg_replace('/\|/m', '', $text);               // テーブル|
        $text = preg_replace('/^-{3,}$/m', '', $text);          // --- 水平線
        $text = preg_replace('/\s+/', ' ', $text);              // 連続空白を1つに
        return trim($text);
    }

    // ---- Helpers ----

    public function isPublished(): bool
    {
        return $this->status === 'published' && $this->published_at <= now();
    }

    public function getImageUrl(?string $path): ?string
    {
        if (empty($path)) {
            return null;
        }

        return rtrim(config('blog.image_base_url', '/storage'), '/') . '/' . ltrim($path, '/');
    }

    public function getEyecatchUrl(): ?string
    {
        return $this->getImageUrl($this->eyecatch_image);
    }

    public function getOgImageUrl(): ?string
    {
        return $this->getImageUrl($this->og_image) ?? $this->getEyecatchUrl();
    }
}
