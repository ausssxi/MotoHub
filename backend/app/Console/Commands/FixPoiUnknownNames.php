<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Poi;
use Illuminate\Console\Command;

/**
 * pois.name が正確に '名称不明'（センチネル値）の行を name = null に置き換えるバックフィル。
 * 既定は dry-run。実際の更新は --execute を明示したときのみ。
 * 完全一致（= '名称不明'）のみ対象で、「〇〇（名称不明）」等の部分一致は巻き込まない。
 */
final class FixPoiUnknownNames extends Command
{
    protected $signature = 'pois:fix-unknown-names {--execute : 実際に name を null 化する（未指定は dry-run）}';

    protected $description = "pois.name が '名称不明' の行を null に置き換える（既定 dry-run・--execute で実行）";

    private const SENTINEL = '名称不明';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');

        // 完全一致のみ（部分一致「〇〇（名称不明）」を除外するため where('name', '=', …)）。
        $base = Poi::query()->where('name', '=', self::SENTINEL);

        $total = (clone $base)->count();
        if ($total === 0) {
            $this->info("対象なし（name が正確に '".self::SENTINEL."' の行は 0 件）。");

            return self::SUCCESS;
        }

        // type ごとの件数。
        $byType = (clone $base)
            ->selectRaw('type, COUNT(*) as c')
            ->groupBy('type')
            ->pluck('c', 'type');

        if (! $execute) {
            $this->warn("[dry-run] name = '".self::SENTINEL."' の行: 合計 {$total} 件（--execute で null 化）");
            foreach ($byType as $type => $count) {
                $this->line("  {$type}: {$count} 件");
            }
            $this->newLine();
            $this->line('サンプル5件（id / type / name / address）:');
            $samples = (clone $base)->limit(5)->get(['id', 'type', 'name', 'address']);
            foreach ($samples as $p) {
                $this->line(sprintf('  #%d [%s] name=%s / address=%s', $p->id, $p->type, $p->name, $p->address ?? '(なし)'));
            }

            return self::SUCCESS;
        }

        // --execute: type ごとに更新して件数を出す。
        $this->info("[execute] name = '".self::SENTINEL."' → null に更新します。");
        $updatedTotal = 0;
        foreach ($byType as $type => $count) {
            $updated = Poi::query()
                ->where('name', '=', self::SENTINEL)
                ->where('type', $type)
                ->update(['name' => null]);
            $updatedTotal += $updated;
            $this->line("  {$type}: {$updated} 件 更新");
        }
        $this->info("完了: 合計 {$updatedTotal} 件を null 化しました。");

        return self::SUCCESS;
    }
}
