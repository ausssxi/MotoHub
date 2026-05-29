<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use Illuminate\Console\Command;

/**
 * active掲載数の多い順に主要車種を一覧出力し、slug の品質を目視確認するコマンド。
 *
 * slug:generate / slug:generate-missing で生成した slug が
 * 主要車種で適切か（reburu-250 ではなく rebel-250 になっているか等）を素早く点検する。
 *
 * 使い方:
 *   php artisan slug:list-top                ← active掲載数TOP100
 *   php artisan slug:list-top --limit=50     ← 件数指定
 *   php artisan slug:list-top --null         ← slug=null の車種だけ表示
 */
final class ListTopModelSlugs extends Command
{
    protected $signature = 'slug:list-top
                            {--limit=100 : 出力件数（active掲載数の多い順）}
                            {--null : slugがnullの車種のみ表示}';

    protected $description = '主要車種（active掲載数順）のslugを一覧出力して品質を確認する';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $onlyNull = (bool) $this->option('null');

        $query = BikeModel::with('manufacturer')
            ->withCount(['listings as active_count' => fn ($q) => $q->active()])
            ->orderByDesc('active_count')
            ->limit($limit);

        if ($onlyNull) {
            $query->whereNull('slug');
        }

        $models = $query->get();

        $rows = $models->map(function (BikeModel $m, int $i) {
            return [
                $i + 1,
                $m->id,
                $m->manufacturer?->name ?? '—',
                $m->name,
                $m->slug ?? '(null)',
                number_format($m->active_count),
                $m->seo_url,
            ];
        })->all();

        $this->table(
            ['#', 'ID', 'メーカー', '車種名', 'slug', 'active台数', 'URL'],
            $rows,
        );

        $this->info("表示: {$models->count()}件" . ($onlyNull ? '（slug=nullのみ）' : ''));

        return Command::SUCCESS;
    }
}
