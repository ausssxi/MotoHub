<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeNews;
use App\Support\NewsJunkFilter;
use Illuminate\Console\Command;

/**
 * 既存の外部RSSレコードから、NewsJunkFilter が「ゴミ」と判定するものを削除する。
 *
 * - 判定は news:fetch の取り込み時と同一（NewsJunkFilter）。
 * - source='MotoHub'（自前記事）は絶対に対象にしない。
 * - comments_count > 0（コメントが付いている）は念のため削除しない。
 * - 必ず --dry-run で件数とサンプルを確認してから本実行する。
 */
final class PurgeJunkNews extends Command
{
    protected $signature = 'news:purge-junk
                            {--dry-run : 削除せず件数とサンプルだけ表示する}
                            {--limit= : 対象にする最大件数（安全用）}';

    protected $description = '外部RSSのゴミ記事（トップ/一覧/掲示板ページ等）を削除する';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $junkIds = [];
        $reasons = [];
        $samples = [];

        // 外部RSS（source != MotoHub）かつコメント無しだけを走査する。
        BikeNews::query()
            ->where('source', '!=', BikeNews::SOURCE_ORIGINAL)
            ->where(function ($q) {
                $q->whereNull('comments_count')->orWhere('comments_count', '<=', 0);
            })
            ->select('id', 'title', 'source', 'bike_model_id', 'comments_count')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$junkIds, &$reasons, &$samples, $limit): bool {
                foreach ($rows as $row) {
                    $reason = NewsJunkFilter::junkReason((string) $row->title, (string) $row->source);
                    if ($reason === null) {
                        continue;
                    }

                    $junkIds[] = $row->id;
                    $reasons[$reason] = ($reasons[$reason] ?? 0) + 1;

                    if (count($samples) < 20) {
                        $samples[] = sprintf(
                            '#%d [%s] %s（source=%s%s）',
                            $row->id,
                            $reason,
                            $row->title,
                            $row->source,
                            $row->bike_model_id ? ', bike_model_id='.$row->bike_model_id : ''
                        );
                    }

                    if ($limit !== null && count($junkIds) >= $limit) {
                        return false; // 上限到達で走査を止める
                    }
                }

                return true;
            });

        $total = count($junkIds);

        if ($total === 0) {
            $this->info('削除対象のゴミ記事はありませんでした。');

            return self::SUCCESS;
        }

        $this->info("ゴミ記事 {$total} 件を検出".($isDryRun ? '（dry-run）' : '').'。');
        $breakdown = collect($reasons)->map(fn (int $n, string $r): string => "{$r}={$n}")->implode(', ');
        $this->line("  内訳: {$breakdown}");
        $this->line('  サンプル（最大20件）:');
        foreach ($samples as $s) {
            $this->line('    '.$s);
        }

        if ($isDryRun) {
            $this->info('dry-run のため削除していません。');

            return self::SUCCESS;
        }

        // 本実行: id 指定でまとめて削除（MotoHub・コメント付きは対象に含まれない）。
        $deleted = 0;
        foreach (array_chunk($junkIds, 500) as $chunk) {
            $deleted += BikeNews::whereIn('id', $chunk)->delete();
        }

        $this->info("完了: {$deleted} 件を削除しました。");

        return self::SUCCESS;
    }
}
