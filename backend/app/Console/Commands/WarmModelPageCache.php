<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class WarmModelPageCache extends Command
{
    protected $signature = 'cache:warm-models';

    protected $description = 'Warm cache for all bike model pages';

    public function handle(): int
    {
        $baseUrl = config('app.url');
        $startTime = microtime(true);
        $success = 0;
        $fail = 0;

        $total = BikeModel::whereHas('manufacturer')->count();
        $this->info("キャッシュウォーマー開始: {$total} モデル");

        $index = 0;

        BikeModel::with('manufacturer')
            ->whereHas('manufacturer')
            ->chunkById(100, function ($models) use ($baseUrl, &$index, &$success, &$fail, $total) {
                foreach ($models as $model) {
                    $index++;
                    $path = $model->seo_url;
                    $url = $baseUrl . $path;

                    try {
                        $t = microtime(true);
                        $response = Http::timeout(30)
                            ->withHeaders(['User-Agent' => 'MotoHub-CacheWarmer/1.0'])
                            ->get($url);
                        $ms = (int) ((microtime(true) - $t) * 1000);

                        if ($response->successful()) {
                            $this->info("[{$index}/{$total}] OK {$path} ({$ms}ms)");
                            $success++;
                        } else {
                            $this->warn("[{$index}/{$total}] FAIL {$path} (status: {$response->status()})");
                            $fail++;
                        }
                    } catch (\Throwable $e) {
                        $this->warn("[{$index}/{$total}] ERROR {$path} ({$e->getMessage()})");
                        Log::warning("CacheWarmer error: {$path}", ['error' => $e->getMessage()]);
                        $fail++;
                    }

                    usleep(500_000);
                }
            });

        $elapsed = (int) (microtime(true) - $startTime);
        $this->newLine();
        $this->info("完了: 成功={$success} 失敗={$fail} 所要時間={$elapsed}秒");

        return self::SUCCESS;
    }
}
