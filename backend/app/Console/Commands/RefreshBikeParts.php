<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Services\Bike\BikePartsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class RefreshBikeParts extends Command
{
    protected $signature = 'parts:refresh
        {--limit=800 : 1回の実行で取得する最大モデル数}
        {--all : キャッシュ有無を問わず取得する（デフォルトは未保持＝失効分のみ）}';

    protected $description = '在庫あり車種の楽天パーツをキャッシュへ事前取得（render pathから分離・日次ローテーション）';

    public function handle(BikePartsService $parts): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $all = (bool) $this->option('all');
        $startTime = microtime(true);

        // 在庫あり車種を人気順（active掲載数desc）に。失効分を優先的に埋める。
        $models = BikeModel::query()
            ->whereHas('listings', fn ($q) => $q->active())
            ->withCount(['listings' => fn ($q) => $q->active()])
            ->orderByDesc('listings_count')
            ->get(['id', 'name']);

        $this->info("在庫車種: {$models->count()} 件 / 今回上限: {$limit}".($all ? '（--all 全件再取得）' : '（失効分のみ）'));

        $done = 0;
        $skipped = 0;

        foreach ($models as $model) {
            if ($done >= $limit) {
                break;
            }

            if (! $all && Cache::has(BikePartsService::cacheKey($model))) {
                $skipped++;

                continue;
            }

            $parts->refreshForModel($model);
            $done++;
            $this->info("[{$done}/{$limit}] {$model->name} (#{$model->id})");
        }

        $elapsed = (int) (microtime(true) - $startTime);
        $this->newLine();
        $this->info("完了: refresh={$done} skip(cache有)={$skipped} 所要={$elapsed}秒");

        return self::SUCCESS;
    }
}
