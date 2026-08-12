<?php

declare(strict_types=1);

namespace App\Services\Parts;

/**
 * 商品説明文から「適合車種 / 適合型式 / 純正品番」を抽出する。
 *
 * パターンは 2026-08-12 に fitment:probe --dump で取得した実データから決めたもので、
 * 推測で書いた分岐は含めない。確認できた書式:
 *
 *   【適合車種】Vストローム250（V-Strom250）【適合型式】2BK-DS11A【適合年式】17年
 *   【適合車種】Vストローム250/ABS（V-Strom250） 【適合型式】DS11A/DS12E 【適合年式】2017〜2023年
 *
 * ■ 設計方針（誤ったデータを入れないことを最優先にする）
 *  - 型式は【適合型式】ラベルがある商品からしか取らない。年式・車台番号からの推測はしない。
 *    実測でラベルを持つ5件は5件とも正しい適合を書いており、逆にラベルを持たない商品は
 *    いずれも「多数の車種に使える汎用品」だった。つまりラベルの要求が、そのまま汎用品の
 *    除外フィルタとして働く。
 *  - 車種名は前方一致で通さない。「Vストローム250」と「Vストローム250SX」は別物で、
 *    実測で SX が混入していた。分割したうえでの完全一致だけを採用する。
 *  - 判断がつかないものは採用しない（呼び出し側で「判定不能」として計上する）。
 *
 * PartsCodeExtractor（JAN・メーカー品番）と役割を分ける。あちらは品番、こちらは適合。
 */
final class FitmentTextExtractor
{
    /**
     * ラベルを囲む括弧。全角【】・全角［］・半角[] の揺れを吸収する。
     * 値はラベルの終わりから「次のラベルの始まり」までとする。
     */
    private const LABEL_OPEN = '【\[［';

    private const LABEL_CLOSE = '】\]］';

    /**
     * 値の区切り。スラッシュ（全角/半角）・中黒・読点・カンマ（全角/半角）。
     * 「DS11A/DS12E」「AA07/AA08/AA09」「Vストローム250/ABS」などを分ける。
     */
    private const SEPARATORS = '/[\/／・、，,]+/u';

    /**
     * 車体型式（規制記号なし）。DS11A / JA42 / AA07 / RN52J など。
     * 英字2 + 数字2 + 英字0〜1。実データで確認できた形はすべてこれに収まる。
     */
    private const FRAME_CODE = '[A-Z]{2}\d{2}[A-Z]?';

    /**
     * 規制記号。2BK / 8BL / EBL / 2BL など（英数2〜3文字）。
     */
    private const REGULATION = '[0-9A-Z]{2,3}';

    /**
     * 【適合型式】ラベルが存在するか。
     * これが false の商品からは、型式も純正品番も採らない（汎用品の可能性が高いため）。
     */
    public static function hasFrameCodeLabel(string $text): bool
    {
        return self::labelledValue($text, '適合型式') !== null;
    }

    /**
     * 【適合型式】の値から車体型式を取り出す。
     * ラベルが無い、または1件も形が合わなければ空配列。
     *
     * 「2BK-DS11A」は規制記号（2BK）と車体型式（DS11A）に分けて保持する。
     * 規制記号は年式・排ガス規制で変わるため、型式そのものとは別に持つ必要がある。
     *
     * @return array<int, array{raw: string, regulation: string|null, code: string}>
     */
    public static function frameCodes(string $text): array
    {
        $value = self::labelledValue($text, '適合型式');
        if ($value === null) {
            return [];
        }

        $out = [];
        foreach (self::splitValues($value) as $token) {
            // 「2BK-DS11A（17年〜）」のような補足は落としてから判定する
            $token = self::stripParentheses($token);
            $token = strtoupper(self::normalize($token));

            if (preg_match('/^('.self::REGULATION.')-('.self::FRAME_CODE.')$/u', $token, $m) === 1) {
                $out[] = ['raw' => $token, 'regulation' => $m[1], 'code' => $m[2]];

                continue;
            }

            if (preg_match('/^('.self::FRAME_CODE.')$/u', $token, $m) === 1) {
                $out[] = ['raw' => $token, 'regulation' => null, 'code' => $m[1]];
            }
            // どちらにも当てはまらない語は採らない（誤った型式を作らないため）
        }

        return self::uniqueByRaw($out);
    }

    /**
     * 【適合車種】の値を車種名の配列にして返す。ラベルが無ければ null（＝判定不能）。
     *
     * 「（V-Strom250）」のような英語表記の併記は落とす。
     * 「Vストローム250/ABS」はスラッシュで分割して両方を候補にする。
     *
     * @return array<int, string>|null
     */
    public static function fitmentModelNames(string $text): ?array
    {
        $value = self::labelledValue($text, '適合車種');
        if ($value === null) {
            return null;
        }

        $value = self::stripParentheses($value);

        $names = [];
        foreach (self::splitValues($value) as $token) {
            $token = trim($token);
            if ($token !== '') {
                $names[] = $token;
            }
        }

        return $names;
    }

    /**
     * 適合車種の候補群に、対象車種名と完全一致するものがあるか。
     *
     * 前方一致では判定しない。「Vストローム250SX」を「Vストローム250」として
     * 採用してしまうため（実測で混入を確認）。
     *
     * @param  array<int, string>  $names
     */
    public static function matchesModel(array $names, string $target): bool
    {
        $needle = strtoupper(self::normalize($target));
        if ($needle === '') {
            return false;
        }

        foreach ($names as $name) {
            if (strtoupper(self::normalize($name)) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * 「純正品番」「該当純正品番」ラベルの直後にある品番群。
     * 呼び出し側は、型式ラベルを持つ商品に限って使うこと（汎用品の品番を拾わないため）。
     *
     * 実データ: 「該当純正品番:16510-03G00、16510-06B00」
     *
     * @return array<int, string>
     */
    public static function oemPartNumbers(string $text): array
    {
        // ラベルは括弧つき（【純正品番】）でもコロン区切り（該当純正品番:）でも来る
        $value = self::labelledValue($text, '(?:該当)?純正品番');

        if ($value === null && preg_match('/(?:該当)?純正品番\s*[:：]\s*(.+)$/u', $text, $m) === 1) {
            $value = $m[1];
        }

        if ($value === null) {
            return [];
        }

        // 値の終わりが次のラベルで区切られているとは限らない（「【純正品番】A、B 品番 C」のように
        // 別の語が続くことがある）。そこで区切り文字と空白の両方で分割し、
        // 品番らしい形が続く間だけ採り、最初にそうでない語が出たら打ち切る。
        // こうしないと2つ目以降の品番を取りこぼす（「A、B」の B が「B 品番 C」として弾かれる）。
        $tokens = preg_split('/[\s\x{3000}\/／・、，,]+/u', $value) ?: [];

        $out = [];
        foreach ($tokens as $token) {
            $token = strtoupper(self::normalize(self::stripParentheses($token)));
            if ($token === '') {
                continue;
            }

            // 品番らしい形（英数字とハイフンで6文字以上）だけを採る
            if (preg_match('/^[0-9A-Z][0-9A-Z\-]{5,}$/u', $token) !== 1) {
                break; // 品番の並びが途切れた＝ここから先は別の記述
            }

            $out[] = $token;
        }

        return array_values(array_unique($out));
    }

    /**
     * ラベルの値を取り出す。ラベルが無ければ null。
     *
     * 値は「ラベルの閉じ括弧の直後」から「次のラベルの開き括弧の直前」まで。
     * 括弧なしでコロン区切りのラベルにも対応する（該当純正品番: など）。
     */
    private static function labelledValue(string $text, string $labelPattern): ?string
    {
        $open = self::LABEL_OPEN;
        $close = self::LABEL_CLOSE;

        // 【適合型式】値 …（次の【まで）
        $re = '/['.$open.']\s*'.$labelPattern.'\s*['.$close.']\s*([^'.$open.']*)/u';
        if (preg_match($re, $text, $m) === 1) {
            $value = trim($m[1]);

            return $value !== '' ? $value : null;
        }

        return null;
    }

    /**
     * 区切り文字で分割する。
     *
     * @return array<int, string>
     */
    private static function splitValues(string $value): array
    {
        $parts = preg_split(self::SEPARATORS, $value) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn (string $v): bool => $v !== ''));
    }

    /**
     * 全角・半角どちらの括弧も、その中身ごと落とす。
     * 「Vストローム250（V-Strom250）」→「Vストローム250」
     */
    private static function stripParentheses(string $text): string
    {
        return trim(preg_replace('/[（(\[［][^）)\]］]*[）)\]］]/u', '', $text) ?? $text);
    }

    /**
     * 比較用の正規化: 全角英数記号→半角、空白（半角・全角）除去、trim。
     * 車種名にも型式にも同じものを通し、比較の左右で必ず揃える。
     */
    public static function normalize(string $text): string
    {
        $text = mb_convert_kana($text, 'a');
        $text = preg_replace('/[\s\x{3000}]+/u', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * raw で重複排除する。
     *
     * @param  array<int, array{raw: string, regulation: string|null, code: string}>  $codes
     * @return array<int, array{raw: string, regulation: string|null, code: string}>
     */
    private static function uniqueByRaw(array $codes): array
    {
        $seen = [];
        foreach ($codes as $c) {
            $seen[$c['raw']] = $c;
        }

        return array_values($seen);
    }
}
