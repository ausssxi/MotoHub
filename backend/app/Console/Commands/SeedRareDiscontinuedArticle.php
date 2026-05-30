<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * 「絶版・希少バイクの中古まとめ10選」ブログ記事を本番DBへ安全に投入する。
 *
 * 本文Markdownは database/content/rare-discontinued-used-bikes-2026.md に同梱
 * （手動コピペによる崩れを避けるため、リポジトリ管理の実ファイルを正本とする）。
 *
 * 安全設計:
 *  - slug で冪等（updateOrCreate）。再実行しても重複作成しない。
 *  - 新規作成は必ず status=draft（公開は管理画面で人が最終確認してから行う）。
 *  - 既存記事がある場合は本文・メタのみ更新し、status / published_at は維持
 *    （誤って公開を取り消したり再公開したりしない）。
 *  - author_id は本番ユーザーを動的解決（dev の id をハードコードしない）。
 *    --author=<id|email> 指定可。未指定なら is_admin の最若番、無ければ最若番ユーザー。
 *
 * 使い方（本番コンテナ内）:
 *   php artisan blog:seed-rare-article --dry-run        # 何が起きるか確認のみ
 *   php artisan blog:seed-rare-article                  # draftで作成/更新
 *   php artisan blog:seed-rare-article --author=editor@example.com
 *
 * 公開後は管理画面で published 化 → `php artisan sitemap:generate` でsitemap収録+IndexNow。
 */
final class SeedRareDiscontinuedArticle extends Command
{
    protected $signature = 'blog:seed-rare-article
        {--author= : 著者ユーザーの id または email（未指定なら is_admin の最若番）}
        {--dry-run : 変更を加えず、実行内容のみ表示}';

    protected $description = '希少・絶版バイクまとめ記事を本番DBへdraftで投入（冪等・本文は同梱ファイルから）';

    private const SLUG = 'rare-discontinued-used-bikes-2026';
    private const BODY_FILE = 'content/rare-discontinued-used-bikes-2026.md';
    private const TITLE = '絶版・希少バイクの中古まとめ10選｜相場・狙い目・探し方【2026年版】';
    private const EXCERPT = '生産終了から数十年を経てもなお探し求められる絶版・希少バイク10台を、MotoHubの実在庫データ（相場・在庫台数）をもとに厳選。各モデルの希少性・中古相場・狙い目を解説します。';
    private const META_TITLE = '絶版・希少バイクの中古まとめ10選｜相場・狙い目・探し方【2026年版】';
    private const META_DESCRIPTION = 'FXB1340スタージス・ドゥカティMHR・Z2・刀・GPZ900Rなど、希少な絶版バイクの中古相場・在庫・探し方を実データで解説。プレミア化する名車を中古で賢く狙うポイントも【2026年版】';
    private const READING_TIME = 13;

    public function handle(): int
    {
        $bodyPath = database_path(self::BODY_FILE);
        if (! is_file($bodyPath)) {
            $this->error("本文ファイルが見つかりません: {$bodyPath}");
            return self::FAILURE;
        }
        $body = file_get_contents($bodyPath);
        if ($body === false || trim($body) === '') {
            $this->error("本文ファイルが空、または読み込めません: {$bodyPath}");
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $existing = BlogPost::where('slug', self::SLUG)->first();

        // 著者解決（更新時は既存著者を尊重し、新規時のみ解決した著者を使う）
        $author = null;
        if ($existing === null) {
            $author = $this->resolveAuthor();
            if ($author === null) {
                $this->error('著者ユーザーを解決できませんでした。--author=<id|email> を指定してください。');
                return self::FAILURE;
            }
        }

        $this->line('--- 投入内容 ---');
        $this->line('slug            : ' . self::SLUG);
        $this->line('title           : ' . self::TITLE);
        $this->line('body_chars      : ' . mb_strlen($body));
        $this->line('reading_minutes : ' . self::READING_TIME);
        $this->line('mode            : ' . ($existing ? '更新（status/published_atは維持）' : 'draftで新規作成'));
        if ($existing) {
            $this->line("existing        : id={$existing->id} status={$existing->status} published_at=" . ($existing->published_at ?? 'NULL'));
        } else {
            $this->line("author          : id={$author->id} ({$author->email})");
        }

        if ($dryRun) {
            $this->warn('[dry-run] 変更は行いませんでした。');
            return self::SUCCESS;
        }

        $post = $existing ?? new BlogPost();
        if ($existing === null) {
            $post->author_id = $author->id;
            $post->slug = self::SLUG;
            $post->status = 'draft';        // 新規は必ずdraft。公開は管理画面で。
        }
        // 本文・メタは常に同梱ファイル/定数で上書き（正本に揃える）。
        $post->title = self::TITLE;
        $post->body = $body;
        $post->excerpt = self::EXCERPT;
        $post->meta_title = self::META_TITLE;
        $post->meta_description = self::META_DESCRIPTION;
        $post->reading_time_minutes = self::READING_TIME;
        $post->save();

        $this->info(($existing ? '更新しました' : 'draftで作成しました') . ": id={$post->id} slug={$post->slug} status={$post->status}");
        $this->newLine();
        $this->line('次の手順:');
        $this->line('  1. 管理画面 /admin/blog/posts で内容を確認');
        $this->line('  2. 問題なければ published 化（published_at を設定）');
        $this->line('  3. デプロイ: view:clear + config:cache + app/web再起動（cache:clear/optimize:clearは不可）');
        $this->line('  4. php artisan sitemap:generate（sitemap収録 + IndexNow送信）');

        return self::SUCCESS;
    }

    private function resolveAuthor(): ?User
    {
        $opt = $this->option('author');
        if ($opt !== null && $opt !== '') {
            return is_numeric($opt)
                ? User::find((int) $opt)
                : User::where('email', $opt)->first();
        }

        return User::where('is_admin', true)->orderBy('id')->first()
            ?? User::orderBy('id')->first();
    }
}
