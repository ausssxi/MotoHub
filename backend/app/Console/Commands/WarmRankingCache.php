<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\Listing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class WarmRankingCache extends Command
{
    protected $signature = 'cache:warm-ranking
        {--daily : 主要4ページ + 前日変動分のモデル別ページ}
        {--full : 主要4ページ + 全モデル別ページ}';

    protected $description = 'Warm cache for ranking pages';

    private string $logFile;

    public function handle(): int
    {
        $this->logFile = storage_path('logs/ranking_cache_warmer.log');

        if (! $this->option('daily') && ! $this->option('full')) {
            $this->error('--daily または --full オプションを指定してください');

            return self::FAILURE;
        }

        $baseUrl = config('app.url');
        $startTime = microtime(true);
        $success = 0;
        $fail = 0;

        $mode = $this->option('full') ? '全件' : 'daily';
        $this->log("キャッシュウォーマー開始（{$mode}）");

        // 主要4ページ
        $mainPages = [
            '/ranking',
            '/ranking/daily',
            '/ranking/weekly',
            '/ranking/monthly',
        ];

        foreach ($mainPages as $path) {
            $result = $this->warmUrl($baseUrl . $path, $path);
            $result ? $success++ : $fail++;
            sleep(5);
        }

        // モデル別ページ
        if ($this->option('full')) {
            $modelIds = BikeModel::whereHas('manufacturer')
                ->pluck('id');
        } else {
            // --daily: 前日にsoldになったモデルのbikeModelIdを抽出
            $modelIds = Listing::where('is_sold_out', true)
                ->where('updated_at', '>=', now()->subDay()->startOfDay())
                ->where('updated_at', '<', now()->startOfDay())
                ->whereNotNull('bike_model_id')
                ->distinct()
                ->pluck('bike_model_id');
        }

        $total = count($mainPages) + $modelIds->count();
        $this->log("モデル別ページ: {$modelIds->count()} 件");

        $index = 0;
        foreach ($modelIds as $modelId) {
            $index++;
            $path = "/ranking/model/{$modelId}";
            $result = $this->warmUrl($baseUrl . $path, $path, $index, $modelIds->count());
            $result ? $success++ : $fail++;
            sleep(5);
        }

        $elapsed = (int) (microtime(true) - $startTime);
        $summary = "完了: 成功={$success} 失敗={$fail} 所要時間={$elapsed}秒";
        $this->log($summary);
        $this->newLine();
        $this->info($summary);

        return self::SUCCESS;
    }

    private function warmUrl(string $url, string $path, ?int $index = null, ?int $total = null): bool
    {
        $prefix = $index !== null ? "[{$index}/{$total}]" : '[main]';

        try {
            $t = microtime(true);
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'MotoHub-CacheWarmer/1.0'])
                ->get($url);
            $ms = (int) ((microtime(true) - $t) * 1000);

            if ($response->successful()) {
                $this->info("{$prefix} OK {$path} ({$ms}ms)");

                return true;
            }

            $msg = "{$prefix} FAIL {$path} (status: {$response->status()})";
            $this->warn($msg);
            $this->log($msg);

            return false;
        } catch (\Throwable $e) {
            $msg = "{$prefix} ERROR {$path} ({$e->getMessage()})";
            $this->warn($msg);
            $this->log($msg);

            return false;
        }
    }

    private function log(string $message): void
    {
        $timestamp = now()->format('Y-m-d H:i:s');
        file_put_contents($this->logFile, "[{$timestamp}] {$message}" . PHP_EOL, FILE_APPEND);
    }
}
