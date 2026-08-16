<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

final class ComputeBulkExclusions extends Command
{
    protected $signature = 'ranking:compute-bulk-exclusions';

    protected $description = '一括sold_out除外対象を listings.is_bulk_sold に事前計算する';

    /** 同一 bike_model_id × updated_at で何件以上を除外とするか */
    private const THRESHOLD = 10;

    /**
     * UPDATE の分割単位。一括UPDATEはテーブルロックでサイトが数分重くなるため必須
     * （31万行を1文で更新して障害になった実績あり）。リセット・セットとも 1,000件ずつ回す。
     */
    private const UPDATE_CHUNK = 1000;

    public function handle(): int
    {
        $this->info('一括sold_out除外フラグの計算を開始...');
        $start = microtime(true);

        // 対象IDを算出（従来の検出クエリと同一）。同一 bike_model_id × updated_at（秒精度）で THRESHOLD件以上。
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

        // 1) 既存フラグをリセット（1 → 0）。1,000件ずつ whereIn で分割UPDATE（一括UPDATE禁止）。
        //    立っている行を1,000件ずつ取り出して落とす→再取得、を空になるまで繰り返す。
        $resetTotal = 0;
        do {
            $resetIds = DB::table('listings')
                ->where('is_bulk_sold', true)
                ->limit(self::UPDATE_CHUNK)
                ->pluck('id')
                ->all();

            if ($resetIds === []) {
                break;
            }

            DB::table('listings')
                ->whereIn('id', array_map('intval', $resetIds))
                ->update(['is_bulk_sold' => false]);
            $resetTotal += count($resetIds);
        } while (count($resetIds) === self::UPDATE_CHUNK);

        // 3) 対象に is_bulk_sold = 1 を立てる。1,000件ずつ whereIn で分割UPDATE（一括UPDATE禁止）。
        foreach (array_chunk($ids, self::UPDATE_CHUNK) as $chunk) {
            DB::table('listings')
                ->whereIn('id', array_map('intval', $chunk))
                ->update(['is_bulk_sold' => true]);
        }

        $elapsed = round(microtime(true) - $start, 2);
        $this->info("完了: リセット {$resetTotal} 件 / 除外フラグ {$count} 件 ({$elapsed}秒)");

        return self::SUCCESS;
    }
}
