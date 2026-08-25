<?php

declare(strict_types=1);

namespace App\Support;

/**
 * akippa アフィリCTAのリンク解決（純関数）。
 *
 * ・akippa物件（management_company==='akippa株式会社'）でのみ CTA を出す（非akippaは null＝非表示）。
 * ・akippaの予約URLは notes（無ければ description）に文章として埋め込まれているため正規表現で抽出する。
 * ・A8MAT設定 かつ 抽出URLあり → 駐車場別ディープリンク（アフィリ・成果がユーザーに入る／affiliate=true）。
 * ・A8MAT未設定 かつ 抽出URLあり → その駐車場のakippaページへ素のリンク（非アフィリ・成果なし／affiliate=false）。
 *   ★akippaのアフィリは EPC が低く成果条件も合わないため、A8MAT を外せば「読者に有用な個別リンク」だけが残る。
 * ・A8規約厳守：飛び先は必ず https://www.akippa.com/ 内のみ（抽出パターン自体がドメインを固定）。
 * ・抽出不可 → null（偽リンクもakippaトップへの送客も置かない）。
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
     * CTA用のリンク解決。表示不可（非akippa物件・個別URL抽出不可）は null。
     *
     * @return array{url:string, deeplink:bool, affiliate:bool}|null
     */
    public static function ctaFor(?string $managementCompany, ?string $notes, ?string $description = null): ?array
    {
        // 非akippa物件はCTAを出さない（akippaで予約できない駐車場にakippa CTAを出さない）。
        if ($managementCompany !== self::AKIPPA_COMPANY) {
            return null;
        }

        // 個別ページURL（notes/description 内の akippa.com URL）が無ければリンクを出さない。
        // akippaトップへの汎用送客はしない（アフィリを外した以上、価値の薄いトップ送客は置かない）。
        $akippaUrl = self::extractAkippaUrl($notes, $description);
        if ($akippaUrl === null) {
            return null;
        }

        $a8mat = (string) config('parking.affiliate.akippa.a8mat', '');

        // A8MAT設定時：その駐車場への A8 ディープリンク（アフィリ・成果がユーザーに入る）。
        if ($a8mat !== '') {
            return [
                'url' => 'https://px.a8.net/svt/ejp?a8mat='.$a8mat.'&a8ejpredirect='.rawurlencode($akippaUrl),
                'deeplink' => true,
                'affiliate' => true,
            ];
        }

        // A8MAT未設定時：アフィリを介さず、その駐車場の akippa ページへ素のリンクで飛ばす。
        // 読者に有用・成果は発生しない（表示側は rel="nofollow noopener"／PR表記なし）。
        return [
            'url' => $akippaUrl,
            'deeplink' => true,
            'affiliate' => false,
        ];
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
