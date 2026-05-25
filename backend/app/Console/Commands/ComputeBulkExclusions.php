<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

final class ComputeBulkExclusions extends Command
{
    protected $signature = 'ranking:compute-bulk-exclusions';

    protected $description = '一括sold_out除外対象のlisting IDを事前計算してRedisに保存';

    /** Redisキー */
    public const REDIS_KEY = 'bulk_sold_exclusion_ids';

    /** TTL: 24時間 */
    private const TTL_SECONDS = 86400;

    /** 同一 bike_model_id × updated_at で何件以上を除外とするか */
    private const THRESHOLD = 10;

    public function handle(): int
    {
        $this->info('一括sold_out除外IDの計算を開始...');
        $start = microtime(true);

        // 同一 bike_model_id × updated_at（秒精度）で THRESHOLD件以上のレコードを検出
        $ids = DB::table('listings')
            ->where('is_sold_out', true)
            ->whereNotNull('bike_model_id')
            ->whereIn(
                DB::raw('CONCAT(bike_model_id, "_", updated_at)'),
                DB::table('listings')
                    ->select(DB::raw('CONCAT(bike_model_id, "_", updated_at)'))
                    ->where('is_sold_out', true)
                    ->whereNotNull('bike_model_id')
                    ->groupBy('bike_model_id', 'updated_at')
                    ->havingRaw('COUNT(*) >= ?', [self::THRESHOLD])
            )
            ->pluck('id')
            ->all();

        $count = count($ids);

        if ($count > 0) {
            // SET型でRedisに保存（高速なSISMEMBER lookup）
            $redis = Redis::connection();
            $tempKey = self::REDIS_KEY . ':tmp';

            // 一時キーに書き込み → リネームでアトミック更新
            $redis->del($tempKey);
            foreach (array_chunk($ids, 1000) as $chunk) {
                $redis->sadd($tempKey, ...$chunk);
            }
            $redis->rename($tempKey, self::REDIS_KEY);
            $redis->expire(self::REDIS_KEY, self::TTL_SECONDS);
        } else {
            // 除外対象なし → キーを削除
            Redis::connection()->del(self::REDIS_KEY);
        }

        $elapsed = round(microtime(true) - $start, 2);
        $this->info("完了: 除外対象 {$count} 件 ({$elapsed}秒)");

        return self::SUCCESS;
    }
}
