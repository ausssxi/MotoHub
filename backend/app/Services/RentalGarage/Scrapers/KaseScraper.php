<?php

declare(strict_types=1);

namespace App\Services\RentalGarage\Scrapers;

use App\Models\RentalGarage;
use Symfony\Component\DomCrawler\Crawler;

/**
 * 加瀬倉庫「レンタルボックス」（kase3535.com）スクレイパー。
 *
 * robots.txt は Allow: /（禁止は /favorite/ /history/ /search/ *.pdf /api/ のみ、2026-08 確認）。
 * よって /search/ は使わず、/type/bike/ 配下の静的な一覧ページ（バイク収納）だけを辿る。
 *
 * 導線（2026-08 時点で本番確認）:
 *   - 都道府県一覧: https://www.kase3535.com/type/bike/
 *       ページ内の href から都道府県スラッグ（aichi, tokyo …）を得る（/type/bike/{slug}/）。
 *   - バイク収納一覧: /type/bike/{slug}/ 、2ページ目以降は /type/bike/{slug}/page/{n}/（1ページ20件）。
 *       物件詳細URLは href のパスが正規表現 ^/[a-z]+/[a-z]+/[0-9]+/$ に一致するものとして現れる。
 *   - 物件詳細: /{都道府県スラッグ}/{市区町村スラッグ}/{数字ID}/
 *       JSON-LD（SelfStorage / Product）が埋め込まれている:
 *         SelfStorage: name / url / address(addressRegion, addressLocality)
 *                      / offers(AggregateOffer: lowPrice, highPrice)
 *         Product:     name / description（「{住所}の収納スペース。料金: …」）/ image
 *       広さは本文に「2帖～8帖」の形（帖の範囲）で入る。
 *
 * 取得HTMLはすべて Http でメモリ上に取り込むだけで、リポジトリにはファイルを書き出さない
 * （デバッグ用のHTMLダンプが要る場合も /tmp に置き、リポジトリへはコミットしないこと）。
 */
final class KaseScraper extends AbstractRentalGarageScraper
{
    private const BASE = 'https://www.kase3535.com';
    private const LIST_ROOT = self::BASE.'/type/bike/';
    private const OPERATOR = '加瀬倉庫';

    /** 都道府県ごとのページ送り安全上限（無限ループ防止）。 */
    private const MAX_PAGES_PER_PREF = 200;

    public function key(): string
    {
        return 'kase';
    }

    public function label(): string
    {
        return self::OPERATOR;
    }

    /**
     * 1リクエストにつき3秒空ける（加瀬向けに基底の1秒から広げる）。
     */
    protected function throttle(): void
    {
        sleep(3);
    }

    public function fetch(?int $limit = null): iterable
    {
        $rootHtml = $this->get(self::LIST_ROOT);
        if ($rootHtml === null) {
            return; // 都道府県一覧が取れなければ何も出さない
        }

        $slugs = $this->extractPrefectureSlugs($rootHtml);
        if ($slugs === []) {
            return;
        }

        $emitted = 0;
        $seenDetail = []; // 詳細URLの重複取得を防ぐ（都道府県をまたいだ重複も含む）

        foreach ($slugs as $slug) {
            for ($page = 1; $page <= self::MAX_PAGES_PER_PREF; $page++) {
                $listUrl = $page === 1
                    ? self::LIST_ROOT.$slug.'/'
                    : self::LIST_ROOT.$slug.'/page/'.$page.'/';

                $this->throttle(); // 一覧ページ取得前に3秒
                $html = $this->get($listUrl);
                if ($html === null) {
                    break; // このページが取れなければ当該都道府県は打ち切り
                }

                $detailUrls = $this->extractDetailUrls($html);
                $newOnPage = 0;

                foreach ($detailUrls as $detailUrl) {
                    if (isset($seenDetail[$detailUrl])) {
                        continue;
                    }
                    $seenDetail[$detailUrl] = true;
                    $newOnPage++;

                    $this->throttle(); // 詳細ページ取得前に3秒
                    $detailHtml = $this->get($detailUrl);
                    if ($detailHtml === null) {
                        continue;
                    }

                    $row = $this->parseDetail($detailHtml, $detailUrl);
                    if ($row === null) {
                        continue;
                    }

                    yield $row;
                    $emitted++;
                    if ($limit !== null && $emitted >= $limit) {
                        return;
                    }
                }

                // 新規物件が1件も無いページに達したら最終ページの先とみなして打ち切る
                // （末尾を超えた page/{n}/ が最終ページを返し続けても無限ループしない）。
                if ($newOnPage === 0) {
                    break;
                }
            }
        }
    }

    /**
     * /type/bike/ ページから都道府県スラッグ（aichi, tokyo …）を重複なく抽出する。
     *
     * @return array<int, string>
     */
    private function extractPrefectureSlugs(string $html): array
    {
        $crawler = new Crawler($html);

        $slugs = [];
        $crawler->filter('a')->each(function (Crawler $a) use (&$slugs): void {
            $path = $this->hrefPath($a->attr('href') ?? '');
            if ($path !== null && preg_match('#^/type/bike/([a-z]+)/$#', $path, $m)) {
                $slugs[$m[1]] = true;
            }
        });

        return array_keys($slugs);
    }

    /**
     * 一覧ページから物件詳細URL（^/[a-z]+/[a-z]+/[0-9]+/$）を絶対URLで重複なく抽出する。
     *
     * @return array<int, string>
     */
    private function extractDetailUrls(string $html): array
    {
        $crawler = new Crawler($html);

        $urls = [];
        $crawler->filter('a')->each(function (Crawler $a) use (&$urls): void {
            $path = $this->hrefPath($a->attr('href') ?? '');
            if ($path !== null && preg_match('#^/[a-z]+/[a-z]+/[0-9]+/$#', $path)) {
                $urls[self::BASE.$path] = true;
            }
        });

        return array_keys($urls);
    }

    /**
     * href（絶対/相対どちらでも）からパス部分だけを取り出す。取れなければ null。
     */
    private function hrefPath(string $href): ?string
    {
        $href = trim($href);
        if ($href === '') {
            return null;
        }

        $path = parse_url($href, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : null;
    }

    /**
     * 物件詳細ページ（JSON-LD + 本文）を1件の配列にする。取れなければ null。
     *
     * @return array<string, mixed>|null
     */
    private function parseDetail(string $html, string $url): ?array
    {
        $crawler = new Crawler($html);

        $objects = $this->jsonLdObjects($crawler);
        $self = $this->firstOfType($objects, 'SelfStorage');
        $product = $this->firstOfType($objects, 'Product');

        // name は JSON-LD から。SelfStorage 優先、無ければ Product。
        $name = $this->normalizeText((string) ($self['name'] ?? $product['name'] ?? ''));
        // source_url は SelfStorage.url、無ければ取得URL。
        $sourceUrl = $this->normalizeUrl((string) ($self['url'] ?? $url));
        if ($name === '' || $sourceUrl === '') {
            return null;
        }

        // prefecture / city は SelfStorage の住所構造化フィールドから。
        $addr = is_array($self['address'] ?? null) ? $self['address'] : [];
        $region = $this->normalizeText((string) ($addr['addressRegion'] ?? ''));
        $locality = $this->normalizeText((string) ($addr['addressLocality'] ?? ''));

        // address は Product.description の「〜の収納スペース」手前を切り出す。
        $rawAddress = $this->extractAddressFromDescription((string) ($product['description'] ?? ''));
        // description から取れなければ region+locality で代替（座標精度は落ちるが空よりまし）。
        if ($rawAddress === '' && ($region !== '' || $locality !== '')) {
            $rawAddress = $region.$locality;
        }
        $cleanAddress = $rawAddress !== '' ? $this->normalizeAddress($rawAddress) : '';

        // monthly_fee は SelfStorage.offers（AggregateOffer）の lowPrice / highPrice。
        $offers = is_array($self['offers'] ?? null) ? $self['offers'] : [];
        $feeMin = $this->toIntOrNull($offers['lowPrice'] ?? null);
        $feeMax = $this->toIntOrNull($offers['highPrice'] ?? null);

        // size_text は当面 null。
        // 実HTML検査の結果、1ページ内に帖の範囲が複数存在し（例 0.7帖～57.6帖 / 1.6帖～8帖 /
        // 6.5帖～8帖 / 2帖～8帖）、どれが物件固有の値かを現時点で特定できないため。
        // extractSizeText() は消さず残し、本番の dry-run で実データの並びを確認してから
        // どの帖範囲を採るか決めて有効化する。
        $sizeText = null;

        // garage_type は 'container' 固定。
        // 本文からの屋内判定は削除した。理由: 「屋内」は全物件共通の定型文
        // （meta description「屋内型トランクルームと屋外型コンテナボックスから選べます」・
        //  keywords「屋外型, 屋内型」）としてのみ15回出現し、本文一致では全物件が indoor に
        // なってしまう。JSON-LD（SelfStorage / Product / WebPage / BreadcrumbList）にも
        // 屋内/屋外を示す type・category 相当のフィールドが無いため、判定材料が存在しない。
        $garageType = 'container';

        // description: 住所正規化で末尾表現などを落とした場合のみ原文を残して検証可能にする。
        $descParts = [];
        if ($cleanAddress !== $rawAddress && $rawAddress !== '') {
            $descParts[] = '所在地表記: '.$rawAddress;
        }
        $description = $descParts !== [] ? implode("\n", $descParts) : null;

        return [
            'name' => $name,
            'operator' => self::OPERATOR,
            'garage_type' => $garageType,
            'address' => $cleanAddress,
            'prefecture' => $region !== '' ? $region : ($cleanAddress !== '' ? $this->extractPrefecture($cleanAddress) : null),
            'city' => $locality !== '' ? $locality : ($cleanAddress !== '' ? $this->extractCity($cleanAddress) : null),
            'monthly_fee_min' => $feeMin,
            'monthly_fee_max' => $feeMax,
            'size_text' => $sizeText,
            'is_24h' => null,
            'has_power' => null,
            'has_security' => null,
            'has_shutter' => null,
            'website_url' => $sourceUrl,
            'source_url' => $sourceUrl,
            'description' => $description,
            'is_active' => true,
        ];
    }

    /**
     * Product.description（「{住所}の収納スペース。料金: …」）から住所部分を切り出す。
     * 「の収納スペース」より前を住所とみなす。見つからなければ空文字。
     */
    private function extractAddressFromDescription(string $description): string
    {
        $description = $this->normalizeText($description);
        if ($description === '') {
            return '';
        }

        $pos = mb_strpos($description, 'の収納スペース');
        if ($pos === false) {
            return '';
        }

        return $this->normalizeText(mb_substr($description, 0, $pos));
    }

    /**
     * 本文から広さ（帖の範囲／単一）を取り出す。例「2帖～8帖」「2.0帖」。取れなければ null。
     */
    private function extractSizeText(string $bodyText): ?string
    {
        // 範囲（「2帖～8帖」。～は U+FF5E/U+301C/U+007E、ハイフンにも対応）を優先。
        if (preg_match('/[0-9][0-9.]*\s*帖\s*[〜～~\-]\s*[0-9][0-9.]*\s*帖/u', $bodyText, $m)) {
            return $m[0];
        }
        // 単一（「2.0帖」）。
        if (preg_match('/[0-9][0-9.]*\s*帖/u', $bodyText, $m)) {
            return $m[0];
        }

        return null;
    }

    /**
     * ページ内の全 JSON-LD を平坦化した連想配列群として返す（@graph・配列にも対応）。
     *
     * @return array<int, array<string, mixed>>
     */
    private function jsonLdObjects(Crawler $crawler): array
    {
        $objects = [];
        $crawler->filter('script[type="application/ld+json"]')->each(function (Crawler $n) use (&$objects): void {
            $decoded = json_decode($n->text(''), true);
            if (is_array($decoded)) {
                $this->collectJsonLd($decoded, $objects);
            }
        });

        return $objects;
    }

    /**
     * JSON-LD の入れ子（@graph / リスト）を展開して連想配列を集める。
     *
     * @param  array<mixed>  $data
     * @param  array<int, array<string, mixed>>  $out
     */
    private function collectJsonLd(array $data, array &$out): void
    {
        if (isset($data['@graph']) && is_array($data['@graph'])) {
            foreach ($data['@graph'] as $g) {
                if (is_array($g)) {
                    $this->collectJsonLd($g, $out);
                }
            }

            return;
        }

        if (array_is_list($data)) {
            foreach ($data as $item) {
                if (is_array($item)) {
                    $this->collectJsonLd($item, $out);
                }
            }

            return;
        }

        $out[] = $data;
    }

    /**
     * JSON-LD オブジェクト群から指定 @type を最初に持つものを返す。無ければ null。
     * （@type は文字列/配列どちらもあり得る）
     *
     * @param  array<int, array<string, mixed>>  $objects
     * @return array<string, mixed>|null
     */
    private function firstOfType(array $objects, string $type): ?array
    {
        foreach ($objects as $o) {
            $t = $o['@type'] ?? null;
            if ($t === $type) {
                return $o;
            }
            if (is_array($t) && in_array($type, $t, true)) {
                return $o;
            }
        }

        return null;
    }

    /**
     * "17160" / "17,160" / 17160 → int。null/空/数字なしは null。
     */
    private function toIntOrNull(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', (string) $value) ?? '';

        return $digits === '' ? null : (int) $digits;
    }
}
