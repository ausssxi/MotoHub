<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 地域別中古相場（中央値）の事前計算。
 *
 * - 母集団: active 在庫（is_sold_out=0 かつ total_price>0）。
 * - 地域: listing→shop join で shops.prefecture → config/regions.php で8ブロック解決。
 * - per-shopキャップ: 同一(shop×bike_model)の寄与を最大5件に制限（1業者の大量出品が
 *   中央値を引っ張るのを防ぐ。お得バッジ94%問題の二の舞回避）。並びは l.id 順＝
 *   価格非依存のサンプル（total_price順だと安値5件採用で中央値が下方バイアスするため）。
 * - (bike_model_id × region_block) ごとに total_price の中央値・件数を算出。
 * - 全国行: 同モデルの全ブロック合算（同じキャップ適用）を region_block='全国' に。
 * - ゲート: listing_count >= 10 のセルのみ保存（薄いブロックの中央値ノイズを表から除外。
 *   ※これは「モデルページ表示」のコンテンツ品質ゲート。area×model LP の noindex閾値5とは別物）。
 * - 冪等: 全件 DELETE→再投入をトランザクションで（TRUNCATEはMySQLで暗黙コミットを起こすため不可）。
 */
final class ComputeRegionalPrices extends Command
{
    protected $signature = 'stats:regional-prices';

    protected $description = '地域別中古相場（中央値）を bike_model × 8地方ブロックで事前計算する';

    /** per-shop×model の寄与上限。既存の area×model LP noindex閾値と同値。 */
    private const PER_SHOP_CAP = 5;

    /**
     * セル保存（=モデルページ表示）の最低台数ゲート。薄いブロックの中央値は振れやすいため10に設定。
     * ⚠️ area×model LP の noindex閾値(5)とは独立。混同しないこと。
     */
    private const MIN_LISTINGS = 10;

    public function handle(): int
    {
        $prefToBlock = $this->buildPrefToBlock();
        $nationalLabel = (string) config('regions.national_label', '全国');

        // キャッチオール/非バイクのモデルを集計から除外（誤価格・異種混在の中央値混入を断つ）。
        $excludedModelIds = $this->resolveExcludedModelIds();
        $this->info('除外モデル（キャッチオール/非バイク）: '.count($excludedModelIds).'件');

        // per-shop×model キャップ後の (model, prefecture, price) をストリーム取得。
        // ROW_NUMBER は MySQL8 / sqlite3.25+ 双方対応のため driver 分岐不要。
        $sub = DB::table('listings as l')
            ->join('shops as s', 's.id', '=', 'l.shop_id')
            ->where('l.is_sold_out', 0)
            ->whereNotNull('l.total_price')
            ->where('l.total_price', '>', 0)
            ->whereNotNull('s.prefecture')
            ->when($excludedModelIds !== [], fn ($q) => $q->whereNotIn('l.bike_model_id', $excludedModelIds))
            ->select(
                'l.bike_model_id as bike_model_id',
                's.prefecture as prefecture',
                'l.total_price as total_price',
                DB::raw('ROW_NUMBER() OVER (PARTITION BY l.shop_id, l.bike_model_id ORDER BY l.id) as rn')
            );

        $buckets = [];        // [bike_model_id][block] => int[] prices
        $unmapped = [];       // prefecture => count（取りこぼし検知）
        $scanned = 0;

        DB::query()->fromSub($sub, 'capped')
            ->where('rn', '<=', self::PER_SHOP_CAP)
            ->select('bike_model_id', 'prefecture', 'total_price')
            ->orderBy('bike_model_id')
            ->cursor()
            ->each(function ($row) use (&$buckets, &$unmapped, &$scanned, $prefToBlock, $nationalLabel) {
                $scanned++;
                $block = $prefToBlock[$row->prefecture] ?? null;
                if ($block === null) {
                    $unmapped[$row->prefecture] = ($unmapped[$row->prefecture] ?? 0) + 1;

                    return;
                }
                $price = (int) $row->total_price;
                $buckets[$row->bike_model_id][$block][] = $price;
                $buckets[$row->bike_model_id][$nationalLabel][] = $price;
            });

        $this->info("キャップ後 {$scanned} 行を集計（per-shop上限 ".self::PER_SHOP_CAP.'件）');

        $computedAt = now();
        $insertRows = [];
        foreach ($buckets as $modelId => $blocks) {
            foreach ($blocks as $block => $prices) {
                $count = count($prices);
                if ($count < self::MIN_LISTINGS) {
                    continue; // ゲート未達は保存しない
                }
                $insertRows[] = [
                    'bike_model_id' => $modelId,
                    'region_block' => $block,
                    'median_price' => $this->median($prices),
                    'listing_count' => $count,
                    'computed_at' => $computedAt,
                ];
            }
        }

        // TRUNCATE は MySQL で暗黙コミットを起こしトランザクションを壊すため、
        // 冪等な全件入れ替えは DELETE（トランザクション安全）で行う。
        DB::transaction(function () use ($insertRows) {
            DB::table('model_region_price_stats')->delete();
            foreach (array_chunk($insertRows, 1000) as $chunk) {
                DB::table('model_region_price_stats')->insert($chunk);
            }
        });

        $this->info('保存セル数: '.count($insertRows).'（ゲート '.self::MIN_LISTINGS.'台以上）');

        if (! empty($unmapped)) {
            arsort($unmapped);
            $this->warn('マップ未解決の prefecture（集計から除外）: '.json_encode($unmapped, JSON_UNESCAPED_UNICODE));
        } else {
            $this->info('マップ未解決の prefecture: なし');
        }

        return self::SUCCESS;
    }

    /**
     * config/regions.php の catch_all_exclusions を解決し、除外する bike_model_id の集合を返す。
     * 明示 model_ids ∪ (name LIKE name_like)。
     *
     * @return array<int, int>
     */
    private function resolveExcludedModelIds(): array
    {
        $conf = (array) config('regions.catch_all_exclusions', []);
        $ids = array_map('intval', (array) ($conf['model_ids'] ?? []));

        foreach ((array) ($conf['name_like'] ?? []) as $pattern) {
            $matched = DB::table('bike_models')
                ->where('name', 'like', '%'.$pattern.'%')
                ->pluck('id')
                ->all();
            $ids = array_merge($ids, array_map('intval', $matched));
        }

        return array_values(array_unique($ids));
    }

    /**
     * config/regions.php の blocks を prefecture→block の平坦マップに変換。
     *
     * @return array<string, string>
     */
    private function buildPrefToBlock(): array
    {
        $map = [];
        foreach ((array) config('regions.blocks', []) as $block => $prefectures) {
            foreach ((array) $prefectures as $pref) {
                $map[$pref] = $block;
            }
        }

        return $map;
    }

    /**
     * 整数価格配列の中央値（偶数個は中央2値の平均、円単位に丸め）。
     *
     * @param  array<int, int>  $prices
     */
    private function median(array $prices): int
    {
        sort($prices);
        $n = count($prices);
        $mid = intdiv($n, 2);

        if ($n % 2 === 1) {
            return (int) $prices[$mid];
        }

        return (int) round(($prices[$mid - 1] + $prices[$mid]) / 2);
    }
}
