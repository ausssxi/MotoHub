<?php

namespace App\Services\Parts;

class PartsCodeExtractor
{
    /**
     * 車体型式コード (2BL-RN52J, 8BL-RN80J, EBL-RN34J 等)
     */
    private const VEHICLE_TYPE_RE = '/^[0-9A-Z]{2,3}-[A-Z]{2}\d{2}[A-Z]$/i';

    /**
     * バイク車種名 (XSR900, MT-09, YZF-R1 等)
     */
    private const BIKE_MODEL_RE = '/^(XSR\d|MT-?\d|YZF-?R\d|CBR\d|GSX-?[RS]\d|ZX-?\d|CB\d|FZ-?\d|NINJA|V-?MAX|TT\d|FJR\d|TMAX|XMAX|NMAX|PCX\d|ADV\d|CRF\d|WR\d|BOLT|VMAX)/i';

    /** ASCII英数字1文字 */
    private const ALNUM    = '[A-Za-z0-9]';

    /** ASCII英数字・アンダースコア・ハイフン */
    private const ALNUM_HU = '[A-Za-z0-9_-]';

    /**
     * itemName + itemCaption からJANコードと品番を抽出
     */
    public static function extract(string $name, string $caption = ''): array
    {
        $combined = $name . ' ' . $caption;
        return [
            'jan'        => self::extractJan($combined),
            'partNumber' => self::extractPartNumber($combined),
        ];
    }

    /**
     * JANコード抽出 (13桁, 日本のプレフィックス 45/49)
     */
    public static function extractJan(string $text): ?string
    {
        // 明示的ラベル付き
        if (preg_match('/JAN[コード：:\s]*(\d{13})/u', $text, $m)) {
            return $m[1];
        }
        // 45xxxxx or 49xxxxx で始まる13桁
        if (preg_match('/\b(4[59]\d{11})\b/', $text, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * メーカー品番抽出 (車体型式・車種名は除外)
     */
    public static function extractPartNumber(string $text): ?string
    {
        $candidates = [];

        // パターン1: 明示的ラベル (品番, 型番, 商品コード)
        if (preg_match_all('/(?:品番|型番|商品コード|Part ?No\.?)[：:\s]*(' . self::ALNUM . self::ALNUM_HU . '{4,})/u', $text, $m)) {
            foreach ($m[1] as $v) $candidates[] = $v;
        }

        // パターン2a: 英字始まり+数字+ハイフン区切り (S-Y9R2-AFC, G1016-24-001)
        if (preg_match_all('/(?<![A-Za-z0-9_-])([A-Za-z]{1,5}\d[A-Za-z0-9]*(?:-[A-Za-z0-9]+){1,4})(?![A-Za-z0-9_-])/u', $text, $m)) {
            foreach ($m[1] as $v) $candidates[] = $v;
        }

        // パターン2b: 全英字始まりセグメント (SPT-MT9-PB-22a) — 数字を含む場合のみ
        if (preg_match_all('/(?<![A-Za-z0-9_-])([A-Za-z]{1,5}(?:-[A-Za-z0-9]+){2,4})(?![A-Za-z0-9_-])/u', $text, $m)) {
            foreach ($m[1] as $v) {
                if (preg_match('/\d/', $v)) $candidates[] = $v;
            }
        }

        // パターン3: 数字-数字-英数字 (110-380-5T50, 70-963-10013)
        if (preg_match_all('/(?<![A-Za-z0-9_-])(\d{2,3}-\d{2,3}-[A-Za-z0-9]{3,})(?![A-Za-z0-9_-])/u', $text, $m)) {
            foreach ($m[1] as $v) $candidates[] = $v;
        }

        // パターン4: OEM部品番号 (4FM-14613-00)
        if (preg_match_all('/\b(\d[A-Za-z]{1,2}-\d{4,}-\d{2})\b/', $text, $m)) {
            foreach ($m[1] as $v) $candidates[] = $v;
        }

        // フィルタリング・重複排除
        $seen = [];
        foreach ($candidates as $c) {
            $c = self::sanitize($c);
            if (strlen($c) < 6) continue;
            if (preg_match(self::VEHICLE_TYPE_RE, $c)) continue;
            if (preg_match(self::BIKE_MODEL_RE, $c)) continue;
            $key = strtoupper($c);
            if (!isset($seen[$key])) {
                $seen[$key] = $c;
            }
        }

        return !empty($seen) ? reset($seen) : null;
    }

    /**
     * 品番候補の先頭・末尾から非ASCII文字を除去する安全弁
     */
    private static function sanitize(string $code): string
    {
        $code = preg_replace('/[^\x20-\x7E]+$/u', '', $code);
        $code = preg_replace('/^[^\x20-\x7E]+/u', '', $code);
        return trim($code);
    }
}
