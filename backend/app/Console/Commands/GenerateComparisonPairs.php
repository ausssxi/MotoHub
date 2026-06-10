<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\SeoCompare;
use App\Services\Bike\SeoCompareService;
use Illuminate\Console\Command;

/**
 * プログラマティック車種比較ページ（SeoCompare）の生成対象ペアを品質ゲート付きで生成する。
 *
 * 全組合せ(NxN)は作らない。config/comparison.php の AND ゲート
 * （同クラス＝同cc-band&同category / 両車種 active在庫≥min / クリーンなスペック）を満たす
 * ペアだけを、各クラスの在庫上位同士の総当りから initial_batch_cap 件まで生成する。
 *
 * 安全設計（SeedRareDiscontinuedArticle 踏襲）:
 *  - 冪等: SeoCompare を (model1_id, model2_id) で updateOrCreate。再実行で重複作成しない。
 *  - canonical: 小さい bike_model_id を必ず model1（左）。逆順の旧行は is_active=false に倒す。
 *  - --dry-run で何が起きるか確認のみ。本番コンテナ内で実行する。
 *
 * 使い方（本番コンテナ内）:
 *   php artisan comparison:generate-pairs --dry-run
 *   php artisan comparison:generate-pairs
 *   php artisan comparison:generate-pairs --limit=100
 * 生成後: php artisan sitemap:generate（sitemap-compare.xml 収録 + IndexNow 差分送信）
 */
final class GenerateComparisonPairs extends Command
{
    protected $signature = 'comparison:generate-pairs
        {--dry-run : 変更を加えず、生成されるペア数とサンプルのみ表示}
        {--limit= : initial_batch_cap を上書きする生成上限}';

    protected $description = '同クラス・実在庫十分・クリーンスペックの車種ペアをSeoCompareへ生成（品質ゲート付き・冪等）';

    public function __construct(private readonly SeoCompareService $compareService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $cfg = config('comparison');
        $cap = $this->option('limit') !== null ? (int) $this->option('limit') : (int) $cfg['initial_batch_cap'];
        $minStock = (int) $cfg['min_stock_each'];
        $topN = (int) $cfg['top_models_per_class'];
        $requiredSpecs = (array) $cfg['required_specs'];
        $excluded = array_map('intval', (array) $cfg['excluded_model_ids']);
        $bands = (array) $cfg['cc_bands'];

        // 1. 候補モデル: displacement/category_id と required_specs が全て埋まっているもの
        $q = BikeModel::query()
            ->whereNotNull('displacement')
            ->whereNotNull('category_id');
        foreach ($requiredSpecs as $spec) {
            $q->whereNotNull($spec);
        }
        if ($excluded !== []) {
            $q->whereNotIn('id', $excluded);
        }
        $models = $q->get(array_values(array_unique(array_merge(
            ['id', 'slug', 'displacement', 'category_id'],
            $requiredSpecs
        ))));

        // 2. active 在庫（excludeBulkSold 後）を集計し、min_stock_each 未満を除外
        $stock = [];
        foreach ($models as $m) {
            $c = $this->stockCount((int) $m->id);
            if ($c >= $minStock) {
                $stock[$m->id] = $c;
            }
        }
        $models = $models->filter(fn ($m) => isset($stock[$m->id]))->values();

        // 3. (cc-band, category_id) でグルーピング → 各クラス在庫上位 topN で総当り
        $bandOf = function (int $disp) use ($bands): ?string {
            foreach ($bands as $b) {
                if ($disp >= $b['min'] && $disp <= $b['max']) {
                    return $b['slug'];
                }
            }
            return null;
        };

        $groups = [];
        foreach ($models as $m) {
            $band = $bandOf((int) $m->displacement);
            if ($band === null) {
                continue;
            }
            $groups[$band . '|' . $m->category_id][] = $m;
        }

        $pairs = [];
        foreach ($groups as $group) {
            usort($group, fn ($x, $y) => $stock[$y->id] <=> $stock[$x->id]);
            $group = array_slice($group, 0, $topN);
            $n = count($group);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $pairs[] = [
                        'a' => $group[$i],
                        'b' => $group[$j],
                        'sum' => $stock[$group[$i]->id] + $stock[$group[$j]->id],
                        'priority' => false,
                    ];
                }
            }
        }

        // 4. 手動許可ペア（クラス跨ぎの実ライバル）。同じ在庫/スペックゲートを適用し優先扱い
        foreach ((array) $cfg['manual_pairs'] as $mp) {
            if (! is_array($mp) || count($mp) !== 2) {
                continue;
            }
            $ma = $this->resolveModel($mp[0], $requiredSpecs, $excluded);
            $mb = $this->resolveModel($mp[1], $requiredSpecs, $excluded);
            if (! $ma || ! $mb || $ma->id === $mb->id) {
                $this->warn("manual_pair をスキップ: " . json_encode($mp) . "（解決不可 or スペック欠損）");
                continue;
            }
            $sa = $this->stockCount((int) $ma->id);
            $sb = $this->stockCount((int) $mb->id);
            if ($sa < $minStock || $sb < $minStock) {
                $this->warn("manual_pair をスキップ: " . json_encode($mp) . "（在庫不足 {$sa}/{$sb}）");
                continue;
            }
            $pairs[] = ['a' => $ma, 'b' => $mb, 'sum' => $sa + $sb, 'priority' => true];
        }

        // 5. 並べ替え（手動優先 → 在庫合計 desc）→ 無順序ペアで重複除去 → cap
        usort($pairs, fn ($x, $y) => [$y['priority'], $y['sum']] <=> [$x['priority'], $x['sum']]);

        $seen = [];
        $final = [];
        foreach ($pairs as $p) {
            [$lo, $hi] = $p['a']->id <= $p['b']->id ? [$p['a'], $p['b']] : [$p['b'], $p['a']];
            $key = $lo->id . '_' . $hi->id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $final[] = ['m1' => $lo, 'm2' => $hi];
            if (count($final) >= $cap) {
                break;
            }
        }

        $this->line('--- 生成サマリ ---');
        $this->line('候補モデル(ゲート通過): ' . $models->count());
        $this->line('クラス数             : ' . count($groups));
        $this->line('生成ペア             : ' . count($final) . ' / cap ' . $cap);
        foreach (array_slice($final, 0, 10) as $f) {
            $this->line('  ' . $this->compareService->canonicalSlugFor($f['m1'], $f['m2']));
        }

        if ($this->option('dry-run')) {
            $this->warn('[dry-run] 変更は行いませんでした。');
            return self::SUCCESS;
        }

        // 6. canonical で upsert（小さいid=model1）。逆順の旧行は無効化して重複ページを防ぐ
        $order = 0;
        foreach ($final as $f) {
            SeoCompare::where('model1_id', $f['m2']->id)
                ->where('model2_id', $f['m1']->id)
                ->update(['is_active' => false]);

            SeoCompare::updateOrCreate(
                ['model1_id' => $f['m1']->id, 'model2_id' => $f['m2']->id],
                [
                    'slug' => $this->compareService->canonicalSlugFor($f['m1'], $f['m2']),
                    'is_active' => true,
                    'sort_order' => $order++,
                ]
            );
        }

        $this->info('SeoCompare を ' . count($final) . ' 件 生成/更新しました。');
        $this->line('次: php artisan sitemap:generate（sitemap-compare.xml + IndexNow）');

        return self::SUCCESS;
    }

    private function stockCount(int $modelId): int
    {
        return Listing::query()
            ->where('bike_model_id', $modelId)
            ->active()
            ->excludeBulkSold()
            ->count();
    }

    /**
     * 手動ペアの参照（id or slug）をモデルへ解決し、除外/スペックゲートを適用。
     */
    private function resolveModel(int|string $ref, array $requiredSpecs, array $excluded): ?BikeModel
    {
        $model = is_numeric($ref)
            ? BikeModel::find((int) $ref)
            : BikeModel::where('slug', $ref)->orderBy('id')->first();

        if (! $model || in_array((int) $model->id, $excluded, true)) {
            return null;
        }
        if ($model->displacement === null || $model->category_id === null) {
            return null;
        }
        foreach ($requiredSpecs as $spec) {
            if ($model->{$spec} === null) {
                return null;
            }
        }

        return $model;
    }
}
