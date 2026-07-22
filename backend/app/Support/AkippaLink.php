<?php

declare(strict_types=1);

namespace App\Support;

/**
 * akippa アフィリCTAのリンク解決（純関数）。
 *
 * ・akippa物件（management_company==='akippa株式会社'）でのみ CTA を出す（非akippaは null＝非表示）。
 * ・akippaの予約URLは notes（無ければ description）に文章として埋め込まれているため正規表現で抽出する。
 * ・A8MAT設定 かつ 抽出URLあり → 駐車場別ディープリンク（成果がユーザーに入る）。
 * ・A8規約厳守：飛び先は必ず https://www.akippa.com/ 内のみ（抽出パターン自体がドメインを固定）。
 * ・抽出不可 → 汎用リンク（akippaトップ）へフォールバック。汎用も無ければ null（偽リンクを置かない）。
 */
final class AkippaLink
{
    /** akippa物件の判定キー（management_company の完全一致）。 */
    private const AKIPPA_COMPANY = 'akippa株式会社';

    /**
     * notes/description 内の akippa 予約URL抽出パターン。
     * 末尾の空白・全角空白・日本語括弧は含めない（「」等を巻き込まない）。utmクエリ(?..&..)は保持。
     * 飛び先を akippa.com 内に固定＝A8規約のドメイン制約をパターンで担保。
     */
    private const AKIPPA_URL_PATTERN = '#https://www\.akippa\.com/[^\s　「」『』【】（）]+#u';

    /**
     * CTA用のリンク解決。表示不可（非akippa物件・リンク無し）は null。
     *
     * @return array{url:string, deeplink:bool}|null
     */
    public static function ctaFor(?string $managementCompany, ?string $notes, ?string $description = null): ?array
    {
        // 非akippa物件はCTAを出さない（akippaで予約できない駐車場にakippa CTAを出さない）。
        if ($managementCompany !== self::AKIPPA_COMPANY) {
            return null;
        }

        $a8mat = (string) config('parking.affiliate.akippa.a8mat', '');
        $generic = (string) config('parking.affiliate.akippa.url', '');
        $akippaUrl = self::extractAkippaUrl($notes, $description);

        // ディープリンク：A8MAT設定 かつ notes/description から akippa.com URL を抽出できた。
        if ($a8mat !== '' && $akippaUrl !== null) {
            return [
                'url' => 'https://px.a8.net/svt/ejp?a8mat='.$a8mat.'&a8ejpredirect='.rawurlencode($akippaUrl),
                'deeplink' => true,
            ];
        }

        // フォールバック：汎用リンク（akippaトップ）。
        if ($generic !== '') {
            return ['url' => $generic, 'deeplink' => false];
        }

        return null;
    }

    /** notes（無ければ description）から akippa.com のURLを1つ抽出。無ければ null。 */
    public static function extractAkippaUrl(?string $notes, ?string $description = null): ?string
    {
        foreach ([$notes, $description] as $text) {
            if (is_string($text) && preg_match(self::AKIPPA_URL_PATTERN, $text, $m)) {
                return $m[0];
            }
        }

        return null;
    }

    /**
     * テキスト中の akippa URL を除去して表示用に整える（成果漏れ防止・文言は残す）。
     * URL除去後、連続空白（全半角）を1つに圧縮し前後をtrim（過剰整形はしない）。
     */
    public static function stripAkippaUrl(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $stripped = preg_replace(self::AKIPPA_URL_PATTERN, '', $text) ?? $text;
        $stripped = preg_replace('/[ \t　]{2,}/u', ' ', $stripped) ?? $stripped;

        return trim($stripped);
    }
}
