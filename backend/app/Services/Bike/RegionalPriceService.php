<?php

declare(strict_types=1);

namespace App\Services\Bike;

use App\Models\BikeModel;
use App\Models\ModelRegionPriceStat;

/**
 * モデルページの「地域別中古相場（中央値）」表示用データを組み立てる。
 * 事前計算テーブル(model_region_price_stats)を読むだけ（集計は stats:regional-prices）。
 */
final class RegionalPriceService
{
    /** 地域比較が成立する最低ブロック数。これ未満はセクションごと非表示。 */
    private const MIN_BLOCKS = 2;

    /**
     * 見出しの「高め/割安」断定に使える最低台数。これ未満のブロックは中央値が振れやすく、
     * 偶然の高/安を"事実"として掲げると94%バッジと同じ失敗になるため判定母数から除外。
     * 表自体の表示ゲート(MIN_LISTINGS=10, コマンド側)とは別の、より厳しい頑健閾値。
     */
    private const ROBUST_N = 20;

    /**
     * @return array{regions: array<int, array{block: string, median: int, median_man: string, count: int}>,
     *               national: array{median: int, median_man: string, count: int}|null,
     *               headline: string|null}
     */
    public function getForModel(BikeModel $model): array
    {
        $empty = ['regions' => [], 'national' => null, 'headline' => null, 'spread' => null, 'spread_narrative' => null];

        $rows = ModelRegionPriceStat::where('bike_model_id', $model->id)
            ->get()
            ->keyBy('region_block');

        if ($rows->isEmpty()) {
            return $empty;
        }

        // heterogeneity guard: 混在バケット（p90/p10 過大）は地域価格を一切主張しない
        $nat = $rows->get((string) config('regions.national_label', '全国'));
        if ($nat !== null && $this->isHeterogeneous($nat)) {
            return $empty;
        }

        $regions = $this->regionsFromRows($rows);

        // 地域比較が成立しない（ブロック1個以下）なら出さない
        if (count($regions) < self::MIN_BLOCKS) {
            return $empty;
        }

        $national = $this->nationalFromRows($rows);

        $spread = $this->buildSpread($regions, $national);

        return [
            'regions' => $regions,
            'national' => $national,
            'headline' => $this->buildHeadline($model, $regions, $national),
            'spread' => $spread,
            'spread_narrative' => $this->buildSpreadNarrative($model->name, $spread, $national),
        ];
    }

    /**
     * stat行（block→row）から表示順の地域ブロック配列を組む。
     *
     * @param  \Illuminate\Support\Collection<string, ModelRegionPriceStat>  $rowsByBlock
     * @return array<int, array{block: string, median: int, median_man: string, count: int, robust: bool}>
     */
    private function regionsFromRows($rowsByBlock): array
    {
        $regions = [];
        foreach ((array) config('regions.block_order', []) as $block) {
            if (isset($rowsByBlock[$block])) {
                $regions[] = $this->row($block, $rowsByBlock[$block]);
            }
        }

        return $regions;
    }

    /**
     * stat行から全国行を取り出す（無ければ null）。
     *
     * @param  \Illuminate\Support\Collection<string, ModelRegionPriceStat>  $rowsByBlock
     * @return array{median: int, median_man: string, count: int}|null
     */
    private function nationalFromRows($rowsByBlock): ?array
    {
        $label = (string) config('regions.national_label', '全国');

        return isset($rowsByBlock[$label]) ? $this->row($label, $rowsByBlock[$label]) : null;
    }

    /**
     * 地域差ページのゲート該当モデル（spread.robust_block_count≥minRobust かつ pct≥minPct）を
     * spread%降順で返す。全stat行を1クエリで取得しPHPで判定（モデル毎クエリなし＝N+1なし）。1日キャッシュ。
     *
     * @return array<int, array{model: BikeModel, spread: array{pct: int, diff_man: string, robust_block_count: int, high: array{block: string, median_man: string}, low: array{block: string, median_man: string}}}>
     */
    public function gatedRegionPriceModels(int $minRobust = 3, int $minPct = 20): array
    {
        return \Illuminate\Support\Facades\Cache::remember(
            "region_price_gate_v1_{$minRobust}_{$minPct}",
            86400,
            function () use ($minRobust, $minPct) {
                $byModel = ModelRegionPriceStat::all()->groupBy('bike_model_id');

                $pass = [];
                $nationalLabel = (string) config('regions.national_label', '全国');
                foreach ($byModel as $modelId => $rows) {
                    $byBlock = $rows->keyBy('region_block');

                    // heterogeneity guard: 混在バケットは featured からも除外
                    $nat = $byBlock->get($nationalLabel);
                    if ($nat !== null && $this->isHeterogeneous($nat)) {
                        continue;
                    }

                    $regions = $this->regionsFromRows($byBlock);
                    if (count($regions) < self::MIN_BLOCKS) {
                        continue;
                    }
                    $spread = $this->buildSpread($regions, $this->nationalFromRows($byBlock));
                    if ($spread === null || $spread['robust_block_count'] < $minRobust || $spread['pct'] < $minPct) {
                        continue;
                    }
                    $pass[(int) $modelId] = $spread;
                }

                // slug を持つモデルのみ（URL化できないものは除外）
                $models = BikeModel::with('manufacturer')
                    ->whereIn('id', array_keys($pass))
                    ->whereNotNull('slug')
                    ->get()
                    ->keyBy('id');

                $out = [];
                foreach ($pass as $id => $spread) {
                    if (isset($models[$id])) {
                        $out[] = ['model' => $models[$id], 'spread' => $spread];
                    }
                }
                usort($out, fn ($a, $b) => $b['spread']['pct'] <=> $a['spread']['pct']);

                return $out;
            }
        );
    }

    /**
     * area×model LP 用の「地域相場の一文」＋region-priceリンク。
     * LP1枚につき getForModel 1呼び出し（N+1なし）。
     * text: 県が属するブロックが robust(count≥20) かつ全国行ありのときだけ生成（薄い断定回避）。
     * region_price_url: モデルが地域差ページのゲート(robust_block_count≥3 && pct≥20)該当時のみ。
     *
     * @return array{text: string|null, region_price_url: string|null}
     */
    public function landingNote(BikeModel $model, string $prefecture): array
    {
        $data = $this->getForModel($model);

        $text = null;
        $block = $this->blockForPrefecture($prefecture);
        if ($block !== null && $data['national'] !== null) {
            $row = collect($data['regions'])->firstWhere('block', $block);
            if ($row !== null && $row['robust']) {
                $natMedian = $data['national']['median'];
                $ratio = $natMedian > 0 ? ($row['median'] - $natMedian) / $natMedian : 0;
                $cmp = abs($ratio) <= 0.05 ? '同程度' : ($ratio > 0 ? '高め' : '安め');
                $text = "{$prefecture}が属する{$block}での{$model->name}の中古相場は約{$row['median_man']}万円"
                    ."（全国 約{$data['national']['median_man']}万円より{$cmp}）。";
            }
        }

        $url = null;
        $sp = $data['spread'];
        if ($sp !== null && $sp['robust_block_count'] >= 3 && $sp['pct'] >= 20 && $model->slug) {
            $url = route('bikes.region_price', $model->slug);
        }

        return ['text' => $text, 'region_price_url' => $url];
    }

    /**
     * 混在バケット判定（単一の抑制点）。全国行の p90/p10 が config の倍率を超えたら true。
     * 現行＋ヴィンテージ等が1モデル名に混ざり中央値が無意味な母集団を弾く。
     * p10/p90 が null/0 のモデルはガード無効＝false（後方互換）。
     */
    private function isHeterogeneous(ModelRegionPriceStat $national): bool
    {
        $p10 = (int) ($national->p10 ?? 0);
        $p90 = (int) ($national->p90 ?? 0);
        if ($p10 <= 0 || $p90 <= 0) {
            return false;
        }

        return ($p90 / $p10) > (float) config('region_price.heterogeneity_max_ratio', 3.0);
    }

    /**
     * 都道府県（フルネーム）→ 所属8地方ブロック。未該当は null。
     */
    private function blockForPrefecture(string $prefecture): ?string
    {
        foreach ((array) config('regions.blocks', []) as $block => $prefectures) {
            if (in_array($prefecture, (array) $prefectures, true)) {
                return $block;
            }
        }

        return null;
    }

    /**
     * 地域間の価格スプレッド（頑健ブロックのみ）。
     * pct = (最高robust中央値 − 最安robust中央値) / 全国中央値 * 100。
     * 「地域差が大きい車種」ページのゲート判定・表示に使う。robust2未満/全国行なしは null。
     *
     * @param  array<int, array{block: string, median: int, median_man: string, count: int, robust: bool}>  $regions
     * @param  array{median: int, median_man: string, count: int}|null  $national
     * @return array{pct: int, robust_block_count: int, high: array{block: string, median_man: string}, low: array{block: string, median_man: string}}|null
     */
    private function buildSpread(array $regions, ?array $national): ?array
    {
        if ($national === null || ($national['median'] ?? 0) <= 0) {
            return null;
        }

        $robust = array_values(array_filter($regions, fn ($r) => $r['robust']));
        if (count($robust) < self::MIN_BLOCKS) {
            return null;
        }

        usort($robust, fn ($a, $b) => $a['median'] <=> $b['median']);
        $low = $robust[0];
        $high = $robust[count($robust) - 1];

        // diff_man は「表示用に丸めた high/low median 同士の差」で算出する。
        // 生値の差を丸めると画面の引き算(56.5−33.9)と一致しないため（例 トリシティ 22.7→22.6 が正）。
        $highMan = round($high['median'] / 10000, 1);
        $lowMan = round($low['median'] / 10000, 1);

        return [
            'pct' => (int) round(($high['median'] - $low['median']) / $national['median'] * 100),
            'diff_man' => number_format($highMan - $lowMan, 1),
            'robust_block_count' => count($robust),
            'high' => ['block' => $high['block'], 'median_man' => $high['median_man']],
            'low' => ['block' => $low['block'], 'median_man' => $low['median_man']],
        ];
    }

    /**
     * 地域差を買い手目線で解釈する短い本文（spread配列からのテンプレ生成・AI/クエリなし）。
     * spread===null（robust<2）なら null＝本文を出さない（薄い断定を避ける／headlineと同じ頑健ゲート）。
     *
     * @param  array{pct: int, diff_man: string, high: array{block: string, median_man: string}, low: array{block: string, median_man: string}}|null  $spread
     * @param  array{median: int, median_man: string, count: int}|null  $national
     */
    public function buildSpreadNarrative(string $modelName, ?array $spread, ?array $national): ?string
    {
        if ($spread === null) {
            return null;
        }

        $pct = $spread['pct'];
        $hi = $spread['high'];
        $lo = $spread['low'];

        if ($pct >= 20) {
            return "{$modelName}の中古相場は地域差が大きい傾向です。最安は{$lo['block']}（約{$lo['median_man']}万円）、"
                ."最高は{$hi['block']}（約{$hi['median_man']}万円）で、約{$spread['diff_man']}万円（{$pct}%）の開きがあります。"
                ."{$lo['block']}周辺で探すと割安に見つかりやすいでしょう。";
        }

        if ($pct >= 10) {
            return "やや地域差があります。{$lo['block']}が安め（約{$lo['median_man']}万円）、"
                ."{$hi['block']}が高め（約{$hi['median_man']}万円）です。";
        }

        $natMan = $national['median_man'] ?? $hi['median_man'];

        return "全国でほぼ一様（中央値 約{$natMan}万円前後）。地域による価格差は小さめです。";
    }

    /**
     * @return array{block: string, median: int, median_man: string, count: int, robust: bool}
     */
    private function row(string $block, ModelRegionPriceStat $stat): array
    {
        return [
            'block' => $block,
            'median' => (int) $stat->median_price,
            'median_man' => number_format($stat->median_price / 10000, 1),
            'count' => (int) $stat->listing_count,
            // 頑健ブロック（n≥ROBUST_N）か。bladeの参考値マーク・見出し判定に使う。
            'robust' => (int) $stat->listing_count >= self::ROBUST_N,
        ];
    }

    /**
     * 実データ由来の独自文。
     * 「高め/割安」の断定は頑健ブロック（n≥ROBUST_N）からのみ選ぶ。薄いブロックの
     * 偶然の高/安を主張に格上げしない（94%バッジの二の舞回避）。
     * 頑健ブロックが2つ未満なら比較表現は出さず、全国中央値のみ述べる。
     *
     * @param  array<int, array{block: string, median: int, count: int}>  $regions
     * @param  array{median: int, median_man: string, count: int}|null  $national
     */
    private function buildHeadline(BikeModel $model, array $regions, ?array $national): ?string
    {
        $robust = array_values(array_filter($regions, fn ($r) => $r['count'] >= self::ROBUST_N));

        if (count($robust) >= self::MIN_BLOCKS) {
            usort($robust, fn ($a, $b) => $a['median'] <=> $b['median']);
            $cheapest = $robust[0];
            $priciest = $robust[count($robust) - 1];

            if ($cheapest['block'] !== $priciest['block']) {
                return "「{$model->name}」の地域別中古相場（中央値・支払総額）。"
                    ."{$priciest['block']}が高めの約{$this->man($priciest['median'])}万円、"
                    ."{$cheapest['block']}が割安傾向の約{$this->man($cheapest['median'])}万円です。"
                    .'購入時は近隣ブロックの相場と件数も確認しましょう。';
            }
        }

        // 頑健ブロックが足りない → 断定を避け、全国中央値のみ述べる
        if ($national !== null) {
            return "「{$model->name}」の地域別中古相場（中央値・支払総額）。"
                ."全国の中央値は約{$national['median_man']}万円です。"
                .'地域差は掲載台数が十分な地域から順次ご案内します。';
        }

        return null;
    }

    private function man(int $yen): string
    {
        return number_format($yen / 10000, 1);
    }
}
