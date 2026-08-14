<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ModelFitment;
use App\Services\Parts\ProductSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 適合表（battery / plug）の recommended_part_no で商品検索し、価格の四分位から
 * cost_part_min / cost_part_max を埋める。
 *
 * 設計の肝:
 *  - 楽天への呼び出しは必ず ProductSearchService 経由（＝共有の RakutenRateGate を通る）。
 *    独自 Http::get は書かない（3経路に分散してレート制限に常時ぶつかっていた問題を直した直後のため）。
 *  - 下限・上限は単純な min/max ではなく四分位（Q1/Q3）を採る。まとめ買い・誤ヒットの外れ値で
 *    レンジが壊れるのを防ぐため。有効価格が MIN_SAMPLES 件未満の行は更新せずスキップ＋ログ。
 *  - 既定は書き込まない（dry-run）。書き込むのは --execute かつ --dry-run 未指定のときだけ。
 *  - oil は対象外（recommended_part_no が粘度で容量違いが混ざり、品番検索で価格が定まらないため）。
 */
final class UpdateFitmentCosts extends Command
{
    protected $signature = 'fitment:update-costs
        {--task= : battery|plug のみに絞る（未指定は両方）}
        {--execute : 実際に DB へ書き込む（既定は書き込まない＝ドライラン）}
        {--dry-run : 明示的にドライラン（--execute より優先。既定でも書き込まない）}
        {--hits=30 : 1品番あたりの取得件数（四分位のサンプル数。ProductSearchService 側で最大30に丸められる）}
        {--limit=0 : 処理する行数の上限（0=無制限。動作確認用）}';

    protected $description = '適合表(battery/plug)の recommended_part_no で商品検索し、価格の四分位で cost_part_min/max を埋める（ProductSearchService経由・既定dry-run）';

    /** 四分位を計算するのに必要な有効価格の最小件数。これ未満はスキップ。 */
    private const MIN_SAMPLES = 3;

    /** 対象タスク（oil は品番が粘度で価格が定まらないため対象外）。 */
    private const TASKS = ['battery', 'plug'];

    public function handle(ProductSearchService $service): int
    {
        $task = trim((string) ($this->option('task') ?? ''));
        if ($task !== '' && ! in_array($task, self::TASKS, true)) {
            $this->error('--task は '.implode(' / ', self::TASKS).' のみ指定できます。');

            return self::FAILURE;
        }
        $tasks = $task !== '' ? [$task] : self::TASKS;

        // 既定は書き込まない。--execute かつ --dry-run 未指定のときだけ書き込む。
        $write = (bool) $this->option('execute') && ! $this->option('dry-run');
        $hits = max(1, (int) $this->option('hits'));
        $limit = max(0, (int) $this->option('limit'));
        $stamp = now()->format('Y-m'); // 既存 cost_updated_at と同じ '2026-08' 書式に合わせる

        $query = ModelFitment::query()
            ->whereIn('task', $tasks)
            ->whereNotNull('recommended_part_no')
            ->where('recommended_part_no', '!=', '')
            ->orderBy('id');
        if ($limit > 0) {
            $query->limit($limit);
        }
        $rows = $query->get(['id', 'task', 'frame_code', 'recommended_part_no']);

        $this->newLine();
        $this->line('==== fitment:update-costs '.($write ? '（本書き込み）' : '（dry-run・DBへは書き込みません）').' ====');
        $this->line('対象タスク: '.implode(', ', $tasks).' / 1品番あたり取得: '.$hits.'件 / 四分位の最小サンプル: '.self::MIN_SAMPLES.'件');
        $this->line('対象行: '.$rows->count());

        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $partNo = trim((string) $row->recommended_part_no);

            // 楽天+Yahoo を ProductSearchService（→RakutenRateGate）経由で検索。
            $result = $service->searchProducts($partNo, $hits);

            // 有効価格（>0）だけを昇順に集める。0（価格欠落）や誤ヒットの極端値は四分位で吸収する。
            $prices = collect($result)
                ->map(fn ($item) => (int) ($item['price'] ?? 0))
                ->filter(fn (int $p): bool => $p > 0)
                ->sort()
                ->values()
                ->all();

            if (count($prices) < self::MIN_SAMPLES) {
                $skipped++;
                $errors = $service->lastErrors();
                $reason = count($prices).'件（最小'.self::MIN_SAMPLES.'件未満）'
                    .($errors !== [] ? ' / API: '.json_encode($errors, JSON_UNESCAPED_UNICODE) : '');
                $this->warn(sprintf('  skip  id=%d %-7s 品番=%s : %s', $row->id, $row->task, $partNo, $reason));
                // 黙って飛ばさない。品番と理由を残す。
                Log::info('fitment:update-costs skip', [
                    'id' => $row->id,
                    'task' => $row->task,
                    'part_no' => $partNo,
                    'samples' => count($prices),
                    'errors' => $errors,
                ]);

                continue;
            }

            $min = $this->quartile($prices, 0.25); // 第1四分位 = 下限
            $max = $this->quartile($prices, 0.75); // 第3四分位 = 上限

            $this->line(sprintf(
                '  ok    id=%d %-7s 品番=%s : n=%d  min(Q1)=%d円  max(Q3)=%d円',
                $row->id,
                $row->task,
                $partNo,
                count($prices),
                $min,
                $max
            ));

            if ($write) {
                DB::table('model_fitments')->where('id', $row->id)->update([
                    'cost_part_min' => $min,
                    'cost_part_max' => $max,
                    'cost_updated_at' => $stamp,
                ]);
            }
            $updated++;
        }

        $this->newLine();
        $this->line('==== 集計 ====');
        $this->line(sprintf('  対象: %d件 / 更新%s: %d件 / スキップ: %d件', $rows->count(), $write ? '' : '（予定）', $updated, $skipped));
        if (! $write) {
            $this->comment('  ※ dry-run のため DB は変更していません。書き込むには --execute を付けてください。');
        }

        return self::SUCCESS;
    }

    /**
     * 昇順ソート済み配列の分位点（線形補間）。$q=0.25 で第1四分位、0.75 で第3四分位。
     * min/max ではなく四分位を採ることで、まとめ買い・誤ヒットの外れ値を下位25%/上位25%として除外する。
     *
     * @param  array<int, int>  $sorted 昇順ソート済みの価格配列
     */
    private function quartile(array $sorted, float $q): int
    {
        $n = count($sorted);
        if ($n === 1) {
            return $sorted[0];
        }

        $pos = $q * ($n - 1);
        $lo = (int) floor($pos);
        $hi = (int) ceil($pos);
        if ($lo === $hi) {
            return $sorted[$lo];
        }

        $frac = $pos - $lo;

        return (int) round($sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * $frac);
    }
}
