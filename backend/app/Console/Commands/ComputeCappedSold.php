<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 成約判定（cappedSold 条件 #1〜#3）を日次で事前計算し listings.is_capped_sold に保存する。
 *
 * 条件:
 *   #1 is_sold_out = 1
 *   #2 created_at <= updated_at - 3日（出品から3日以上＝即時消去ノイズ除外）
 *   #3 (shop_id, bike_model_id, DATE(updated_at)) 内で updated_at 昇順 ROW_NUMBER <= 5（水増し対策）
 *
 * 重い ROW_NUMBER() ウィンドウを1日1回だけ実行し、以降のリクエストはフラグ参照だけで済むようにする。
 * ※ updated_at は成約時刻として全集計の基準。本コマンドは is_capped_sold 以外を一切変更しない。
 * ※ excludeBulkSold(Redis) はここに含めない（直交・呼び出し側でチェーン）。
 */
final class ComputeCappedSold extends Command
{
    protected $signature = 'listings:compute-capped-sold';

    protected $description = '成約判定(cappedSold条件#1〜#3)を事前計算し listings.is_capped_sold を更新する';

    public function handle(): int
    {
        $start = microtime(true);

        // 「掲載3日以上」はドライバ依存（scopeCappedSold と同一の分岐）。
        $intervalExpr = DB::connection()->getDriverName() === 'mysql'
            ? 'created_at <= updated_at - INTERVAL 3 DAY'
            : "created_at <= datetime(updated_at, '-3 days')";

        // 期間フィルタは付けない：cap は (…, DATE(updated_at)) パーティションで日単位に閉じるため、
        // 全期間で1回計算した順位が、任意の期間クエリと一致する（期間非依存）。
        // ★重要: listings.updated_at は `ON UPDATE CURRENT_TIMESTAMP`。is_capped_sold だけを更新しても
        //   updated_at が現在時刻に書き換わり、成約時刻（全集計の基準）を破壊する。
        //   各 UPDATE で updated_at = updated_at を明示し、自動更新を抑止する（値が変わらないため発火しない）。
        DB::transaction(function () use ($intervalExpr): void {
            // 1) 既存フラグをリセット（updated_at は据え置き）
            DB::table('listings')->where('is_capped_sold', true)
                ->update(['is_capped_sold' => false, 'updated_at' => DB::raw('updated_at')]);

            // 2) 条件#1〜#3を満たす行に立てる。派生テーブルで materialize（自己更新の 1093 回避）。
            //    updated_at = updated_at で ON UPDATE 自動更新を抑止。
            DB::statement("
                UPDATE listings SET is_capped_sold = 1, updated_at = updated_at
                WHERE id IN (
                    SELECT id FROM (
                        SELECT id, ROW_NUMBER() OVER (
                            PARTITION BY shop_id, bike_model_id, DATE(updated_at)
                            ORDER BY updated_at
                        ) as rn
                        FROM listings
                        WHERE is_sold_out = 1
                        AND {$intervalExpr}
                    ) as capped
                    WHERE rn <= 5
                )
            ");
        });

        $flagged = DB::table('listings')->where('is_capped_sold', true)->count();
        $elapsed = round(microtime(true) - $start, 1);

        $this->info("is_capped_sold 更新完了: {$flagged} 件 / {$elapsed}秒");

        return self::SUCCESS;
    }
}
