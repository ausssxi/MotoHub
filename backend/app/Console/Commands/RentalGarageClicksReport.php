<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RentalGarageClick;
use Illuminate\Console\Command;

/**
 * レンタルガレージの送客クリック数を集計して表示する。
 *
 * 既定は直近30日・bot 除外。--by で日別/ガレージ別/事業者別を切り替え、省略時は3種すべて出す。
 */
final class RentalGarageClicksReport extends Command
{
    protected $signature = 'rental-garage:clicks
        {--days=30 : 集計対象の日数（clicked_at の直近N日）}
        {--by= : day|garage|operator（省略時は3種すべて）}
        {--include-bots : bot と判定したクリックも含める}';

    protected $description = 'レンタルガレージ事業者サイトへの送客クリック数を日別/ガレージ別/事業者別で集計する';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $by = $this->option('by');
        $includeBots = (bool) $this->option('include-bots');

        if ($by !== null && ! in_array($by, ['day', 'garage', 'operator'], true)) {
            $this->error("--by は day / garage / operator のいずれかです（指定値: {$by}）");

            return self::FAILURE;
        }

        $since = now()->subDays($days)->startOfDay();

        $base = RentalGarageClick::query()->where('clicked_at', '>=', $since);
        if (! $includeBots) {
            $base->where('is_bot', false);
        }

        $total = (clone $base)->count();
        $botTotal = RentalGarageClick::query()->where('clicked_at', '>=', $since)->where('is_bot', true)->count();

        $this->info(sprintf(
            '集計期間: %s 〜 現在（%d日間） / %s',
            $since->format('Y-m-d'),
            $days,
            $includeBots ? 'bot含む' : 'bot除外'
        ));
        $this->line(sprintf('送客クリック合計: <fg=green;options=bold>%d件</> （除外したbot判定: %d件）', $total, $botTotal));
        $this->newLine();

        if ($by === null || $by === 'day') {
            $this->reportByDay($base);
        }
        if ($by === null || $by === 'garage') {
            $this->reportByGarage($base);
        }
        if ($by === null || $by === 'operator') {
            $this->reportByOperator($base);
        }

        return self::SUCCESS;
    }

    private function reportByDay(\Illuminate\Database\Eloquent\Builder $base): void
    {
        $rows = (clone $base)
            ->selectRaw('DATE(clicked_at) as d, COUNT(*) as c')
            ->groupBy('d')
            ->orderBy('d')
            ->get();

        $this->line('<options=bold>■ 日別</>');
        if ($rows->isEmpty()) {
            $this->line('  （データなし）');
            $this->newLine();

            return;
        }
        $this->table(['日付', '件数'], $rows->map(fn ($r) => [$r->d, (int) $r->c])->all());
        $this->newLine();
    }

    private function reportByGarage(\Illuminate\Database\Eloquent\Builder $base): void
    {
        // 事業者・ガレージ名は rental_garages を join（削除済みも件数に残すため withTrashed 相当で soft delete を無視）。
        $rows = (clone $base)
            ->join('rental_garages', 'rental_garages.id', '=', 'rental_garage_clicks.rental_garage_id')
            ->selectRaw('rental_garages.id as gid, rental_garages.name as name, rental_garages.operator as operator, COUNT(*) as c')
            ->groupBy('gid', 'name', 'operator')
            ->orderByDesc('c')
            ->get();

        $this->line('<options=bold>■ ガレージ別</>');
        if ($rows->isEmpty()) {
            $this->line('  （データなし）');
            $this->newLine();

            return;
        }
        $this->table(
            ['ID', 'ガレージ名', '事業者', '件数'],
            $rows->map(fn ($r) => [$r->gid, $r->name, $r->operator ?? '（未設定）', (int) $r->c])->all()
        );
        $this->newLine();
    }

    private function reportByOperator(\Illuminate\Database\Eloquent\Builder $base): void
    {
        $rows = (clone $base)
            ->join('rental_garages', 'rental_garages.id', '=', 'rental_garage_clicks.rental_garage_id')
            ->selectRaw('rental_garages.operator as operator, COUNT(*) as c')
            ->groupBy('operator')
            ->orderByDesc('c')
            ->get();

        $this->line('<options=bold>■ 事業者別</>');
        if ($rows->isEmpty()) {
            $this->line('  （データなし）');
            $this->newLine();

            return;
        }
        $this->table(
            ['事業者', '件数'],
            $rows->map(fn ($r) => [$r->operator ?? '（未設定）', (int) $r->c])->all()
        );
        $this->newLine();
    }
}
