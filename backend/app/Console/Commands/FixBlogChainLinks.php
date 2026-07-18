<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BlogPost;
use Illuminate\Console\Command;

/**
 * ブログ本文のチェーン横断ページへのリンク切れ typo を一括修正。
 * `/blog/shops/chain/...`（`/blog/` が余計＝404）→ 正しい `/shops/chain/...`。
 * 冪等（再実行しても既に正しいものは変わらない）。honda-dream 以外の同 typo も拾う。
 */
final class FixBlogChainLinks extends Command
{
    protected $signature = 'blog:fix-chain-links {--dry-run : 変更せず対象記事数だけ表示}';

    protected $description = 'ブログ本文の /blog/shops/chain/ typo を /shops/chain/ に一括修正';

    private const BAD = '/blog/shops/chain/';

    private const GOOD = '/shops/chain/';

    public function handle(): int
    {
        $posts = BlogPost::where('body', 'like', '%'.self::BAD.'%')->get();

        if ($posts->isEmpty()) {
            $this->info('該当なし（typo リンクはありません）。');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("【ドライラン】{$posts->count()} 記事に typo リンクがあります（変更しません）。");
            $posts->each(fn (BlogPost $p) => $this->line("  - [{$p->slug}] {$p->title}"));

            return self::SUCCESS;
        }

        $fixed = 0;
        foreach ($posts as $post) {
            $new = str_replace(self::BAD, self::GOOD, (string) $post->body);
            if ($new !== $post->body) {
                $post->body = $new;
                $post->save();
                $fixed++;
                $this->line("  修正: [{$post->slug}] {$post->title}");
            }
        }

        $this->info("完了: {$fixed} 記事のリンクを修正しました。");

        return self::SUCCESS;
    }
}
