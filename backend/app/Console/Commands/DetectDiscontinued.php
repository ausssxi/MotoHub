<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\Listing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class DetectDiscontinued extends Command
{
    protected $signature = 'models:detect-discontinued
                            {--dry-run : 変更内容をプレビュー（DBに保存しない）}
                            {--reset : 全モデルのis_discontinuedをリセットしてから再判定}';

    protected $description = '在庫データからバイクの生産終了（絶版）を自動判定';

    private const MODEL_YEAR_THRESHOLD = 2020;

    public function handle(): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $isReset = (bool) $this->option('reset');

        if ($isReset && ! $isDryRun) {
            BikeModel::where('is_discontinued', true)->update([
                'is_discontinued' => false,
                'production_end_year' => null,
            ]);
            $this->info('全モデルの絶版フラグをリセットしました。');
        }

        // 各モデルのmodel_year統計を取得
        $modelStats = Listing::whereNotNull('bike_model_id')
            ->whereNotNull('model_year')
            ->where('model_year', '>', 1970)
            ->where('model_year', '<=', (int) now()->format('Y'))
            ->select(
                'bike_model_id',
                DB::raw('MAX(model_year) as max_year'),
                DB::raw('COUNT(*) as total_listings')
            )
            ->groupBy('bike_model_id')
            ->having('total_listings', '>=', 3)
            ->get()
            ->keyBy('bike_model_id');

        // 直近1年間に新車（今年or去年）の在庫登録があるモデルを除外
        $currentYear = (int) now()->format('Y');
        $recentModelIds = Listing::whereNotNull('bike_model_id')
            ->whereNotNull('model_year')
            ->where('model_year', '>=', $currentYear - 1)
            ->where('created_at', '>=', now()->subYear())
            ->distinct()
            ->pluck('bike_model_id')
            ->toArray();

        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $modelStats->keys())
            ->where('name', '!=', '他車種')
            ->orderBy('manufacturer_id')
            ->orderBy('name')
            ->get();

        $tableRows = [];
        $detected = 0;

        foreach ($models as $model) {
            $stat = $modelStats->get($model->id);
            if (! $stat) {
                continue;
            }

            $maxYear = (int) $stat->max_year;

            // 判定: max_yearが閾値以下 AND 直近1年に新車登録なし
            if ($maxYear > self::MODEL_YEAR_THRESHOLD) {
                continue;
            }

            if (in_array($model->id, $recentModelIds, true)) {
                continue;
            }

            $maker = $model->manufacturer?->name ?? '';

            $tableRows[] = [
                $model->id,
                $maker,
                $model->displayLabel(),
                $maxYear,
                $stat->total_listings,
            ];

            if (! $isDryRun) {
                $model->update([
                    'is_discontinued' => true,
                    'production_end_year' => $maxYear,
                ]);
            }

            $detected++;
        }

        if (count($tableRows) > 0) {
            $this->table(
                ['ID', 'メーカー', '車種名', '最終年式', '在庫数'],
                $tableRows
            );
        }

        $this->newLine();
        $label = $isDryRun ? 'プレビュー' : '更新';
        $this->info("{$label}: {$detected}件の絶版車を検出しました。");

        if ($isDryRun && $detected > 0) {
            $this->newLine();
            $this->comment('実行するには --dry-run を外してください:');
            $this->comment('  php artisan models:detect-discontinued');
        }

        return self::SUCCESS;
    }
}
