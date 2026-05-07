<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BlogPost;
use App\Models\TouringGuide;
use Illuminate\Console\Command;

final class MigrateTouringArticles extends Command
{
    protected $signature = 'touring:migrate-from-blog {--dry-run : 実際には移行せず確認のみ}';

    protected $description = 'ブログのツーリング記事をツーリングガイドに移行する';

    private const ARTICLES = [
        [
            'title_match' => '奥多摩周遊道路ツーリング',
            'prefecture' => '東京都',
            'difficulty' => '中級',
            'distance_km' => 60,
            'duration_text' => '3〜4時間',
            'best_season' => '5月〜6月/10月〜11月',
        ],
        [
            'title_match' => '道志みちツーリング',
            'prefecture' => '神奈川県',
            'difficulty' => '中級',
            'distance_km' => 60,
            'duration_text' => '3〜5時間',
            'best_season' => '4月下旬〜6月/9月〜11月',
        ],
        [
            'title_match' => '秩父・大滝ツーリング',
            'prefecture' => '埼玉県',
            'difficulty' => '中級',
            'distance_km' => 80,
            'duration_text' => '4〜6時間',
            'best_season' => '10月下旬〜11月中旬',
        ],
        [
            'title_match' => '三浦半島一周ツーリング',
            'prefecture' => '神奈川県',
            'difficulty' => '初級',
            'distance_km' => 80,
            'duration_text' => '3〜5時間',
            'best_season' => '通年',
        ],
        [
            'title_match' => '箱根ツーリングルート',
            'prefecture' => '神奈川県',
            'difficulty' => '中級',
            'distance_km' => 70,
            'duration_text' => '3〜5時間',
            'best_season' => '4月〜6月/9月〜11月',
        ],
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $migrated = 0;
        $skipped = 0;

        foreach (self::ARTICLES as $article) {
            $post = BlogPost::withTrashed()
                ->where('title', 'like', '%' . $article['title_match'] . '%')
                ->first();

            if (!$post) {
                $this->warn("記事が見つかりません: {$article['title_match']}");
                continue;
            }

            $this->info("発見: {$post->title} (ID: {$post->id})" . ($post->trashed() ? ' [ソフトデリート済]' : ''));

            // 既に同タイトルのTouringGuideが存在する場合はスキップ
            $existing = TouringGuide::where('title', $post->title)->first();
            if ($existing) {
                $this->line("  -> スキップ: 既にTouringGuide ID={$existing->id} として移行済み");

                // ブログ記事がまだ残っている場合は完全削除
                if (!$dryRun && !$post->trashed()) {
                    $post->forceDelete();
                    $this->line("  -> BlogPost ID={$post->id} を完全削除");
                } elseif (!$dryRun && $post->trashed()) {
                    $post->forceDelete();
                    $this->line("  -> BlogPost ID={$post->id} を完全削除（ソフトデリート→完全削除）");
                }

                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("  -> [DRY RUN] 移行対象: {$article['prefecture']} / {$article['difficulty']} / {$article['distance_km']}km");
                $migrated++;
                continue;
            }

            $guide = TouringGuide::create([
                'author_id' => $post->author_id,
                'title' => $post->title,
                'body' => $post->body,
                'excerpt' => $post->excerpt,
                'latitude' => $post->latitude ?? 35.6812362,
                'longitude' => $post->longitude ?? 139.7671248,
                'zoom_level' => 12,
                'prefecture' => $article['prefecture'],
                'difficulty' => $article['difficulty'],
                'distance_km' => $article['distance_km'],
                'duration_text' => $article['duration_text'],
                'best_season' => $article['best_season'],
                'status' => 'published',
                'published_at' => $post->published_at ?? now(),
            ]);

            $post->forceDelete();

            $this->info("  -> 移行完了: TouringGuide ID={$guide->id}, BlogPost ID={$post->id} を完全削除");
            $migrated++;
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("{$migrated} 件の記事を確認しました（DRY RUN）。スキップ: {$skipped} 件");
        } else {
            $this->info("{$migrated} 件を移行、{$skipped} 件をスキップ（既存ガイドのブログ記事を完全削除）しました。");
        }

        return self::SUCCESS;
    }
}
