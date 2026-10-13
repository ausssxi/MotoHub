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
     * 生URL撤去後に残る「リンク先を見てね」系の宙に浮いた文言。
     * URLを消したため指す先が無くなった文を、自然な文（akippa予約ページ案内）へ置換する。
     * ★置換は「元テキストに akippa URL があった」ときだけ行う（cleanDisplayText がゲート）。
     *   akippa以外のURL（撤去しない）を指す文言には触れない。
     * 追加の言い回しが見つかったらここに足す（notes/description の集計で洗い出す）。
     */
    private const DANGLING_LINK_PHRASES = [
        '#詳細は(?:備考(?:欄)?の?)?(?:下記(?:の)?)?(?:URL|ＵＲＬ)(?:の)?(?:リンク)?(?:先)?を?(?:ご|御)?確認(?:ください|下さい)。?#u',
        '#詳細は(?:下記(?:の)?)?リンク先?を?(?:ご|御)?確認(?:ください|下さい)。?#u',
        '#(?:詳しく|詳細)は(?:上記|下記|備考)?の?(?:URL|ＵＲＬ|リンク)(?:先)?(?:より|から|を)(?:ご|御)?(?:確認|参照)(?:ください|下さい)。?#u',
    ];

    /** 宙に浮いた文言の置換先（akippa予約ページ案内）。 */
    private const AKIPPA_FALLBACK_SENTENCE = '詳細は akippa の予約ページでご確認ください。';

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

    /**
     * 表示用にテキストを整える（DBは書き換えず表示側で整形）。
     *
     * ① akippa の生URLを撤去（stripAkippaUrl）。
     * ② 元テキストに akippa URL があった場合のみ、撤去の名残を掃除する：
     *    ・宙に浮いた「リンク先をご確認ください」系の文言を akippa 予約ページ案内へ置換。
     *    ・先頭等の「▼」「▽」記号を除去。
     *    ・URL撤去で空になった日本語括弧「」『』（）【】を除去し、閉じ括弧の余りを落とす。
     * akippa URL が無いテキスト（＝撤去していない）には①以外手を加えない。
     */
    public static function cleanDisplayText(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }

        $hadAkippaUrl = preg_match(self::AKIPPA_URL_PATTERN, $text) === 1;

        $out = self::stripAkippaUrl($text);

        if (! $hadAkippaUrl) {
            return $out;
        }

        // 宙に浮いたリンク誘導文を自然な案内へ置換。
        foreach (self::DANGLING_LINK_PHRASES as $pattern) {
            $out = preg_replace($pattern, self::AKIPPA_FALLBACK_SENTENCE, $out) ?? $out;
        }

        $out = self::tidyUrlRemovalArtifacts($out);

        return trim($out);
    }

    /** URL撤去の名残（▼記号・空/余り括弧・連続空白）を掃除する。 */
    private static function tidyUrlRemovalArtifacts(string $text): string
    {
        // 誘導用の三角記号を除去。
        $text = preg_replace('/[▼▽]/u', '', $text) ?? $text;

        // URL撤去で空になった日本語括弧ペアを除去（「」→ 消す）。
        $text = preg_replace('/「」|『』|（）|【】/u', '', $text) ?? $text;

        // 閉じ括弧の数が開き括弧より多ければ、余った閉じ括弧を末尾から落とす
        // （元が 「URL」 のうち開き括弧だけURLと一緒に消えた等の余り対策）。
        foreach ([['「', '」'], ['『', '』'], ['（', '）'], ['【', '】']] as [$open, $close]) {
            $diff = substr_count($text, $close) - substr_count($text, $open);
            for ($i = 0; $i < $diff; $i++) {
                $text = preg_replace('/'.$close.'(?=[^'.$close.']*$)/u', '', $text, 1) ?? $text;
            }
        }

        // 連続空白を1つに。
        $text = preg_replace('/[ \t　]{2,}/u', ' ', $text) ?? $text;

        return $text;
    }
}
