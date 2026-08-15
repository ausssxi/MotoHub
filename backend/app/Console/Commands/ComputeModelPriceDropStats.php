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
    protected $signature = 'stats:model-price-drops
        {--execute : 集計結果をテーブルに保存する（既定はドライラン＝表示のみ）}
        {--rank-min= : 記事用ランキング(TOP20)に使う最低の集計対象掲載数（既定は config rank_min_listing_count）}';

    protected $description = '車種ごとの値下げ統計を集計し bike_model_price_drop_stats に保存（既定ドライラン。--executeで保存）';

    public function handle(): int
    {
        $minAmount = (int) config('price_alerts.min_drop_amount', 5000);
        $maxRatio = (float) config('price_alerts.max_drop_ratio', 0.5);
        $since = (string) config('price_alerts.model_stats.since_date', '2026-03-07');
        $minListings = (int) config('price_alerts.model_stats.min_listing_count', 5);
        // ランキング用の母数しきい値（--rank-min 優先、無ければ config）。保存の minListings とは別。
        $rankMin = $this->option('rank-min') !== null
            ? (int) $this->option('rank-min')
            : (int) config('price_alerts.model_stats.rank_min_listing_count', 30);

        // 受け皿レコード（車種名に「その他」「他車種」等を含む分類の受け皿）を集計対象から除外。パターンは config。
        $excludePatterns = (array) config('price_alerts.model_stats.exclude_name_like', []);
        $excludedIds = $this->resolveExcludedModelIds($excludePatterns);
        $excludedSet = array_flip($excludedIds);

        $this->info("集計条件: since={$since} / 保存最低件数={$minListings} / ランキング最低件数={$rankMin} / 値下げ>= {$minAmount}円 かつ 率<= ".($maxRatio * 100).'%');
        $this->info('受け皿レコード（名称パターン一致・集計対象外）: '.count($excludedIds).'件');

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
            ->when($excludedIds !== [], fn ($q) => $q->whereNotIn('l.bike_model_id', $excludedIds))
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
            ->when($excludedIds !== [], fn ($q) => $q->whereNotIn('l2.bike_model_id', $excludedIds))
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
        $excludedFromSave = 0; // 受け皿のうち、掲載数が保存条件を満たしていたのに除外した数（＝実質除外件数）
        foreach ($listingCounts as $modelId => $listingCount) {
            $listingCount = (int) $listingCount;
            if (isset($excludedSet[(int) $modelId])) {
                if ($listingCount >= $minListings) {
                    $excludedFromSave++;
                }

                continue; // 受け皿レコードは保存しない
            }
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
        $this->info('受け皿レコードを保存対象から除外: '.$excludedFromSave.'件（掲載数 '.$minListings.'件以上に該当したもの）');

        // ── ドライラン: 値下げされた割合の高い/低い上位20件（記事用。母数は rankMin 件以上） ──
        $this->printRankings($rows, $rankMin);

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
     * ★保存対象($rows)のうち、さらに母数 $rankMin 件以上の車種だけをランキングに使う（小母数の「4/5台=80%」を排除）。
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function printRankings(array $rows, int $rankMin): void
    {
        // 記事ランキング用の母数しきい値で絞る（保存の最低件数とは別）。
        $ranked = array_values(array_filter($rows, fn (array $r) => (int) $r['listing_count'] >= $rankMin));

        $this->newLine();
        if ($ranked === []) {
            $this->warn("ランキング対象なし（掲載{$rankMin}件以上の車種が0件。--rank-min を下げて再確認してください）");

            return;
        }

        // 車種名をまとめて1クエリで解決（ループ内クエリを避ける）。
        $names = DB::table('bike_models as bm')
            ->leftJoin('manufacturers as m', 'm.id', '=', 'bm.manufacturer_id')
            ->whereIn('bm.id', array_column($ranked, 'bike_model_id'))
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
        }, $ranked);

        $high = $withRate;
        usort($high, fn ($a, $b) => [$b['drop_pct'], $b['listing_count']] <=> [$a['drop_pct'], $a['listing_count']]);
        $low = $withRate;
        usort($low, fn ($a, $b) => [$a['drop_pct'], $b['listing_count']] <=> [$b['drop_pct'], $a['listing_count']]);

        $this->line("==== 値下げされた割合が高い車種 TOP20（掲載{$rankMin}件以上の車種のうち・記事用）====");
        $this->renderList(array_slice($high, 0, 20));

        $this->newLine();
        $this->line("==== 値下げされた割合が低い車種 TOP20（掲載{$rankMin}件以上の車種のうち・記事用）====");
        $this->renderList(array_slice($low, 0, 20));
    }

    /**
     * config の名称パターン（部分一致）に一致する「受け皿」bike_model_id を解決する（1クエリ）。
     * 特定車種を指さない分類の受け皿（「その他」「他車種」等）を値下げ集計から外すために使う。
     *
     * @param  array<int, string>  $patterns
     * @return array<int, int>
     */
    private function resolveExcludedModelIds(array $patterns): array
    {
        $patterns = array_values(array_filter(array_map('strval', $patterns), fn ($p) => $p !== ''));
        if ($patterns === []) {
            return [];
        }

        return DB::table('bike_models')
            ->where(function ($q) use ($patterns) {
                foreach ($patterns as $p) {
                    $q->orWhere('name', 'like', '%'.$p.'%');
                }
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
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
