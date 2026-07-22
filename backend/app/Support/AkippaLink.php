<?php

declare(strict_types=1);

namespace App\Support;

/**
 * akippa アフィリCTAのリンク解決（純関数）。
 *
 * ・akippa物件（management_company==='akippa株式会社'）でのみ CTA を出す（非akippaは null＝非表示）。
 * ・A8MAT設定 かつ 飛び先(source_url)が akippa.com 内 → 駐車場別ディープリンク（成果がユーザーに入る）。
 * ・A8規約厳守：飛び先は必ず https://www.akippa.com/ 内のみ。以外/空は汎用リンク（akippaトップ）へフォールバック。
 * ・汎用も無ければ null（偽リンクを置かない）。
 */
final class AkippaLink
{
    /** akippa物件の判定キー（management_company の完全一致）。 */
    private const AKIPPA_COMPANY = 'akippa株式会社';

    /** ディープリンクを許可する飛び先の接頭辞（A8/akippa規約：akippa.com内のみ）。 */
    private const AKIPPA_URL_PREFIX = 'https://www.akippa.com/';

    /**
     * CTA用のリンク解決。表示不可（非akippa物件・リンク無し）は null。
     *
     * @return array{url:string, deeplink:bool}|null
     */
    public static function ctaFor(?string $managementCompany, ?string $sourceUrl): ?array
    {
        // 非akippa物件はCTAを出さない（akippaで予約できない駐車場にakippa CTAを出さない）。
        if ($managementCompany !== self::AKIPPA_COMPANY) {
            return null;
        }

        $a8mat = (string) config('parking.affiliate.akippa.a8mat', '');
        $generic = (string) config('parking.affiliate.akippa.url', '');

        // ディープリンク：A8MAT設定 かつ 飛び先が akippa.com 内（厳格チェック）。
        if ($a8mat !== '' && is_string($sourceUrl) && str_starts_with($sourceUrl, self::AKIPPA_URL_PREFIX)) {
            $redirect = rawurlencode($sourceUrl); // 元URL全体を1回だけエンコード（二重エンコード回避）

            return [
                'url' => 'https://px.a8.net/svt/ejp?a8mat='.$a8mat.'&a8ejpredirect='.$redirect,
                'deeplink' => true,
            ];
        }

        // フォールバック：汎用リンク（akippaトップ）。
        if ($generic !== '') {
            return ['url' => $generic, 'deeplink' => false];
        }

        return null;
    }
}
