<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Parts\ProductSearchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * パーツカテゴリごとの実勢価格を商品検索から集計し parts_category_price_stats に保存する。
 *
 * config/parts-categories.php の price_range はベタ書きなので、実際の楽天/Yahoo 価格から四分位で算出した値を
 * 別テーブルに貯める（ページ側の表示切替は別タスク。まずデータを作って分布を見る）。
 *
 * 作法は UpdateFitmentCosts を踏襲:
 *  - 取得は必ず {@see ProductSearchService::searchProducts}（→ 共有 {@see \App\Support\RakutenRateGate}）経由。
 *    Http::get は直接呼ばない。並列化しない・取得間隔を短くしない（10カテゴリ×keyword数を順次実行）。
 *  - 下限/上限は単純 min/max ではなく四分位（Q1/中央値/Q3）。min/max も参考として保存。
 *
 * サンプルを増やすため keywords[] の全要素で検索して統合し、同一商品（商品名+価格が同じ）を重複除去する。
 *
 * 既定はドライラン（表示のみ）。--execute で保存。
 */
final class ComputePartsCategoryPrices extends Command
{
    protected $signature = 'parts:compute-category-prices
        {--execute : 集計結果をテーブルに保存する（既定はドライラン＝表示のみ）}
        {--hits= : 1キーワードあたりの取得件数（既定は config parts-price-stats.hits。最大30に丸め）}';

    protected $description = 'パーツカテゴリごとの実勢価格を商品検索(keywords全件)から四分位で集計し parts_category_price_stats に保存（既定dry-run）';

    public function handle(ProductSearchService $service): int
    {
        $categories = (array) config('parts-categories', []);
        $minProducts = (int) config('parts-price-stats.min_products', 20);
        $hits = $this->option('hits') !== null
            ? max(1, (int) $this->option('hits'))
            : (int) config('parts-price-stats.hits', 30);
        $write = (bool) $this->option('execute');

        $this->newLine();
        $this->line('==== parts:compute-category-prices '.($write ? '（本書き込み）' : '（dry-run・DBへは書き込みません）').' ====');
        $this->line('カテゴリ数: '.count($categories).' / 1キーワード取得: '.$hits.'件 / 最小保存件数: '.$minProducts.'件');
        $this->comment('※ 取得は ProductSearchService(→RakutenRateGate) 経由・順次。keywords[] 全件で検索し (商品名+価格) で重複除去。');

        $computedAt = now();
        $toSave = [];
        $skipped = [];

        foreach ($categories as $category) {
            $slug = (string) ($category['slug'] ?? '');
            $name = (string) ($category['name'] ?? $slug);

            // 検索語は keywords[]。空ならカテゴリ名にフォールバック（その旨表示）。
            $keywords = array_values(array_filter(
                array_map('strval', (array) ($category['keywords'] ?? [])),
                fn ($k) => trim($k) !== ''
            ));
            $usedFallback = false;
            if ($keywords === []) {
                $keywords = [$name];
                $usedFallback = true;
            }

            $this->newLine();
            $this->line("■ {$name}（{$slug}）".($usedFallback ? '  ※keywords無→カテゴリ名で検索' : ''));

            // keyword ごとに検索（各回 RakutenRateGate を通過）。商品名+価格で重複除去して統合。
            $seen = [];         // "name\0price" => true
            $prices = [];       // int[] 重複除去後の価格
            $perKeyword = [];   // 表示用: keyword => 取得件数（価格>0）
            foreach ($keywords as $kw) {
                $result = $service->searchProducts($kw, $hits);
                $kwCount = 0;
                foreach ($result as $item) {
                    $price = (int) ($item['price'] ?? 0);
                    if ($price <= 0) {
                        continue;
                    }
                    $kwCount++;
                    $key = trim((string) ($item['name'] ?? ''))."\0".$price;
                    if (isset($seen[$key])) {
                        continue; // 同一商品（名前+価格）の重複
                    }
                    $seen[$key] = true;
                    $prices[] = $price;
                }
                $perKeyword[$kw] = $kwCount;
            }

            // キーワードごとの取得件数（どのキーワードが効いているか）。
            foreach ($perKeyword as $kw => $cnt) {
                $this->line(sprintf('    keyword「%s」: 取得%d件', $kw, $cnt));
            }

            $count = count($prices);
            $fetchedTotal = array_sum($perKeyword);
            $this->line(sprintf('    → 統合 取得計%d件 / 重複除去後 %d件（重複 %d件を除外）', $fetchedTotal, $count, $fetchedTotal - $count));

            // 現行 config のベタ書き値（乖離の目視用）。
            $cfg = (array) ($category['price_range'] ?? []);
            $cfgStr = sprintf(
                'config: min=%s / avg=%s / max=%s',
                isset($cfg['min']) ? number_format((int) $cfg['min']) : '-',
                isset($cfg['average']) ? number_format((int) $cfg['average']) : '-',
                isset($cfg['max']) ? number_format((int) $cfg['max']) : '-',
            );

            if ($count < $minProducts) {
                $skipped[] = $slug;
                $this->warn(sprintf('    skip: %d件 < 最小%d件のため保存しない  （%s）', $count, $minProducts, $cfgStr));

                continue;
            }

            sort($prices);
            $q1 = $this->quartile($prices, 0.25);
            $median = $this->quartile($prices, 0.5);
            $q3 = $this->quartile($prices, 0.75);
            $min = $prices[0];
            $max = $prices[$count - 1];

            $this->line(sprintf(
                '    実データ: Q1=%s / 中央=%s / Q3=%s（min=%s / max=%s）  %s',
                number_format($q1),
                number_format($median),
                number_format($q3),
                number_format($min),
                number_format($max),
                $cfgStr,
            ));

            $toSave[] = [
                'category_slug' => $slug,
                'product_count' => $count,
                'price_q1' => $q1,
                'price_median' => $median,
                'price_q3' => $q3,
                'price_min' => $min,
                'price_max' => $max,
                'computed_at' => $computedAt,
            ];
        }

        $this->newLine();
        $this->line('==== 集計 ====');
        $this->line('保存対象カテゴリ: '.count($toSave).'件 / スキップ（件数不足）: '.count($skipped).'件'
            .($skipped !== [] ? '（'.implode(', ', $skipped).'）' : ''));

        if (! $write) {
            $this->newLine();
            $this->comment('※ dry-run のため DB は変更していません。保存するには --execute を付けてください。');

            return self::SUCCESS;
        }

        // カテゴリ単位で冪等に upsert（unique(category_slug)）。
        foreach ($toSave as $row) {
            DB::table('parts_category_price_stats')->updateOrInsert(
                ['category_slug' => $row['category_slug']],
                $row,
            );
        }
        $this->info('保存しました: '.count($toSave).'件を parts_category_price_stats へ。');

        return self::SUCCESS;
    }

    /**
     * 昇順ソート済み配列の分位点（線形補間）。UpdateFitmentCosts::quartile と同一実装。
     * min/max ではなく四分位を採り、まとめ買い・別カテゴリ商品の外れ値を下位/上位25%として除外する。
     *
     * @param  array<int, int>  $sorted 昇順ソート済みの価格配列
     */
    private function quartile(array $sorted, float $q): int
    {
        $n = count($sorted);
        if ($n === 1) {
            return $sorted[0];
        }

        $pos = $q * ($n - 1);
        $lo = (int) floor($pos);
        $hi = (int) ceil($pos);
        if ($lo === $hi) {
            return $sorted[$lo];
        }

        $frac = $pos - $lo;

        return (int) round($sorted[$lo] + ($sorted[$hi] - $sorted[$lo]) * $frac);
    }
}
