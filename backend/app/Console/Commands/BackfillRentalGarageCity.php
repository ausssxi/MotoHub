<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RentalGarage;
use App\Services\Parking\AddressParser;
use Illuminate\Console\Command;

/**
 * 既存の rental_garages.city を AddressParser で取り直してバックフィルする。
 *
 * AbstractRentalGarageScraper::extractCity() の旧自前実装は政令市の区を取り落としていたため、
 * 過去に取り込んだ行の city が誤っている（例:「名古屋市名東区…」→「名古屋市」）。
 * shops / parkings / stations と同じ AddressParser で解決し直す。
 *
 * ※ 既に city が入っている行を書き換えるため、既定は dry-run。--execute で実書き込み。
 * ※ パース結果の city が空文字の行はスキップし、既存の city を絶対に消さない。
 */
class BackfillRentalGarageCity extends Command
{
    protected $signature = 'rental-garages:backfill-city
        {--execute : 実際に更新する（未指定は dry-run）}
        {--limit= : 処理件数の上限}';

    protected $description = 'AddressParser を使って rental_garages の city を取り直す（既定 dry-run）';

    public function handle(AddressParser $parser): int
    {
        $execute = (bool) $this->option('execute');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;

        if (! $execute) {
            $this->info('[DRY RUN] データは更新されません。');
        }

        $evaluated = 0;
        $changed = 0;
        $unchanged = 0;
        $parseFailed = 0;
        $samples = [];

        $query = RentalGarage::query()
            ->whereNotNull('address')
            ->where('address', '!=', '')
            ->orderBy('id');

        $query->chunkById(200, function ($garages) use (
            $parser,
            $execute,
            $limit,
            &$evaluated,
            &$changed,
            &$unchanged,
            &$parseFailed,
            &$samples,
        ): bool {
            foreach ($garages as $garage) {
                if ($limit !== null && $evaluated >= $limit) {
                    return false;
                }

                $evaluated++;

                $newCity = $parser->parse((string) $garage->address)['city'];

                // パース失敗（city 空）は既存 city を消さないためスキップ。
                if ($newCity === '') {
                    $parseFailed++;

                    continue;
                }

                if ($newCity === (string) $garage->city) {
                    $unchanged++;

                    continue;
                }

                $changed++;
                if (count($samples) < 40) {
                    $samples[] = [
                        'id' => $garage->id,
                        'name' => (string) $garage->name,
                        'from' => (string) $garage->city,
                        'to' => $newCity,
                    ];
                }

                if ($execute) {
                    // city のみ更新。他カラムは触らない。
                    $garage->updateQuietly(['city' => $newCity]);
                }
            }

            // limit 到達時は以降のチャンクを打ち切る。
            return ! ($limit !== null && $evaluated >= $limit);
        });

        if ($samples !== []) {
            $this->newLine();
            $this->line('変更対象（最大40件）:');
            foreach ($samples as $s) {
                $from = $s['from'] !== '' ? $s['from'] : '(空)';
                $this->line("  {$s['id']} / {$s['name']} / {$from} → {$s['to']}");
            }
        }

        $this->newLine();
        $this->info('評価件数            : '.$evaluated);
        $this->info('変更対象件数         : '.$changed);
        $this->info('変更なし件数         : '.$unchanged);
        $this->info('パース失敗スキップ件数 : '.$parseFailed);

        if (! $execute) {
            $this->newLine();
            $this->warn('DRY RUN のため実際の更新は行っていません。');
        }

        return self::SUCCESS;
    }
}
