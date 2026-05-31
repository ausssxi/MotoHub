<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class WarmModelPageCache extends Command
{
    protected $signature = 'cache:warm-models {--all : 全モデルを対象にする（デフォルトは直近24時間に変動があったモデルのみ）}';

    protected $description = 'Warm cache for bike model pages';

    public function handle(): int
    {
        $baseUrl = config('app.url');
        $startTime = microtime(true);
        $success = 0;
        $fail = 0;

        $query = BikeModel::with('manufacturer')->whereHas('manufacturer');

        if (! $this->option('all')) {
            $query->whereHas('listings', fn ($q) => $q->where('updated_at', '>=', now()->subDay()));
            $mode = '変動分のみ';
        } else {
            $mode = '全件';
        }

        $total = (clone $query)->count();
        $this->info("キャッシュウォーマー開始（{$mode}）: {$total} モデル");

        if ($total === 0) {
            $this->info('対象モデルなし — スキップ');

            return self::SUCCESS;
        }

        $index = 0;

        $query->chunkById(100, function ($models) use ($baseUrl, &$index, &$success, &$fail, $total) {
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

                sleep(5);
            }
        });

        $elapsed = (int) (microtime(true) - $startTime);
        $this->newLine();
        $this->info("完了: 成功={$success} 失敗={$fail} 所要時間={$elapsed}秒");

        return self::SUCCESS;
    }
}
