<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeNews;
use App\Services\News\ModelImpactTitleBuilder;
use Illuminate\Console\Command;

/**
 * 既存の「{車種名}の新型発表で旧型中古相場はどう動く？｜データで予測」記事のタイトルを、
 * 事実ベース・一意なタイトルへ書き換える。
 *
 * - 記事は消さない・URL(/news/{id})は変えない・published_at も変えない。title だけ更新。
 * - 数字は本文（content）から抽出し、本文と必ず一致させる。取れなければ日付形式に落とす。
 * - 既存書き換えではトリガー要約は付けない（年月・年月日で一意化する）。
 */
final class RetitleModelImpactNews extends Command
{
    protected $signature = 'news:retitle-model-impact
                            {--dry-run : 変更前後を表示するだけでDBは触らない}
                            {--limit= : 処理件数の上限（確認用）}';

    protected $description = '新型発表構文の自前ニュース318本のタイトルを事実ベースに書き換える';

    /** 対象を確実に絞り込む旧タイトルの共通部分句。ランキング記事等には一致しない。 */
    private const OLD_TITLE_MARKER = '新型発表で旧型中古相場はどう動く';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        $query = BikeNews::query()
            ->where('source', BikeNews::SOURCE_ORIGINAL)
            ->where('title', 'like', '%'.self::OLD_TITLE_MARKER.'%')
            ->with('bikeModel')
            ->orderBy('id');

        if ($limit !== null) {
            $query->limit($limit);
        }

        $articles = $query->get();

        if ($articles->isEmpty()) {
            $this->info('対象記事はありません。');

            return self::SUCCESS;
        }

        $this->info($articles->count().'件の対象記事を検出'.($isDryRun ? '（dry-run）' : '').'。');

        // 1st pass: 各記事の新タイトル候補を作る。
        $plans = [];
        $skipped = 0;

        foreach ($articles as $news) {
            $officialName = ModelImpactTitleBuilder::officialName($news->bikeModel);

            if ($officialName === null) {
                // 車種名が取れない記事はタイトルを作れないので触らない。
                $this->warn("  [skip] #{$news->id} 正式車種名が取れず: {$news->title}");
                $skipped++;

                continue;
            }

            $date = $news->published_at ?? $news->created_at;
            $yearMonth = $date->format('Y年n月');
            $yearMonthDay = $date->format('Y年n月j日');

            $numbers = ModelImpactTitleBuilder::extractNumbers((string) $news->content);

            if ($numbers !== null) {
                $newTitle = ModelImpactTitleBuilder::build(
                    $officialName,
                    $numbers['avg'],
                    $numbers['count'],
                    null,
                    $yearMonth,
                );
                $fallbackTitle = ModelImpactTitleBuilder::buildDateFallback($officialName, $yearMonthDay);
            } else {
                // 数字が取れなければ日付形式に落とす。
                $newTitle = ModelImpactTitleBuilder::buildDateFallback($officialName, $yearMonthDay);
                $fallbackTitle = $newTitle;
            }

            $plans[] = [
                'news' => $news,
                'new_title' => $newTitle,
                'fallback_title' => $fallbackTitle,
            ];
        }

        // 2nd pass: 同一タイトルの衝突（同一車種・同一年月で数字も同じ等）を日付形式へ落とす。
        $this->resolveCollisions($plans);

        // 出力 & 反映
        $updated = 0;
        foreach ($plans as $i => $plan) {
            /** @var BikeNews $news */
            $news = $plan['news'];
            $newTitle = $plan['new_title'];

            if ($newTitle === $news->title) {
                continue;
            }

            $this->line(sprintf('  [%d] #%d', $i + 1, $news->id));
            $this->line('    旧: '.$news->title);
            $this->line('    新: '.$newTitle);

            if (! $isDryRun) {
                $news->update(['title' => $newTitle]);
            }

            $updated++;
        }

        $mode = $isDryRun ? '（dry-run: DB未変更）' : '';
        $this->info("完了: {$updated}件書き換え{$mode}、{$skipped}件スキップ。");

        return self::SUCCESS;
    }

    /**
     * このバッチ内で生成タイトルが重複するグループは、日付形式（年月日時点）に落として一意化する。
     * 数字が違えば自然に区別されるが、同一車種・同一年月・同一数字だと衝突するため。
     *
     * @param  array<int, array{news: BikeNews, new_title: string, fallback_title: string}>  $plans
     */
    private function resolveCollisions(array &$plans): void
    {
        // このバッチ内で同一タイトルになるものを日付形式へ。
        $counts = [];
        foreach ($plans as $plan) {
            $counts[$plan['new_title']] = ($counts[$plan['new_title']] ?? 0) + 1;
        }

        foreach ($plans as &$plan) {
            if (($counts[$plan['new_title']] ?? 0) > 1) {
                $plan['new_title'] = $plan['fallback_title'];
            }
        }
        unset($plan);
    }
}
