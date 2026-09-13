<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Google News RSS が配信するゴミ（トップページ・掲示板の一覧ページ等）を判定する。
 *
 * 判定は「- サイト名」を落としたあとの整形済みタイトルに対して行う。
 * news:fetch（取り込み時のスキップ）と news:purge-junk（既存レコードの掃除）で共用する。
 *
 * ★ source='MotoHub'（自前記事）には使わない。呼び出し側で外部RSSに限定すること。
 */
final class NewsJunkFilter
{
    /**
     * ゴミなら理由ラベルを、正当な記事なら null を返す。理由はログ・出力の内訳に使う。
     *
     * @param  array<int, string>  $excludedSources  ニュース扱いしない source（部分一致・大小無視）。
     *                                               config('news.excluded_sources') を呼び出し側が渡す。
     *                                               （NewsJunkFilter を Unit テストで使うため内部で config() は呼ばない）
     */
    public static function junkReason(string $title, string $source, array $excludedSources = []): ?string
    {
        $t = trim($title);

        // (e) 5文字未満・空
        if (mb_strlen($t) < 5) {
            return 'too_short';
        }

        // (a) 「ホーム｜」「ホーム |」等。★「ホーム」始まりではなく区切り記号つきに限定
        //     （「ホームセンターで買える〜」のような正当な記事を巻き込まないため）。
        if (preg_match('/ホーム\s*[｜|]/u', $t) === 1) {
            return 'home_pipe';
        }

        // (b) 「〜の投稿一覧」で終わる（掲示板の一覧ページ）
        if (str_ends_with($t, 'の投稿一覧')) {
            return 'post_list';
        }

        // (c) 「〜の投稿」で終わる
        if (str_ends_with($t, 'の投稿')) {
            return 'post';
        }

        // (d) 実質サイト名だけ（括弧内と記号を除いた文字列が source と一致）
        if (self::isSiteNameOnly($t, $source)) {
            return 'site_name';
        }

        // (f) slug/画像ページ由来（image / oppo_2 / "oppo_2 | サイト名" 等）。
        //     区切り（| ｜ の最初）より前を見て、スペース無し・日本語無し・英数字と _-. のみなら除外。
        //     ★スペースの有無が唯一の防波堤: 英語の正当な記事タイトルは必ずスペースを含む。
        if (self::isSlugLike($t)) {
            return 'slug_like';
        }

        // (g) ニュースメディアでない source（中古車検索サイト等）。source 単位で切る。
        //     ★例外: タイトルに「試乗レポート」を含むものは記事として成立するので残す。
        if (self::isExcludedSource($source, $excludedSources) && ! str_contains($t, '試乗レポート')) {
            return 'excluded_source';
        }

        return null;
    }

    /**
     * @param  array<int, string>  $excludedSources
     */
    public static function isJunk(string $title, string $source, array $excludedSources = []): bool
    {
        return self::junkReason($title, $source, $excludedSources) !== null;
    }

    /**
     * source が除外リストのいずれかを部分一致（大小無視）で含むか。
     *
     * @param  array<int, string>  $excludedSources
     */
    private static function isExcludedSource(string $source, array $excludedSources): bool
    {
        $source = trim($source);
        if ($source === '' || $excludedSources === []) {
            return false;
        }

        $haystack = mb_strtolower($source);
        foreach ($excludedSources as $ex) {
            $ex = mb_strtolower(trim((string) $ex));
            if ($ex !== '' && str_contains($haystack, $ex)) {
                return true;
            }
        }

        return false;
    }

    /**
     * slug/画像ページ由来のタイトルか。区切り（| ｜ の最初）より前、無ければ全体を見て、
     * 英数字と _ - . のみ（＝スペース無し・日本語無し）なら true。
     */
    private static function isSlugLike(string $title): bool
    {
        // 最初の縦棒（半角 | / 全角 ｜）より前を対象にする。無ければ全体。
        $target = preg_split('/[|｜]/u', $title, 2)[0];
        $target = trim($target);
        if ($target === '') {
            return false;
        }

        // 英数字と _ - . のみ = スペースも日本語も含まない。
        return preg_match('/^[A-Za-z0-9_.\-]+$/', $target) === 1;
    }

    /**
     * タイトルから括弧内と記号を除いた文字列が source と一致するか（例: GooBike(グーバイク) == GooBike）。
     */
    private static function isSiteNameOnly(string $title, string $source): bool
    {
        $source = trim($source);
        if ($source === '') {
            return false;
        }

        $t = self::stripBracketsAndSymbols($title);
        $s = self::stripBracketsAndSymbols($source);
        if ($t === '' || $s === '') {
            return false;
        }

        return mb_strtolower($t) === mb_strtolower($s);
    }

    /**
     * 括弧（半角/全角）とその中身、および記号・空白を除去して文字と数字だけ残す。
     */
    private static function stripBracketsAndSymbols(string $value): string
    {
        $value = (string) preg_replace('/[（(][^）)]*[）)]/u', '', $value);

        return (string) preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
    }
}
