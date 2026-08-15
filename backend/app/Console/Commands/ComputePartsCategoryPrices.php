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
        $topProducts = (int) config('parts-price-stats.top_products', 12);
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
        $productsToSave = []; // category_slug => 保存する商品行[]
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
            // ★stats と商品カードで同じ配列を使う（同じ除外条件＝価格>0 と (名前+価格) 重複除去を共有）。
            $seen = [];         // "name\0price" => true
            $products = [];     // 重複除去後の商品（API関連度順を維持）
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
                    $title = trim((string) ($item['name'] ?? ''));
                    $key = $title."\0".$price;
                    if (isset($seen[$key])) {
                        continue; // 同一商品（名前+価格）の重複
                    }
                    $seen[$key] = true;
                    $shop = trim((string) ($item['shop'] ?? ''));
                    $mall = (string) ($item['mall'] ?? '');
                    $products[] = [
                        '_key' => $key,
                        'source' => $mall,
                        'title' => $title,
                        'price' => $price,
                        'shop_name' => $shop !== '' ? $shop : null,
                        'product_url' => (string) ($item['url'] ?? ''),
                        'image_url' => $this->normalizeImageUrl($mall, (string) ($item['image'] ?? '')),
                    ];
                }
                $perKeyword[$kw] = $kwCount;
            }

            $prices = array_column($products, 'price');

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

            // 商品カード用に上位を選ぶ（同じ商品配列から。追加APIなし）。
            $selected = $this->selectTopProducts($products, $q1, $q3, $topProducts);
            $productsToSave[$slug] = [];
            foreach ($selected as $i => $p) {
                $productsToSave[$slug][] = [
                    'category_slug' => $slug,
                    'rank' => $i + 1,
                    'source' => mb_substr((string) $p['source'], 0, 16),
                    'title' => mb_substr((string) $p['title'], 0, 512),
                    'price' => (int) $p['price'],
                    'shop_name' => $p['shop_name'] !== null ? mb_substr((string) $p['shop_name'], 0, 255) : null,
                    'product_url' => (string) $p['product_url'],
                    'image_url' => $p['image_url'],
                    'fetched_at' => $computedAt,   // stats の computed_at と同時刻
                    'created_at' => $computedAt,
                    'updated_at' => $computedAt,
                ];
            }

            // ドライラン表示: 保存予定件数＋先頭3件のタイトル・価格。
            $this->line(sprintf('    商品カード: 保存予定%d件（top_products=%d）', count($selected), $topProducts));
            foreach (array_slice($selected, 0, 3) as $i => $p) {
                $this->line(sprintf('      %d. [%s] %s ¥%s', $i + 1, $p['source'], mb_strimwidth((string) $p['title'], 0, 44, '…'), number_format((int) $p['price'])));
            }
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

        // 商品カードはカテゴリ単位で入れ替え（該当 category_slug を全削除 → rank 1..N を挿入）。
        $productRowCount = 0;
        foreach ($productsToSave as $slug => $rows) {
            DB::transaction(function () use ($slug, $rows) {
                DB::table('parts_category_products')->where('category_slug', $slug)->delete();
                if ($rows !== []) {
                    DB::table('parts_category_products')->insert($rows);
                }
            });
            $productRowCount += count($rows);
        }
        $this->info('保存しました: '.$productRowCount.'件を parts_category_products へ（'.count($productsToSave).'カテゴリ）。');

        return self::SUCCESS;
    }

    /**
     * 楽天の画像URLの ?_ex=WxH サイズ指定のみ 300x300 に書き換える（ホスト/パスは組み立てない）。
     * URLが空なら null（除外はしない・画像なしとして保存）。
     */
    private function normalizeImageUrl(string $mall, string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if ($mall === 'rakuten') {
            $url = preg_replace('/([?&]_ex=)\d+x\d+/', '${1}300x300', $url) ?? $url;
        }

        return $url;
    }

    /**
     * 商品カードの上位を選ぶ。
     *  (1) stats と同じ除外条件（価格>0・重複除去）は収集時に適用済み。
     *  (2) Q1〜Q3内を候補にする（極端な付属品/業販セットを弾く）。
     *  (3) 候補が limit に満たないときのみ全体へ広げて補充。
     *  (4) API順（関連度順）を維持（価格ソートしない）。
     *  (5) 同一ショップ最大3件。 (6) 楽天/Yahoo を交互に採用。
     *
     * @param  array<int, array<string, mixed>>  $products  重複除去済み・API関連度順
     * @return array<int, array<string, mixed>>
     */
    private function selectTopProducts(array $products, int $q1, int $q3, int $limit): array
    {
        $inRange = array_values(array_filter($products, fn ($p) => $p['price'] >= $q1 && $p['price'] <= $q3));

        $shopCount = []; // shop_name => 採用数（cap 3）
        $taken = [];     // _key => true
        $selected = [];
        $lastSource = null;

        // フェーズ1: Q1〜Q3内。フェーズ2: 不足なら全体で補充。制約（順序・shop cap・交互）は継続。
        foreach ([$inRange, $products] as $pool) {
            $this->pickAlternating($pool, $limit, $shopCount, $taken, $selected, $lastSource);
            if (count($selected) >= $limit) {
                break;
            }
        }

        return $selected;
    }

    /**
     * 与えられた候補列から、順序維持・1ショップ最大3件・楽天/Yahoo交互で $limit まで採る。
     * $shopCount/$taken/$selected/$lastSource は呼び出し間で引き継ぐ（フェーズ2の補充用）。
     *
     * @param  array<int, array<string, mixed>>  $pool
     * @param  array<string, int>  $shopCount
     * @param  array<string, bool>  $taken
     * @param  array<int, array<string, mixed>>  $selected
     */
    private function pickAlternating(array $pool, int $limit, array &$shopCount, array &$taken, array &$selected, ?string &$lastSource): void
    {
        while (count($selected) < $limit) {
            $pick = null;      // lastSource と異なる source の先頭候補（交互優先）
            $fallback = null;  // source を問わない先頭候補
            foreach ($pool as $p) {
                if (isset($taken[$p['_key']])) {
                    continue;
                }
                $shop = (string) ($p['shop_name'] ?? '');
                if ($shop !== '' && ($shopCount[$shop] ?? 0) >= 3) {
                    continue; // 1ショップ最大3件
                }
                if ($fallback === null) {
                    $fallback = $p;
                }
                if ($lastSource === null || $p['source'] !== $lastSource) {
                    $pick = $p;
                    break;
                }
            }
            $chosen = $pick ?? $fallback;
            if ($chosen === null) {
                return; // これ以上採れない
            }
            $taken[$chosen['_key']] = true;
            $shop = (string) ($chosen['shop_name'] ?? '');
            if ($shop !== '') {
                $shopCount[$shop] = ($shopCount[$shop] ?? 0) + 1;
            }
            $selected[] = $chosen;
            $lastSource = (string) $chosen['source'];
        }
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
