<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\TroubleEvent;
use Illuminate\Console\Command;

/**
 * 症状診断ファネルのレポート（コンソール表）。
 * Filamentダッシュボードは作らず、このコマンドで運用する。
 */
final class TroubleReport extends Command
{
    protected $signature = 'trouble:report {--days=30 : 集計対象の日数}';

    protected $description = '症状診断ファネルの集計（完走率・判定分布・CTA CTR・deeplink流入）';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $since = now()->subDays($days);

        $this->info("=== 症状診断ファネル レポート（直近 {$days} 日）===");

        $this->reportCompletion($since);
        $this->reportVerdictDistribution($since);
        $this->reportCtaCtr($since);
        $this->reportDeeplink($since);
        $this->reportFeedback($since);
        $this->reportRefFunnel($since);

        return self::SUCCESS;
    }

    /**
     * ⑥ 入口別（ref）: 流入（symptom_selected）→ 判定表示（verdict_shown）の完走率。
     * distinct session でカウント。ref null は「(直接/不明)」に集約。
     */
    private function reportRefFunnel(\DateTimeInterface $since): void
    {
        $selected = $this->countRefSessions($since, 'symptom_selected');
        $shown = $this->countRefSessions($since, 'verdict_shown');

        $refs = collect(array_keys($selected + $shown));
        $rows = [];
        foreach ($refs as $ref) {
            $sel = $selected[$ref] ?? 0;
            $done = $shown[$ref] ?? 0;
            $rate = $sel > 0 ? round($done / $sel * 100, 1) : 0.0;
            $rows[] = [$ref === '' ? '(直接/不明)' : $ref, $sel, $done, "{$rate}%"];
        }
        usort($rows, fn ($a, $b) => $b[1] <=> $a[1]);

        $this->newLine();
        $this->line('⑥ 入口別（ref）流入・完走率（distinct session）');
        $this->table(['入口(ref)', '流入(選択)', '判定表示', '完走率'], $rows ?: [['(データなし)', '', '', '']]);
    }

    /**
     * event 単位で ref 別の distinct session 数を返す（ref null は '' キー）。
     *
     * @return array<string,int>
     */
    private function countRefSessions(\DateTimeInterface $since, string $event): array
    {
        return TroubleEvent::query()
            ->where('event', $event)
            ->where('created_at', '>=', $since)
            ->selectRaw("COALESCE(ref, '') as ref, COUNT(DISTINCT session_id) as c")
            ->groupBy('ref')
            ->pluck('c', 'ref')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /**
     * ⑤ 解決フィードバック: 症状×原因(card)別に yes/no/positive率。
     * 同一セッションの連打対策として session_id の distinct でカウントする。
     * 回答数が少ない行も分母を隠さず出す（＝対応記事が薄い原因を見つけるためのデータ）。
     */
    private function reportFeedback(\DateTimeInterface $since): void
    {
        $rows = TroubleEvent::query()
            ->where('event', 'feedback')
            ->where('created_at', '>=', $since)
            ->whereNotNull('symptom')
            ->whereNotNull('card')
            ->whereIn('answer', TroubleEvent::FEEDBACK_ANSWERS)
            ->selectRaw('symptom, card, answer, COUNT(DISTINCT session_id) as c')
            ->groupBy('symptom', 'card', 'answer')
            ->get();

        // 症状×card ごとに yes/no を畳み込む
        $agg = [];
        foreach ($rows as $r) {
            $key = $r->symptom.'|'.$r->card;
            $agg[$key] ??= ['symptom' => $r->symptom, 'card' => $r->card, 'yes' => 0, 'no' => 0];
            $agg[$key][$r->answer] = (int) $r->c;
        }

        $table = [];
        foreach ($agg as $a) {
            $total = $a['yes'] + $a['no'];
            $pos = $total > 0 ? round($a['yes'] / $total * 100, 1) : 0.0;
            $table[] = [$a['symptom'], $a['card'], $a['yes'], $a['no'], "{$pos}%"];
        }
        usort($table, fn ($x, $y) => [$x[0], $x[1]] <=> [$y[0], $y[1]]);

        $this->newLine();
        $this->line('⑤ 解決フィードバック（症状×原因・positive率＝👍/(👍+👎)・distinct session）');
        $this->table(['症状', '原因(card)', '👍yes', '👎no', 'positive率'], $table ?: [['(データなし)', '', '', '', '']]);
    }

    /** ① 症状別: selected → verdict_shown（完走率） */
    private function reportCompletion(\DateTimeInterface $since): void
    {
        $selected = $this->countBy($since, 'symptom_selected', 'symptom');
        $shown = $this->countBy($since, 'verdict_shown', 'symptom');

        $rows = [];
        foreach ($selected as $symptom => $sel) {
            $done = $shown[$symptom] ?? 0;
            $rate = $sel > 0 ? round($done / $sel * 100, 1) : 0.0;
            $rows[] = [$symptom, $sel, $done, "{$rate}%"];
        }
        usort($rows, fn ($a, $b) => $b[1] <=> $a[1]);

        $this->newLine();
        $this->line('① 症状別 完走率（選択 → 判定表示）');
        $this->table(['症状', '選択', '判定表示', '完走率'], $rows ?: [['(データなし)', '', '', '']]);
    }

    /** ② 症状×判定のクロス */
    private function reportVerdictDistribution(\DateTimeInterface $since): void
    {
        $rows = TroubleEvent::query()
            ->where('event', 'verdict_shown')
            ->where('created_at', '>=', $since)
            ->whereNotNull('symptom')
            ->whereNotNull('verdict')
            ->selectRaw('symptom, verdict, COUNT(*) as c')
            ->groupBy('symptom', 'verdict')
            ->orderBy('symptom')
            ->orderByDesc('c')
            ->get()
            ->map(fn ($r) => [$r->symptom, $r->verdict, (int) $r->c])
            ->all();

        $this->newLine();
        $this->line('② 判定分布（症状 × 判定）');
        $this->table(['症状', '判定', '件数'], $rows ?: [['(データなし)', '', '']]);
    }

    /** ③ 判定別 CTA CTR（verdict_shown に対する各ctaクリック率） */
    private function reportCtaCtr(\DateTimeInterface $since): void
    {
        $shown = $this->countBy($since, 'verdict_shown', 'verdict');

        $clicks = TroubleEvent::query()
            ->where('event', 'cta_clicked')
            ->where('created_at', '>=', $since)
            ->whereNotNull('verdict')
            ->whereNotNull('cta')
            ->selectRaw('verdict, cta, COUNT(*) as c')
            ->groupBy('verdict', 'cta')
            ->get();

        $rows = [];
        foreach ($clicks as $r) {
            $base = $shown[$r->verdict] ?? 0;
            $ctr = $base > 0 ? round((int) $r->c / $base * 100, 1) : 0.0;
            $rows[] = [$r->verdict, $r->cta, (int) $r->c, $base, "{$ctr}%"];
        }
        usort($rows, fn ($a, $b) => [$a[0], $b[2]] <=> [$b[0], $a[2]]);

        $this->newLine();
        $this->line('③ CTA CTR（判定別・分母=判定表示数）');
        $this->table(['判定', 'CTA', 'クリック', '判定表示', 'CTR'], $rows ?: [['(データなし)', '', '', '', '']]);
    }

    /** ④ deeplink 流入（症状別） */
    private function reportDeeplink(\DateTimeInterface $since): void
    {
        $rows = TroubleEvent::query()
            ->where('event', 'symptom_selected')
            ->where('source', 'deeplink')
            ->where('created_at', '>=', $since)
            ->whereNotNull('symptom')
            ->selectRaw('symptom, COUNT(*) as c')
            ->groupBy('symptom')
            ->orderByDesc('c')
            ->get()
            ->map(fn ($r) => [$r->symptom, (int) $r->c])
            ->all();

        $this->newLine();
        $this->line('④ ディープリンク流入（source=deeplink・症状別）');
        $this->table(['症状', '流入数'], $rows ?: [['(データなし)', '']]);
    }

    /**
     * event 単位で任意カラムの件数を連想配列で返す。
     *
     * @return array<string,int>
     */
    private function countBy(\DateTimeInterface $since, string $event, string $column): array
    {
        return TroubleEvent::query()
            ->where('event', $event)
            ->where('created_at', '>=', $since)
            ->whereNotNull($column)
            ->selectRaw("{$column} as k, COUNT(*) as c")
            ->groupBy($column)
            ->pluck('c', 'k')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
