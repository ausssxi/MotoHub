<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Blade;

/**
 * 記事本文HTMLへの広告差し込み。段落の途中には割り込ませず、中盤の h2 の直前にだけ入れる。
 * 見出し（h2）が MID_MIN_HEADINGS 未満の記事には差し込まない（薄い記事に広告を足さない）。
 * 無効時・スロット未設定時は何もしない（HTMLをそのまま返す）。
 */
final class AdPlacement
{
    /** 中盤枠を出すのに必要な最小 h2 数。 */
    private const MID_MIN_HEADINGS = 3;

    /**
     * 中盤の h2 の直前に <x-ads.unit> を差し込む。条件を満たさなければ $html をそのまま返す。
     */
    public static function injectMidUnit(string $html, string $slot): string
    {
        if (! config('adsense.enabled')) {
            return $html;
        }
        if ((string) config('adsense.slots.'.$slot, '') === '') {
            return $html;
        }

        // h2 の位置（バイトオフセット）を集める。3つ未満なら中盤枠を出さない。
        if (preg_match_all('/<h2\b/i', $html, $m, PREG_OFFSET_CAPTURE) < self::MID_MIN_HEADINGS) {
            return $html;
        }

        $offsets = array_map(static fn (array $x): int => (int) $x[1], $m[0]);

        // 中盤の h2（先頭は避ける＝記事冒頭に入れない）。3つなら2番目、4つなら3番目…。
        $pos = $offsets[intdiv(count($offsets), 2)];

        $ad = (string) Blade::render('<x-ads.unit :slot="$slot" />', ['slot' => $slot]);

        return substr($html, 0, $pos).$ad.substr($html, $pos);
    }
}
