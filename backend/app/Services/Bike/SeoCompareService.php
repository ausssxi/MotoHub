<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Http\Resources\Bike\ListingResource;
use App\Models\BikeModel;
use App\Models\Listing;
use App\Models\SeoCompare;
use Illuminate\Support\Facades\Cache;

/**
 * 車種比較ページ用のロジッククラス
 */
final class SeoCompareService
{
    /**
     * 両車種の在庫KPIを集計（キャッシュ付き）
     */
    public function computeCompareKpi(BikeModel $m1, BikeModel $m2): array
    {
        // v2: AVG → 中央値/パーセンタイル＋excludeBulkSold＋外れ値除外に刷新（旧v1キャッシュを無効化）
        $cacheKey = 'compare_kpi_v2_' . $m1->id . '_' . $m2->id;

        return Cache::remember($cacheKey, 3600, function () use ($m1, $m2) {
            return [
                'model1' => $this->buildModelKpi($m1),
                'model2' => $this->buildModelKpi($m2),
            ];
        });
    }

    /**
     * 比較ページ用に、当該車種の販売中車両を安い順で取得（bike_card用の配列化済み）。
     * marketStats は読み込まないため bargain_info は常に null（お得バッジはbladeで抑止）。
     * KPIと同じくキャッシュに同梱（車種単位で1時間）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function getInventoryCards(BikeModel $model, int $limit = 4): array
    {
        $cacheKey = 'compare_inventory_v1_' . $model->id . '_' . $limit;

        return Cache::remember($cacheKey, 3600, function () use ($model, $limit) {
            $listings = Listing::query()
                ->where('bike_model_id', $model->id)
                ->active()
                ->excludeBulkSold()
                ->where('total_price', '>', 0)
                ->with([
                    'bikeModel.manufacturer',
                    'bikeModel.categoryData',
                    'shop',
                    'site',
                    'tags:id,name',
                ])
                ->orderBy('total_price', 'asc')
                ->limit($limit)
                ->get();

            return ListingResource::collection($listings)->resolve();
        });
    }

    /**
     * スペック/価格データから定型FAQを機械的に生成（3〜5問）。
     * データが揃わない質問は出さない（薄い・誤情報を避ける）。
     * 返り値は表示用とFAQPage JSON-LDで同一に使う。
     *
     * @param  array<string, mixed>  $kpi  computeCompareKpi() の戻り値
     * @return array<int, array{q: string, a: string}>
     */
    public function buildFaq(BikeModel $m1, BikeModel $m2, array $kpi): array
    {
        $faq = [];

        // 1) どちらが安いか（中央値の差から）
        $med1 = $this->parseMan($kpi['model1']['median_price'] ?? null);
        $med2 = $this->parseMan($kpi['model2']['median_price'] ?? null);
        if ($med1 !== null && $med2 !== null) {
            if (abs($med1 - $med2) < 0.1) {
                $a = "{$m1->name}と{$m2->name}の中古相場（中央値）はどちらも約" . $this->fmtMan($med1) . '万円で、ほぼ同水準です。';
            } else {
                [$cheap, $cheapV, $exp, $expV] = $med1 < $med2 ? [$m1, $med1, $m2, $med2] : [$m2, $med2, $m1, $med1];
                $diff = round($expV - $cheapV, 1);
                $a = "中古相場（中央値）は{$cheap->name}が約" . $this->fmtMan($cheapV) . "万円、{$exp->name}が約" . $this->fmtMan($expV)
                    . "万円です。{$cheap->name}のほうが約" . $this->fmtMan($diff) . '万円安く購入できる傾向です。';
            }
            $faq[] = ['q' => "{$m1->name}と{$m2->name}はどちらが安いですか？", 'a' => $a];
        }

        // 2) 排気量・車検の違い（250cc境界で車検有無が分かれる）
        if ($m1->displacement && $m2->displacement) {
            $shaken1 = $m1->displacement <= 250 ? '不要' : '必要（2年ごと）';
            $shaken2 = $m2->displacement <= 250 ? '不要' : '必要（2年ごと）';
            $a = "{$m1->name}は{$m1->displacement}cc、{$m2->name}は{$m2->displacement}ccです。"
                . "車検は{$m1->name}が{$shaken1}、{$m2->name}が{$shaken2}です。";
            if (($m1->displacement <= 250) !== ($m2->displacement <= 250)) {
                $a .= '250ccを境に車検の有無が分かれるため、維持費に差が出ます。';
            }
            $faq[] = ['q' => "{$m1->name}と{$m2->name}の排気量・車検の違いは？", 'a' => $a];
        }

        // 3) 初心者向け（シート高・車両重量から機械的に）
        if ($m1->seat_height && $m2->seat_height && $m1->weight && $m2->weight
            && ($m1->seat_height !== $m2->seat_height || $m1->weight !== $m2->weight)) {
            $lowerSeat = $m1->seat_height < $m2->seat_height ? $m1 : ($m2->seat_height < $m1->seat_height ? $m2 : null);
            $lighter = $m1->weight < $m2->weight ? $m1 : ($m2->weight < $m1->weight ? $m2 : null);

            $a = "{$m1->name}はシート高{$m1->seat_height}mm・車両重量{$m1->weight}kg、"
                . "{$m2->name}はシート高{$m2->seat_height}mm・車両重量{$m2->weight}kgです。";
            if ($lowerSeat && $lighter && $lowerSeat->id === $lighter->id) {
                $a .= "シート高が低く車両重量も軽い{$lowerSeat->name}のほうが、足つき・取り回しの面では扱いやすい傾向です。";
            } else {
                $parts = [];
                if ($lowerSeat) {
                    $parts[] = "足つき重視なら{$lowerSeat->name}（シート高が低い）";
                }
                if ($lighter) {
                    $parts[] = "取り回し重視なら{$lighter->name}（車両重量が軽い）";
                }
                $a .= implode('、', $parts) . 'が扱いやすい傾向です。';
            }
            $a .= '（あくまでスペック上の目安です）';
            $faq[] = ['q' => "{$m1->name}と{$m2->name}、初心者にはどちらが向いていますか？", 'a' => $a];
        }

        // 4) エンジン種類の違い（両方あり、かつ異なる場合のみ）
        if ($m1->engine_type && $m2->engine_type && $m1->engine_type !== $m2->engine_type) {
            $faq[] = [
                'q' => "{$m1->name}と{$m2->name}のエンジンの違いは？",
                'a' => "{$m1->name}は{$m1->engine_type}、{$m2->name}は{$m2->engine_type}を搭載しています。",
            ];
        }

        return $faq;
    }

    /**
     * 「45.0」「1,234.5」形式の万円文字列を float に。null/'-' は null。
     */
    private function parseMan(?string $value): ?float
    {
        if ($value === null || $value === '-' || $value === '') {
            return null;
        }

        return (float) str_replace(',', '', $value);
    }

    private function fmtMan(float $value): string
    {
        return number_format($value, 1);
    }

    /**
     * 2車種の canonical 比較slug。小さい bike_model_id を必ず左（重複防止の正規順）。
     */
    public function canonicalSlugFor(BikeModel $a, BikeModel $b): string
    {
        [$m1, $m2] = $a->id <= $b->id ? [$a, $b] : [$b, $a];

        return $this->makeSlug($m1, $m2);
    }

    /**
     * リクエストslugから active な SeoCompare を解決（並び順非依存）。
     * 生成側は canonical slug を保存するので、完全一致＝canonical、逆順一致＝301対象。
     * 見つからなければ null（=404）。
     */
    public function findActiveBySlugAnyOrder(string $slug): ?SeoCompare
    {
        $eager = [
            'model1.manufacturer', 'model1.categoryData', 'model1.representativeListing',
            'model2.manufacturer', 'model2.categoryData', 'model2.representativeListing',
        ];

        $seo = SeoCompare::active()->with($eager)->where('slug', $slug)->first();
        if ($seo) {
            return $seo;
        }

        // 逆順（B-vs-A）でアクセスされた場合は canonical 行を引いて 301 用に返す
        $parts = explode('-vs-', $slug);
        if (count($parts) !== 2) {
            return null;
        }
        $reversed = $parts[1] . '-vs-' . $parts[0];

        return SeoCompare::active()->with($eager)->where('slug', $reversed)->first();
    }

    /**
     * 比較ハブ（/bikes/compare）用に、active な比較ペア全件を
     * cc帯 × カテゴリでグルーピングして返す（1日キャッシュ）。
     *
     * @return array<int, array{cc_label: string, category: string, pairs: array<int, array{label: string, url: string}>}>
     */
    public function getHubGroups(): array
    {
        return Cache::remember('compare_hub_groups_v1', 86400, function () {
            $ccBands = config('comparison.cc_bands');

            $pairs = SeoCompare::active()
                ->ordered()
                ->with(['model1.manufacturer', 'model1.categoryData', 'model2.manufacturer'])
                ->get();

            $groups = [];
            foreach ($pairs as $pair) {
                $m1 = $pair->model1;
                $m2 = $pair->model2;
                if (! $m1 || ! $m2) {
                    continue;
                }

                // 同クラス生成（cc帯 AND category 一致）なので model1 の属性で代表させる
                $cc = (int) ($m1->displacement ?? 0);
                $band = null;
                foreach ($ccBands as $b) {
                    if ($cc >= $b['min'] && $cc <= $b['max']) {
                        $band = $b;
                        break;
                    }
                }
                $bandSlug = $band['slug'] ?? 'other';
                $bandMin = $band['min'] ?? 999999;
                $category = $m1->categoryData?->name ?? 'その他';
                $key = $bandSlug . '|' . $category;

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'cc_label' => $this->ccBandLabel($bandSlug),
                        'category' => $category,
                        'sort' => $bandMin,
                        'pairs' => [],
                    ];
                }
                $groups[$key]['pairs'][] = [
                    'label' => $m1->name . ' vs ' . $m2->name,
                    'url' => $pair->url,
                ];
            }

            // cc帯昇順 → カテゴリ名で安定ソート
            usort($groups, function ($a, $b) {
                return [$a['sort'], $a['category']] <=> [$b['sort'], $b['category']];
            });

            return array_map(function ($g) {
                unset($g['sort']);

                return $g;
            }, $groups);
        });
    }

    private function ccBandLabel(string $slug): string
    {
        return [
            '50' => '50cc以下',
            '125' => '51〜125cc',
            '250' => '126〜250cc',
            '400' => '251〜400cc',
            '750' => '401〜750cc',
            'over750' => '751cc以上',
        ][$slug] ?? 'その他';
    }

    /**
     * 同排気量帯・同カテゴリの他の比較ペアを取得
     */
    public function getRelatedComparisons(BikeModel $m1, BikeModel $m2): array
    {
        return SeoCompare::active()
            ->ordered()
            ->where('slug', '!=', $this->makeSlug($m1, $m2))
            ->where(function ($q) use ($m1, $m2) {
                $q->whereIn('model1_id', [$m1->id, $m2->id])
                  ->orWhereIn('model2_id', [$m1->id, $m2->id]);
            })
            ->with(['model1.manufacturer', 'model2.manufacturer'])
            ->limit(10)
            ->get()
            ->map(fn (SeoCompare $c) => [
                'slug' => $c->slug,
                'label' => $c->model1->name . ' vs ' . $c->model2->name,
                'url' => $c->url,
            ])
            ->toArray();
    }

    /**
     * 単一車種のKPIを集計
     */
    private function buildModelKpi(BikeModel $model): array
    {
        // active 在庫から bulk-sold を除外（信用に関わるので相場の母集団から外す）
        $query = Listing::query()
            ->where('bike_model_id', $model->id)
            ->active()
            ->excludeBulkSold()
            ->where('total_price', '>', 0);

        $totalCount = (clone $query)->count();

        // 価格は中央値/パーセンタイルで集計（AVGは外れ値に弱い）。
        // 異常に安い出品（部品取り・誤登録）を下限3万円で除外してから算出。
        $prices = (clone $query)
            ->where('total_price', '>=', 30000)
            ->orderBy('total_price')
            ->pluck('total_price')
            ->map(fn ($p) => (int) $p)
            ->values();

        $man = fn (?int $yen) => $yen !== null ? number_format($yen / 10000, 1) : null;

        return [
            'total_count' => $totalCount,
            // P50（中央値）＝相場の代表値。P5/P95 で外れ値に頑健なレンジを表示。
            'median_price' => $man($this->percentile($prices, 0.50)),
            'min_price' => $man($this->percentile($prices, 0.05)),
            'max_price' => $man($this->percentile($prices, 0.95)),
            'price_distribution' => $this->buildPriceDistribution($query, $totalCount),
        ];
    }

    /**
     * 昇順済みの価格コレクションから線形補間なしのパーセンタイル値を返す（空なら null）。
     */
    private function percentile(\Illuminate\Support\Collection $sorted, float $q): ?int
    {
        $n = $sorted->count();
        if ($n === 0) {
            return null;
        }
        $idx = (int) min($n - 1, max(0, (int) floor($q * ($n - 1))));

        return (int) $sorted[$idx];
    }

    /**
     * 価格帯分布を集計
     */
    private function buildPriceDistribution(mixed $query, int $totalCount): array
    {
        if ($totalCount === 0) {
            return [];
        }

        $bands = [
            ['label' => '〜10万円',   'min' => 0,       'max' => 100000],
            ['label' => '10〜20万円',  'min' => 100000,  'max' => 200000],
            ['label' => '20〜30万円',  'min' => 200000,  'max' => 300000],
            ['label' => '30〜50万円',  'min' => 300000,  'max' => 500000],
            ['label' => '50〜80万円',  'min' => 500000,  'max' => 800000],
            ['label' => '80〜100万円', 'min' => 800000,  'max' => 1000000],
            ['label' => '100万円〜',   'min' => 1000000, 'max' => null],
        ];

        $caseWhen = 'CASE';
        foreach ($bands as $i => $band) {
            if ($band['max'] === null) {
                $caseWhen .= " WHEN total_price >= {$band['min']} THEN {$i}";
            } else {
                $caseWhen .= " WHEN total_price >= {$band['min']} AND total_price < {$band['max']} THEN {$i}";
            }
        }
        $caseWhen .= ' END';

        $rows = (clone $query)
            ->selectRaw("{$caseWhen} as band_idx, COUNT(*) as cnt")
            ->groupByRaw($caseWhen)
            ->get()
            ->keyBy('band_idx');

        $maxCount = $rows->max('cnt') ?: 1;

        $distribution = [];
        foreach ($bands as $i => $band) {
            $count = (int) ($rows->get($i)?->cnt ?? 0);
            if ($count === 0) {
                continue;
            }
            $distribution[] = [
                'label' => $band['label'],
                'count' => $count,
                'percent' => round($count / $totalCount * 100, 1),
                'bar_width' => round($count / $maxCount * 100),
                'is_max' => $count === $maxCount,
            ];
        }

        return $distribution;
    }

    private function makeSlug(BikeModel $m1, BikeModel $m2): string
    {
        return ($m1->slug ?? (string) $m1->id) . '-vs-' . ($m2->slug ?? (string) $m2->id);
    }
}
