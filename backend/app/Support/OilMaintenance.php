<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BikeModel;
use App\Models\ModelFitment;

/**
 * 車種ページ「オイル交換の目安」ブロックのデータ解決（表示時計算・キャッシュbump不要）。
 *
 * ・verified な oil 適合行があれば車種別リッチ（実データ・偽データを出さない verified_at ゲート）。
 * ・無ければ排気量帯の一般目安（config/fitments.oil_general・幅表現＋注記＋出典）。
 * ・用品アフィリ用の keyword（粘度 or 排気量帯＝数種に収束しキャッシュが効く）とフィルタ検索リンクも返す。
 * ・数字は創作しない：rich は DB 値、general は config の一般レンジ。
 */
final class OilMaintenance
{
    /**
     * @return array{mode:string, rich:?array, general:?array, oil_keyword:string, fitment_url:?string, filter_search_url:string, article_url:string, checked_at:string}
     */
    public static function forModel(BikeModel $model): array
    {
        $rich = self::richForModel($model);
        $general = $rich ? null : self::generalForDisplacement((int) ($model->displacement ?? 0));

        $viscosity = $rich['viscosity'] ?? null;
        $oilKeyword = $viscosity
            ? 'バイク エンジンオイル '.$viscosity
            : (string) ($general['keyword'] ?? 'バイク エンジンオイル');

        return [
            'mode' => $rich ? 'rich' : 'general',
            'rich' => $rich,
            'general' => $general,
            'oil_keyword' => $oilKeyword,
            // 適合表ページ /maintenance/{slug}/oil への導線（バッテリー/プラグと同じ route）。
            // verified な oil 適合がある車種（rich）だけ URL を持たせ、無い車種ではリンクを出さない。
            'fitment_url' => $rich ? route('fitments.show', ['bikeModel' => $model->slug, 'task' => 'oil']) : null,
            // parts.search は JSON API（生JSONが表示される）。人間が見る HTML の価格比較ページ parts.compare へ送る。
            'filter_search_url' => route('parts.compare', ['keyword' => $model->name.' オイルフィルター']),
            'article_url' => (string) config('fitments.tasks.oil.article_url', ''),
            'checked_at' => (string) config('fitments.oil_general.checked_at', ''),
        ];
    }

    /**
     * verified な oil 適合行から車種別データ。無ければ null。
     *
     * @return array{viscosity:?string, capacity_change:?string, capacity_filter:?string, jaso:?string, interval:?string, verified_at:mixed, sources:array<int,array{name:string,url:?string}>}|null
     */
    public static function richForModel(BikeModel $model): ?array
    {
        $row = ModelFitment::where('bike_model_id', $model->id)
            ->where('task', 'oil')
            ->whereNotNull('verified_at')
            ->orderByDesc('verified_at')
            ->first();

        if (! $row) {
            return null;
        }

        $spec = is_array($row->spec) ? $row->spec : [];

        return [
            'viscosity' => $row->recommended_part_no ?: null,
            'capacity_change' => $spec['capacity_change'] ?? null,
            'capacity_filter' => $spec['capacity_filter'] ?? null,
            'jaso' => $spec['jaso'] ?? null,
            'interval' => $spec['interval'] ?? null,
            'verified_at' => $row->verified_at,
            // http(s) で始まらない出典URL（打ち間違い等）は SourceUrl で null にし、ビュー側で
            // リンクにせず出典名テキストのみ表示させる。判定は SourceUrl に集約。
            'sources' => array_values(array_filter([
                $row->source_1_name ? ['name' => (string) $row->source_1_name, 'url' => SourceUrl::externalHref($row->source_1_url)] : null,
                $row->source_2_name ? ['name' => (string) $row->source_2_name, 'url' => SourceUrl::externalHref($row->source_2_url)] : null,
            ])),
        ];
    }

    /**
     * 排気量帯の一般目安（config/fitments.oil_general.bands）。
     *
     * @return array{max:?int, label:string, capacity:string, interval:string, keyword:string}
     */
    public static function generalForDisplacement(int $cc): array
    {
        $bands = (array) config('fitments.oil_general.bands', []);
        foreach ($bands as $band) {
            if (($band['max'] ?? null) === null || $cc <= (int) $band['max']) {
                return $band;
            }
        }

        return end($bands) ?: [];
    }
}
