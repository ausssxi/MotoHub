<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 車種ごとの値下げ統計を集計し bike_model_price_drop_stats に保存する。
 *
 * 「待てば下がるのか」という買い手の関心に答えるデータ。既定はドライラン（表示のみ）で、
 * --execute を付けたときだけテーブルへ保存する。
 *
 * 値下げの判定条件は config/price_alerts.php の min_drop_amount / max_drop_ratio（通知と同基準）。
 * min_drop_ratio(1%) は通知専用のためここでは使わない。数値はベタ書きしない。
 *
 * 集計対象は since_date（price_histories 蓄積開始日）以降に MotoHub が確認した掲載に限る。
 * ★listings.created_at は「販売店が掲載した日」ではなく「MotoHub が最初に取得した日」。
 *   since_date より前から実在した車両は初回値下げまでの日数が過小になるため対象から外す。
 *
 * 性能: 集計は SQL 側で一括（ループ内クエリなし）。車種数は5,000件規模だが、集計SQLは3本のみ。
 */
final class ComputeModelPriceDropStats extends Command
{
    protected $signature = 'stats:model-price-drops {--execute : 集計結果をテーブルに保存する（既定はドライラン＝表示のみ）}';

    protected $description = '車種ごとの値下げ統計を集計し bike_model_price_drop_stats に保存（既定ドライラン。--executeで保存）';

    public function handle(): int
    {
        $minAmount = (int) config('price_alerts.min_drop_amount', 5000);
        $maxRatio = (float) config('price_alerts.max_drop_ratio', 0.5);
        $since = (string) config('price_alerts.model_stats.since_date', '2026-03-07');
        $minListings = (int) config('price_alerts.model_stats.min_listing_count', 5);

        $this->info("集計条件: since={$since} / 最低件数={$minListings} / 値下げ>= {$minAmount}円 かつ 率<= ".($maxRatio * 100).'%');

        // ── A: 集計対象の掲載数（since 以降に確認した掲載）を車種別に ──
        $listingCounts = DB::table('listings')
            ->where('created_at', '>=', $since)
            ->whereNotNull('bike_model_id')
            ->groupBy('bike_model_id')
            ->select('bike_model_id', DB::raw('COUNT(*) as listing_count'))
            ->pluck('listing_count', 'bike_model_id');

        // ── B: 条件を満たす値下げの集計（回数/経験掲載数/平均額/平均率）を車種別に ──
        $dropAgg = DB::table('price_histories as ph')
            ->join('listings as l', 'l.id', '=', 'ph.listing_id')
            ->where('l.created_at', '>=', $since)
            ->whereNotNull('l.bike_model_id')
            ->whereColumn('ph.old_price', '>', 'ph.new_price')
            ->whereRaw('(ph.old_price - ph.new_price) >= ?', [$minAmount])
            ->whereRaw('(ph.old_price - ph.new_price) <= ph.old_price * ?', [$maxRatio])
            ->groupBy('l.bike_model_id')
            ->select(
                'l.bike_model_id',
                DB::raw('COUNT(DISTINCT ph.listing_id) as dropped_listing_count'),
                DB::raw('AVG(ph.old_price - ph.new_price) as avg_amount'),
                DB::raw('AVG((ph.old_price - ph.new_price) / ph.old_price) as avg_rate')
            )
            ->get()
            ->keyBy('bike_model_id');

        // ── C: 初回値下げまでの平均日数。listing 単位で最初の該当値下げ→created_at からの日数を車種平均 ──
        $firstDropSub = DB::table('price_histories as ph')
            ->join('listings as l2', 'l2.id', '=', 'ph.listing_id')
            ->where('l2.created_at', '>=', $since)
            ->whereNotNull('l2.bike_model_id')
            ->whereColumn('ph.old_price', '>', 'ph.new_price')
            ->whereRaw('(ph.old_price - ph.new_price) >= ?', [$minAmount])
            ->whereRaw('(ph.old_price - ph.new_price) <= ph.old_price * ?', [$maxRatio])
            ->groupBy('ph.listing_id')
            ->select('ph.listing_id', DB::raw('MIN(ph.created_at) as first_drop_at'));

        $firstDropDays = DB::query()->fromSub($firstDropSub, 'fd')
            ->join('listings as l', 'l.id', '=', 'fd.listing_id')
            ->whereNotNull('l.bike_model_id')
            ->groupBy('l.bike_model_id')
            // DATEDIFF は MySQL（本番）。listings.created_at=MotoHub初回取得日を起点にする。
            ->select('l.bike_model_id', DB::raw('AVG(DATEDIFF(fd.first_drop_at, l.created_at)) as avg_days'))
            ->get()
            ->keyBy('bike_model_id');

        // ── 突き合わせて行を生成（車種単位の追加クエリは発行しない） ──
        $computedAt = now();
        $rows = [];
        foreach ($listingCounts as $modelId => $listingCount) {
            $listingCount = (int) $listingCount;
            if ($listingCount < $minListings) {
                continue; // サンプル不足は保存しない
            }
            $b = $dropAgg->get($modelId);
            $c = $firstDropDays->get($modelId);

            $rows[] = [
                'bike_model_id' => (int) $modelId,
                'listing_count' => $listingCount,
                'dropped_listing_count' => (int) ($b->dropped_listing_count ?? 0),
                'avg_first_drop_days' => $c !== null ? (int) round((float) $c->avg_days) : null,
                'avg_drop_amount' => $b !== null ? (int) round((float) $b->avg_amount) : null,
                // 率はフラクション（0〜0.5）→ 保存は％（小数1桁）。
                'avg_drop_rate' => $b !== null ? round((float) $b->avg_rate * 100, 1) : null,
                'computed_at' => $computedAt,
            ];
        }

        $this->info('保存対象の車種: '.count($rows).'件（掲載数 '.$minListings.'件以上）');

        // ── ドライラン: 値下げされた割合の高い/低い上位20件（記事用） ──
        $this->printRankings($rows);

        if (! $this->option('execute')) {
            $this->newLine();
            $this->warn('ドライランです。テーブルへ保存するには --execute を付けてください。');

            return self::SUCCESS;
        }

        // ── 保存（冪等: 全件入れ替え。TRUNCATE は MySQL で暗黙コミットするため DELETE で） ──
        DB::transaction(function () use ($rows) {
            DB::table('bike_model_price_drop_stats')->delete();
            foreach (array_chunk($rows, 1000) as $chunk) {
                DB::table('bike_model_price_drop_stats')->insert($chunk);
            }
        });

        $this->info('保存しました: '.count($rows).'件を bike_model_price_drop_stats へ。');

        return self::SUCCESS;
    }

    /**
     * ドライラン表示: 値下げされた割合が高い/低い上位20件を、集計対象の掲載数つきで並べる。
     * 記事（「値下げされやすい車種／されにくい車種」）の素材にするための出力。保存内容には影響しない。
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function printRankings(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        // 車種名をまとめて1クエリで解決（ループ内クエリを避ける）。
        $names = DB::table('bike_models as bm')
            ->leftJoin('manufacturers as m', 'm.id', '=', 'bm.manufacturer_id')
            ->whereIn('bm.id', array_column($rows, 'bike_model_id'))
            ->select('bm.id', 'bm.name', 'm.name as maker')
            ->get()
            ->keyBy('id');

        // 割合（%）を付与。並べ替え用。
        $withRate = array_map(function (array $r) use ($names): array {
            $n = $names->get($r['bike_model_id']);
            $r['drop_pct'] = $r['listing_count'] > 0
                ? $r['dropped_listing_count'] / $r['listing_count'] * 100
                : 0.0;
            $r['label'] = trim((string) ($n->maker ?? '').' '.($n->name ?? "id={$r['bike_model_id']}"));

            return $r;
        }, $rows);

        $high = $withRate;
        usort($high, fn ($a, $b) => [$b['drop_pct'], $b['listing_count']] <=> [$a['drop_pct'], $a['listing_count']]);
        $low = $withRate;
        usort($low, fn ($a, $b) => [$a['drop_pct'], $b['listing_count']] <=> [$b['drop_pct'], $a['listing_count']]);

        $this->newLine();
        $this->line('==== 値下げされた割合が高い車種 TOP20（記事用）====');
        $this->renderList(array_slice($high, 0, 20));

        $this->newLine();
        $this->line('==== 値下げされた割合が低い車種 TOP20（記事用）====');
        $this->renderList(array_slice($low, 0, 20));
    }

    /** @param  array<int, array<string, mixed>>  $list */
    private function renderList(array $list): void
    {
        foreach ($list as $i => $r) {
            $this->line(sprintf(
                '  %2d. %5.1f%%  (%d/%d台)  初回%s日  平均%s円  %s',
                $i + 1,
                $r['drop_pct'],
                $r['dropped_listing_count'],
                $r['listing_count'],
                $r['avg_first_drop_days'] !== null ? (string) $r['avg_first_drop_days'] : '-',
                $r['avg_drop_amount'] !== null ? number_format((int) $r['avg_drop_amount']) : '-',
                $r['label'],
            ));
        }
    }
}
