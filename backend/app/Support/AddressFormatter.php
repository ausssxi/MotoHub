<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 住所整形の単一実装。POI/道の駅/レンタルガレージなど複数箇所で使うため、ロジックはここ1か所に置く。
 *
 * ★元は PoiAreaController::townPart() の private。表示名フォールバック（Poi::getDisplayNameAttribute）と
 *   周辺施設ラベル（PoiAreaController::facilityLabel）の両方で使うため共有化した。コピーして分裂させないこと
 *   （郡部・政令市の4パターンを実データ108件から作り込んでおり、片方だけ直す事故の被害が大きい）。
 */
final class AddressFormatter
{
    /**
     * 住所から町名（丁目・番地の手前の地名）を取り出す。「山武郡横芝光町横芝光町横芝」のような二重表記を防ぐ。
     *
     * pois.city は municipalities.full_name（郡付き。例「山武郡横芝光町」）だが、address 側は表記がずれる。
     * 本番246件の実データでは、単純な str_replace([$prefecture,$city]) で108件が市区町村名を残していた。傾向:
     *   - 郡部（…郡○○町/村）: address は郡を含まない → 郡以降（○○町/村）を候補に足す
     *   - 政令市（○○市△△区）: address は区が抜けて市が残る（例 city「横浜市鶴見区」/ addr「…横浜市駒岡」）
     *     → 「市まで」を候補に足すのが本命。念のため「区のみ」も足して安全側にする。
     * str_replace は配列順に処理するので、短い候補が長い候補の一部を先に削らないよう長い順に並べる。
     *
     * 町名が取り出せない（番地だけ・空）場合は空文字を返す（呼び出し側は「種別名だけ」等にフォールバックする）。
     */
    public static function townPart(?string $prefecture, ?string $city, ?string $address): string
    {
        $prefecture = (string) $prefecture;
        $city = (string) $city;

        $town = trim((string) $address);
        if ($town === '') {
            return '';
        }

        // (1) 先頭の行政区分（都道府県・市区町村）を除く。ずれ吸収のため候補を増やして長い順に置換。
        $strip = array_filter([$prefecture, $city], static fn (string $s): bool => $s !== '');
        if (preg_match('/郡(.+)$/u', $city, $m)) {
            $strip[] = $m[1]; // 郡以降（例: 山武郡横芝光町 → 横芝光町）
        }
        if (preg_match('/^(.+?市)/u', $city, $m)) {
            $strip[] = $m[1]; // 市まで（例: 横浜市鶴見区 → 横浜市）
        }
        if (preg_match('/市(.+区)$/u', $city, $m)) {
            $strip[] = $m[1]; // 区のみ（例: 横浜市港北区 → 港北区。address が区名始まりのケース用）
        }
        usort($strip, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        $town = trim(str_replace($strip, '', $town));

        // (2) 先頭の「大字」「字」を落とす（北海道・東北・沖縄に多い。例: 大字津久礼 → 津久礼 / 字森川町 → 森川町）。
        $town = (string) preg_replace('/^(?:大字|字)/u', '', $town);

        // (3) 末尾の番地（数字・ハイフン類・空白）を落とす。丁目名は漢数字なので残る（例: 金岡町6 → 金岡町）。
        //     長音記号「ー」は名前の一部なので除外し、- ‐ ‑ − －（U+2212/FF0D 等）と全角空白のみ対象にする。
        $town = (string) preg_replace('/[\s\x{3000}0-9０-９\-\x{2010}\x{2011}\x{2212}\x{FF0D}]+$/u', '', $town);

        // (4) 妥当性チェック。漢字・かな・カナが1文字も残らなければ地名として無効とみなし空を返す
        //     （括弧付きラベルを出さずラベルだけにする）。例: 刈羽村962-1 → 962-1 → 空 / 喬木村− → − → 空。
        if (! preg_match('/[\p{Han}\p{Hiragana}\p{Katakana}]/u', $town)) {
            return '';
        }

        return trim($town);
    }
}
