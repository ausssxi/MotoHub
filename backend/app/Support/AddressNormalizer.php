<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 住所文字列の正規化（ジオコーディング精度向上・重複判定の一致率向上のため）。
 *
 * スクレイパー（保存時）と GeocodeRentalGarages（重複座標の同一敷地判定）の双方が
 * 同一ロジックを使えるよう、static メソッドとして App\Support に置く。
 *
 * ※ 末尾の位置説明除去は必ず末尾アンカー($)で判定し、住所途中の地名（「西の京」「南北町」等）は壊さない。
 * ※ 実測（GSI AddressSearch）で「大字」「字」は残す/除去で解決結果が変わらなかったため、除去しない。
 */
final class AddressNormalizer
{
    public static function normalize(string $address): string
    {
        $s = self::normalizeWhitespace($address);

        // 1) ハイフン類を半角ハイフン(U+002D)に統一。番地区切りが機種依存記号だと解決精度が落ちるため。
        //    －(U+FF0D 全角ハイフンマイナス・住所頻出) ｰ(U+FF70) ー(U+30FC) −(U+2212 MINUS) ﹣(U+FE63 SMALL)
        //    U+2010〜U+2015 全て: ‐(U+2010) ‑(U+2011) ‒(U+2012 FIGURE DASH) –(U+2013 EN) —(U+2014 EM) ―(U+2015 HORIZONTAL BAR)
        //    ­(U+00AD SOFT HYPHEN)
        $s = str_replace([
            "\u{FF0D}", "\u{FF70}", "\u{30FC}", "\u{2212}", "\u{FE63}",
            "\u{2010}", "\u{2011}", "\u{2012}", "\u{2013}", "\u{2014}", "\u{2015}",
            "\u{00AD}",
        ], '-', $s);

        // 2) 全角数字→半角数字。
        $s = mb_convert_kana($s, 'n');

        // 3) 末尾の補足語「付近」「周辺」「地内」「内」を除去（地内 を 内 より前に置き2文字まとめて消す）。
        $s = preg_replace('/(付近|周辺|地内|内)$/u', '', $s) ?? $s;

        // 4) 末尾の「の＋方角(＋側/方向)」を除去。例「…4013-1の南東」「…の東側」。複合方角を単一方角より先に。
        $s = preg_replace('/の(北東|北西|南東|南西|東|西|南|北)(側|方向)?$/u', '', $s) ?? $s;

        // 5) 末尾の「の＋位置語」を除去。例「…の一部」「…の対面」「…の向かい側」「…の隣」。
        //    長い語を先に並べ、$ アンカーと併せ取りこぼしを防ぐ。
        $s = preg_replace('/の(一部|対面|向かい側|向かい|向い|隣|裏|正面|角)$/u', '', $s) ?? $s;

        // 6) 末尾が「の」1文字だけで終わる場合を除去（「…1051-1の」のように接尾語が欠けた表記）。
        $s = preg_replace('/の$/u', '', $s) ?? $s;

        // 7) 漢数字の丁目を算用数字へ（一〜二十まで）。例「植田西二丁目」→「植田西2丁目」。
        $s = preg_replace_callback('/([一二三四五六七八九十]+)丁目/u', static function (array $m): string {
            return self::kanjiNumToInt($m[1]).'丁目';
        }, $s) ?? $s;

        // 8) 「N丁目M番K号」「N丁目M番地」「N丁目M-K」「N丁目M」→「N-M-K」形式へ統一。
        $s = preg_replace('/(\d+)丁目(\d+)番(\d+)号?/u', '$1-$2-$3', $s) ?? $s;
        $s = preg_replace('/(\d+)丁目(\d+)番地?/u', '$1-$2', $s) ?? $s;
        $s = preg_replace('/(\d+)丁目(\d+)-(\d+)/u', '$1-$2-$3', $s) ?? $s;
        $s = preg_replace('/(\d+)丁目(\d+)/u', '$1-$2', $s) ?? $s;
        $s = preg_replace('/(\d+)丁目/u', '$1', $s) ?? $s;

        return trim($s);
    }

    /**
     * 全角空白→半角、改行・連続空白→単一空白、trim。
     */
    private static function normalizeWhitespace(string $s): string
    {
        $s = str_replace("\u{3000}", ' ', $s);
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;

        return trim($s);
    }

    /**
     * 漢数字（一〜二十程度）を整数に変換。丁目変換の補助。
     */
    private static function kanjiNumToInt(string $k): int
    {
        $digits = ['一' => 1, '二' => 2, '三' => 3, '四' => 4, '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9];

        if ($k === '十') {
            return 10;
        }
        if (isset($digits[$k])) {
            return $digits[$k];
        }
        // 「十M」「N十」「N十M」（十一〜二十一程度）に対応。
        if (str_contains($k, '十')) {
            [$tens, $ones] = array_pad(explode('十', $k), 2, '');
            $t = $tens === '' ? 1 : ($digits[$tens] ?? 0);
            $o = $ones === '' ? 0 : ($digits[$ones] ?? 0);

            return $t * 10 + $o;
        }

        return $digits[$k] ?? 0;
    }
}
