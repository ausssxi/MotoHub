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
        $totalFetched = 0; // 有効価格つきで取得できた総数（絞り込み前）
        $totalMatched = 0; // うちタイトルに品番が含まれた総数（採用サンプル）

        foreach ($rows as $row) {
            $partNo = trim((string) $row->recommended_part_no);

            // 楽天+Yahoo を ProductSearchService（→RakutenRateGate）経由で検索。
            $result = $service->searchProducts($partNo, $hits);

            // まず有効価格（>0）の商品を集める。これが「取得n件」。
            $withPrice = collect($result)
                ->filter(fn ($item): bool => (int) ($item['price'] ?? 0) > 0)
                ->values();
            $fetched = $withPrice->count();

            // タイトルに品番が含まれる商品だけを価格サンプルに採用する（大小文字・ハイフン有無を無視）。
            // 説明文に適合表として品番が羅列された別商品（充電器セット・複数個セット・別サイズ等）を
            // 落とすため。これが無いと Q3 が別商品の価格で汚れる（GTZ4V の上限12,005円等）。
            $needle = $this->normalizeCode($partNo);
            $prices = $withPrice
                ->filter(fn ($item): bool => str_contains($this->normalizeCode((string) ($item['name'] ?? '')), $needle))
                ->map(fn ($item) => (int) $item['price'])
                ->sort()
                ->values()
                ->all();
            $matched = count($prices);

            $totalFetched += $fetched;
            $totalMatched += $matched;

            if ($matched < self::MIN_SAMPLES) {
                $skipped++;
                $errors = $service->lastErrors();
                $reason = "取得{$fetched}件 → タイトル一致{$matched}件（最小".self::MIN_SAMPLES.'件未満）'
                    .($errors !== [] ? ' / API: '.json_encode($errors, JSON_UNESCAPED_UNICODE) : '');
                $this->warn(sprintf('  skip  id=%d %-7s 品番=%s : %s', $row->id, $row->task, $partNo, $reason));
                // 黙って飛ばさない。品番・理由・件数を残す。
                Log::info('fitment:update-costs skip', [
                    'id' => $row->id,
                    'task' => $row->task,
                    'part_no' => $partNo,
                    'fetched' => $fetched,
                    'matched' => $matched,
                    'errors' => $errors,
                ]);

                continue;
            }

            $min = $this->quartile($prices, 0.25); // 第1四分位 = 下限
            $max = $this->quartile($prices, 0.75); // 第3四分位 = 上限

            $this->line(sprintf(
                '  ok    id=%d %-7s 品番=%s : 取得%d件 → タイトル一致%d件  min(Q1)=%d円  max(Q3)=%d円',
                $row->id,
                $row->task,
                $partNo,
                $fetched,
                $matched,
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
        $this->line(sprintf(
            '  価格サンプル: 取得 計%d件 → タイトル一致 計%d件（%d件を別商品として除外）',
            $totalFetched,
            $totalMatched,
            $totalFetched - $totalMatched
        ));
        if (! $write) {
            $this->comment('  ※ dry-run のため DB は変更していません。書き込むには --execute を付けてください。');
        }

        return self::SUCCESS;
    }

    /**
     * 品番／タイトルを照合用に正規化する。大小文字を無視し、ハイフン類（全角/各種ダッシュ含む）を除去する。
     * これで「YTX4L-BS」「ytx4lbs」「ＹＴＸ４Ｌ－ＢＳ」を同一視でき、タイトルに品番が含まれるかを頑健に判定できる。
     */
    private function normalizeCode(string $s): string
    {
        $s = mb_convert_kana($s, 'a'); // 全角英数 → 半角
        $s = preg_replace('/[-\x{2010}-\x{2015}\x{2212}\x{FF0D}]/u', '', $s) ?? $s; // ハイフン類を除去
        // 半角化した英字を大文字へ（品番は英数字のみなので mb 不要）。
        return strtoupper($s);
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
