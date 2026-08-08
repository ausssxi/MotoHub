<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RentalGarage;
use Illuminate\Console\Command;

/**
 * 既存の rental_garages.size_text の記号の表記ゆれを正規化する。
 *
 * 正規化ロジックは RentalGarage::normalizeSizeText() に集約済み（唯一の正解の置き場所）。
 * このコマンドは過去に取り込んだ行へその正規化を一括適用するだけ。
 *
 * ※ 既存の値を書き換えるため、既定は dry-run。--execute で実書き込み。
 */
class NormalizeRentalGarageSizeText extends Command
{
    protected $signature = 'rental-garages:normalize-size-text
        {--execute : 実際に更新する（未指定は dry-run）}
        {--limit= : 処理件数の上限}';

    protected $description = 'rental_garages の size_text の記号ゆれ（チルダ・帖/畳）を正規化する（既定 dry-run）';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        if (! $execute) {
            $this->info('[DRY RUN] データは更新されません。');
        }

        $evaluated = 0;
        $changed = 0;
        $unchanged = 0;
        $samples = [];

        $query = RentalGarage::query()
            ->whereNotNull('size_text')
            ->where('size_text', '!=', '')
            ->orderBy('id');

        $query->chunkById(200, function ($garages) use (
            $execute,
            $limit,
            &$evaluated,
            &$changed,
            &$unchanged,
            &$samples,
        ): bool {
            foreach ($garages as $garage) {
                if ($limit !== null && $evaluated >= $limit) {
                    return false;
                }

                $evaluated++;

                $original = (string) $garage->size_text;
                $normalized = RentalGarage::normalizeSizeText($original);

                if ($normalized === $original) {
                    $unchanged++;

                    continue;
                }

                $changed++;
                if (count($samples) < 40) {
                    $samples[] = [
                        'id' => $garage->id,
                        'name' => (string) $garage->name,
                        'from' => $original,
                        'to' => (string) $normalized,
                    ];
                }

                if ($execute) {
                    // size_text のみ更新。他カラムは触らない。
                    $garage->updateQuietly(['size_text' => $normalized]);
                }
            }

            // limit 到達時は以降のチャンクを打ち切る。
            return ! ($limit !== null && $evaluated >= $limit);
        });

        if ($samples !== []) {
            $this->newLine();
            $this->line('変更対象（最大40件）:');
            foreach ($samples as $s) {
                $this->line("  {$s['id']} / {$s['name']} / {$s['from']} → {$s['to']}");
            }
        }

        $this->newLine();
        $this->info('評価件数      : '.$evaluated);
        $this->info('変更対象件数   : '.$changed);
        $this->info('変更なし件数   : '.$unchanged);

        if (! $execute) {
            $this->newLine();
            $this->warn('DRY RUN のため実際の更新は行っていません。');
        }

        return self::SUCCESS;
    }
}
