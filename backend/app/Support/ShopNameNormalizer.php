<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 店名の正規化（同一性判定の単一実装）。
 *
 * 検索（/shops/search）と重複チェック（ShopSubmissionService::findDuplicates）が
 * 同じ「同一性」の定義を共有するため、正規化ロジックはこの1箇所にのみ置く。
 * どちらか一方だけを直して挙動がドリフトする事故を防ぐ。
 *
 * ・全半角統一（英数字→半角 / カタカナ→全角 / 濁点結合）
 * ・ひらがな→カタカナ統一（「もとぱどっく」＝「モトパドック」）
 * ・スペース（半角・全角）除去
 * ・法人格除去（株式会社・(株)・㈱ 等）
 * ・小文字化
 */
final class ShopNameNormalizer
{
    /** 除去する法人格表記（全角/半角/合字すべて） */
    private const CORPORATE_FORMS = [
        '株式会社', '有限会社', '合同会社', '合資会社',
        '（株）', '(株)', '㈱',
        '（有）', '(有)', '㈲',
    ];

    public static function normalize(string $name): string
    {
        // a: 全角英数→半角 / s: 全角空白→半角 / K: 半角カナ→全角カナ
        // V: 濁点・半濁点を結合（ﾊﾞ→バ） / C: ひらがな→全角カタカナ
        $n = mb_convert_kana($name, 'asKVC');

        $n = str_replace(self::CORPORATE_FORMS, '', $n);

        // 半角・全角スペースをすべて除去
        $n = preg_replace('/[\s\x{3000}]+/u', '', $n) ?? '';

        return mb_strtolower($n);
    }
}
