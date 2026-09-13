<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeNews;
use Illuminate\Console\Command;

/**
 * 外部RSS（source != MotoHub）で、同一 title＋同一 source が複数レコードになっているもの
 * （Google News の URL トークンが変わるたび別レコード化した既存分）を1本にまとめる。
 *
 * 残す1件の優先順位: comments_count>0 → thumbnail_url有 → bike_model_id有 → id最小。
 * ★安全策: コメントの付いたレコードは削除しない（keeper 以外でもコメント有りは残す）。
 * ★ source='MotoHub'（自前記事）は対象外。
 */
final class DedupeExternalNews extends Command
{
    protected $signature = 'news:dedupe-external
                            {--dry-run : 削除せずグループ数・削除件数・サンプルだけ表示する}
                            {--limit= : 対象にする最大グループ数（安全用）}';

    protected $description = '外部RSSの同一title+source重複を1本にまとめる';

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        // 外部RSSの必要列だけを1クエリで取得し、PHP側で (title, source) にグループ化する。
        $rows = BikeNews::query()
            ->where('source', '!=', BikeNews::SOURCE_ORIGINAL)
            ->select('id', 'title', 'source', 'comments_count', 'thumbnail_url', 'bike_model_id')
            ->orderBy('id')
            ->get();

        $groups = $rows->groupBy(fn ($r): string => $r->title."\x00".$r->source);

        $deleteIds = [];
        $groupCount = 0;
        $samples = [];

        foreach ($groups as $members) {
            if ($members->count() < 2) {
                continue; // 重複なし
            }

            if ($limit !== null && $groupCount >= $limit) {
                break;
            }

            $ordered = $this->orderByKeepPriority($members->all());
            $keeper = $ordered[0];

            // keeper 以外を削除。ただしコメントの付いたレコードは削除しない（安全策）。
            $toDelete = [];
            foreach (array_slice($ordered, 1) as $row) {
                if ((int) ($row->comments_count ?? 0) > 0) {
                    continue;
                }
                $toDelete[] = $row->id;
            }

            if (empty($toDelete)) {
                continue; // 実質削除対象なし（全てコメント有り等）
            }

            $groupCount++;
            $deleteIds = array_merge($deleteIds, $toDelete);

            if (count($samples) < 10) {
                $samples[] = sprintf(
                    'title="%s" source=%s / 残す#%d / 消す[%s]',
                    $keeper->title,
                    $keeper->source,
                    $keeper->id,
                    implode(',', $toDelete)
                );
            }
        }

        $deleteCount = count($deleteIds);

        if ($deleteCount === 0) {
            $this->info('重複はありませんでした。');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '重複グループ %d 種類 / 削除できる件数 %d 件%s。',
            $groupCount,
            $deleteCount,
            $isDryRun ? '（dry-run）' : ''
        ));
        $this->line('  サンプル（最大10グループ）:');
        foreach ($samples as $s) {
            $this->line('    '.$s);
        }

        if ($isDryRun) {
            $this->info('dry-run のため削除していません。');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach (array_chunk($deleteIds, 500) as $chunk) {
            $deleted += BikeNews::whereIn('id', $chunk)->delete();
        }

        $this->info("完了: {$deleted} 件を削除しました。");

        return self::SUCCESS;
    }

    /**
     * 残す1件を先頭にする並び替え。comments>0 → thumbnail有 → bike_model_id有 → id最小。
     *
     * @param  array<int, BikeNews>  $members
     * @return array<int, BikeNews>
     */
    private function orderByKeepPriority(array $members): array
    {
        usort($members, static function (BikeNews $a, BikeNews $b): int {
            $ca = (int) ($a->comments_count ?? 0) > 0 ? 1 : 0;
            $cb = (int) ($b->comments_count ?? 0) > 0 ? 1 : 0;
            if ($ca !== $cb) {
                return $cb <=> $ca;
            }

            $ta = ! empty($a->thumbnail_url) ? 1 : 0;
            $tb = ! empty($b->thumbnail_url) ? 1 : 0;
            if ($ta !== $tb) {
                return $tb <=> $ta;
            }

            $ma = ! empty($a->bike_model_id) ? 1 : 0;
            $mb = ! empty($b->bike_model_id) ? 1 : 0;
            if ($ma !== $mb) {
                return $mb <=> $ma;
            }

            return $a->id <=> $b->id; // 最初に取り込んだもの
        });

        return $members;
    }
}
