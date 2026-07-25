<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\BikeModel;

/**
 * 車種ページ「タイヤサイズの目安」ブロックのデータ解決（表示時計算・キャッシュbump不要）。
 *
 * ・データ源は bike_models.tire_size_front / tire_size_rear（スクレイパーが元サイトの表示を無正規化で格納）。
 *   追加クエリは無し（$model の既存カラム）。fitments は使わない＝タイヤは適合表ページも無くカニバリ制約なし。
 * ・表記は多様（M/C の有無・LI/速度記号・全角/半角カッコ・文字化け"?"・旧インチ 3.00-18・B構造・空白区切り）。
 *   extractSize() で先頭のコアサイズだけを安全に取り出し、正規化して「表示」と「商品keyword」の両方に使う。
 *   ・ラジアル/B/R は楽天が密着形（120/60ZR17）だと 0 件になるため、構造記号の前にスペースを入れる（実API検証済）。
 *   ・壊れデータ("?" だけ等)・抽出不能は null → ブロック非表示（偽サイズや文字化けを出さない）。
 * ・排気量からサイズは推定できないため一般フォールバックはしない（データ有りの車種のみ表示）。
 */
final class TireMaintenance
{
    /**
     * @return array{
     *   mode:string,
     *   front:?string, rear:?string, same:bool,
     *   front_keyword:?string, rear_keyword:?string,
     *   search_url:string
     * }
     */
    public static function forModel(BikeModel $model): array
    {
        $front = self::extractSize($model->tire_size_front);
        $rear = self::extractSize($model->tire_size_rear);

        if ($front === null && $rear === null) {
            return ['mode' => 'none'];
        }

        return [
            'mode' => 'rich',
            'front' => $front,
            'rear' => $rear,
            'same' => $front !== null && $front === $rear,
            'front_keyword' => $front !== null ? 'バイク タイヤ '.$front : null,
            'rear_keyword' => $rear !== null ? 'バイク タイヤ '.$rear : null,
            'search_url' => route('parts.compare', ['keyword' => $model->name.' タイヤ']),
        ];
    }

    /**
     * 生のタイヤ表記から先頭のコアサイズを抽出して正規化する。抽出不能・壊れデータは null。
     *
     * 例: "120/70ZR17M/C?58W?" → "120/70 ZR17"、"110/80-14M/C 53P" → "110/80-14"、
     *     "3.00-18" → "3.00-18"、"130/80 B17 M/C 65H" → "130/80 B17"、"100/90 19" → "100/90-19"、"?" → null
     */
    public static function extractSize(?string $raw): ?string
    {
        $s = trim((string) $raw);
        if ($s === '' || $s === '?') {
            return null;
        }

        // メトリック: 幅/扁平 [ZR|R|B|-|空白] リム径。M/C・LI・カッコ・"?"化け・TL は後続で無視される。
        if (preg_match('~^(\d{2,3})/(\d{1,3})\s*(ZR|R|B|-)?\s*(\d{2})~u', $s, $m)) {
            $c = $m[3] ?? '';

            // バイアス（- / 区切り無し）はハイフン、ラジアル系（ZR/R/B）は楽天対策でスペースを入れる。
            return ($c === '' || $c === '-')
                ? "{$m[1]}/{$m[2]}-{$m[4]}"
                : "{$m[1]}/{$m[2]} {$c}{$m[4]}";
        }

        // 旧インチ: 3.00-18 / 2.75-21 等。
        if (preg_match('~^(\d\.\d{2})\s*-\s*(\d{2})~u', $s, $m)) {
            return "{$m[1]}-{$m[2]}";
        }

        return null;
    }
}
