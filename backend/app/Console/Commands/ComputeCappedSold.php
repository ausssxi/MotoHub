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
 *
 * ■ 書き込み方式（2026-08-12 変更・判定条件は不変）
 *   旧: 単一の DB::transaction 内で「全部 false にする」→「あるべき行に true を立て直す」の2文。
 *       listings 36万行の約2/3（238,497件）を毎晩書き換え、449秒間ロックを保持していた。
 *       その間に販売中の車両を見た閲覧者が 1205（Lock wait timeout）で50秒待たされていた
 *       （2026-08-04〜08-11 に11件）。
 *   新: あるべき集合をロックを取らない SELECT で求め、現状と突き合わせて
 *       「0→1 になる行」「1→0 になる行」だけをチャンク単位で更新し、チャンクごとにコミットする。
 *       全体を囲うトランザクションは張らない。
 *       - 変化しない行（大半）は一切触らないので、書き込み量が日々の変化分まで落ちる
 *       - 1回の UPDATE は主キー指定の数百〜千行のみ・即コミットなので、ロック保持は数ミリ秒
 *       - 「全部 false」の中間状態が無くなるため、実行中に集計を読んでも0件にならない
 *   ★ 判定に使うウィンドウクエリ（cappedIdsSql）は旧実装の副問い合わせと同一。
 *     変えたのは「求めた結果をどう書き込むか」だけ。
 */
final class ComputeCappedSold extends Command
{
    protected $signature = 'listings:compute-capped-sold
        {--chunk=1000 : 1回の UPDATE で更新する件数（コミット単位）}';

    protected $description = '成約判定(cappedSold条件#1〜#3)を事前計算し listings.is_capped_sold を更新する';

    public function handle(): int
    {
        $start = microtime(true);
        $chunkSize = max(1, (int) $this->option('chunk'));

        // 「掲載3日以上」はドライバ依存（scopeCappedSold と同一の分岐）。
        $intervalExpr = DB::connection()->getDriverName() === 'mysql'
            ? 'created_at <= updated_at - INTERVAL 3 DAY'
            : "created_at <= datetime(updated_at, '-3 days')";

        // 1) あるべき集合。ロックを取らない単独の SELECT（consistent read）なので、
        //    36万行のウィンドウソートに何分かかっても他セッションを一切ブロックしない。
        //    ここが旧実装との最大の違い（旧は UPDATE の副問い合わせだったため走査行をロックしていた）。
        $should = [];
        foreach (DB::cursor($this->cappedIdsSql($intervalExpr)) as $row) {
            $should[(int) $row->id] = true;
        }

        // 2) 現在フラグが立っている集合。
        $current = [];
        foreach (DB::table('listings')->where('is_capped_sold', true)->select('id')->cursor() as $row) {
            $current[(int) $row->id] = true;
        }

        // 3) 差分だけを取り出す。
        $toAdd = array_keys(array_diff_key($should, $current));      // 0 → 1
        $toRemove = array_keys(array_diff_key($current, $should));   // 1 → 0
        $unchanged = count($should) - count($toAdd);                 // 立ったままで変化なし

        unset($should, $current);

        // 4) チャンクごとに更新してコミット（全体を囲うトランザクションは張らない）。
        //    主キー指定なので走査は発生せず、ロックは対象行だけ・statement 単位で解放される。
        //    updated_at = updated_at で ON UPDATE CURRENT_TIMESTAMP の自動更新を抑止する
        //    （値が変わらないため発火しない。成約時刻を壊さないための必須指定）。
        $this->applyFlag($toAdd, true, $chunkSize);
        $this->applyFlag($toRemove, false, $chunkSize);

        $flagged = DB::table('listings')->where('is_capped_sold', true)->count();
        $elapsed = round(microtime(true) - $start, 1);

        $this->info("is_capped_sold 更新完了: {$flagged} 件 / {$elapsed}秒");
        $this->line(sprintf(
            '  内訳: 0→1 %d 件 / 1→0 %d 件 / 変化なし %d 件（チャンク %d 件）',
            count($toAdd),
            count($toRemove),
            $unchanged,
            $chunkSize
        ));

        return self::SUCCESS;
    }

    /**
     * 与えた id 群の is_capped_sold を chunkSize 件ずつ更新する。
     * 各 UPDATE は暗黙のトランザクションで即コミットされる（囲いを作らないのが要点）。
     *
     * @param  list<int>  $ids
     */
    private function applyFlag(array $ids, bool $value, int $chunkSize): void
    {
        foreach (array_chunk($ids, $chunkSize) as $chunk) {
            DB::table('listings')
                ->whereIn('id', $chunk)
                ->update([
                    'is_capped_sold' => $value,
                    'updated_at' => DB::raw('updated_at'),
                ]);
        }
    }

    /**
     * 条件#1〜#3を満たす listing の id を返す SELECT。
     *
     * ★ 中身は旧実装の UPDATE 副問い合わせと同一（PARTITION / ORDER BY / rn <= 5 / WHERE 条件）。
     *   単独の SELECT として実行する点だけが違う。判定結果は変わらない。
     */
    private function cappedIdsSql(string $intervalExpr): string
    {
        return "
            SELECT id FROM (
                SELECT id, ROW_NUMBER() OVER (
                    PARTITION BY shop_id, bike_model_id, DATE(updated_at)
                    ORDER BY updated_at, id
                ) as rn
                FROM listings
                WHERE is_sold_out = 1
                AND {$intervalExpr}
            ) as capped
            WHERE rn <= 5
        ";
    }
}
