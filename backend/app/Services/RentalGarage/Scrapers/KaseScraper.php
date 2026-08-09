<?php

declare(strict_types=1);

namespace App\Services\RentalGarage\Scrapers;

use App\Models\RentalGarage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

/**
 * 加瀬倉庫「レンタルボックス」（kase3535.com）スクレイパー。
 *
 * robots.txt は Allow: /（禁止は /favorite/ /history/ /search/ *.pdf /api/ のみ、2026-08 確認）。
 * よって /search/ は使わず、/type/bike/ 配下（バイク収納）だけを辿る。
 *
 * ■ sitemap を起点にする理由（2026-08 実測）
 *   都道府県ページ /type/bike/{県}/ の2ページ目以降（?page 相当）は、件数表示（「全146件」
 *   「21-40」）は正しく出るのに物件カードが描画されず「物件が見つかりませんでした」になる
 *   （加瀬側サイトの不具合でこちらでは回避不可）。そのため都道府県ページのページ送りでは
 *   各県20件で頭打ちになり取りこぼす。一方、市区町村ページ /type/bike/{県}/{市区町村}/ は
 *   正常に描画され、それらは sitemap.xml に173件すべて載っている。よって都道府県ページ＋
 *   ページ送りをやめ、sitemap から市区町村ページを列挙して辿る方式に変更した。
 *
 * 導線（2026-08 時点で本番確認）:
 *   - サイトマップ: https://www.kase3535.com/sitemap.xml
 *       バイク収納の市区町村ページ ^https://www\.kase3535\.com/type/bike/[a-z]+/[a-z]+/$
 *       （173件想定）だけを抽出する。sitemap にはバイク収納以外の物件URL(約1,916件)も
 *       含まれるが、それらは使わない（「バイク収納可」の絞り込みが失われるため）。
 *   - 市区町村ページ: /type/bike/{県}/{市区町村}/（正常描画・ページ送り無し）
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
    private const SITEMAP_URL = self::BASE.'/sitemap.xml';
    private const OPERATOR = '加瀬倉庫';

    /**
     * 市区町村ページの1ページ表示上限。ちょうどこの件数が取れた場合は、機能していない
     * ページ送りで残りを取りこぼしている可能性があるため警告ログを出す（後追い用）。
     */
    private const PAGE_SIZE = 20;

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
        $sitemap = $this->get(self::SITEMAP_URL);
        if ($sitemap === null) {
            return; // sitemap が取れなければ何も出さない
        }

        $cityUrls = $this->extractCityUrlsFromSitemap($sitemap);
        if ($cityUrls === []) {
            return;
        }

        $emitted = 0;
        $seenDetail = []; // 詳細URLの重複取得を防ぐ（市区町村をまたいだ重複も含む）

        foreach ($cityUrls as $cityUrl) {
            $this->throttle(); // 市区町村ページ取得前に3秒
            $html = $this->get($cityUrl);
            if ($html === null) {
                continue; // この市区町村は飛ばす（他は続行）
            }

            $detailUrls = $this->extractDetailUrls($html);

            // 20件ちょうど＝機能していないページ送りで残りを取りこぼしている可能性。
            // 後から手当てできるよう、市区町村URLと件数を残す。
            if (count($detailUrls) === self::PAGE_SIZE) {
                Log::warning('KaseScraper: 市区町村ページが20件ちょうど。ページ送り取りこぼしの可能性', [
                    'city_url' => $cityUrl,
                    'count' => count($detailUrls),
                ]);
            }

            foreach ($detailUrls as $detailUrl) {
                if (isset($seenDetail[$detailUrl])) {
                    continue;
                }
                $seenDetail[$detailUrl] = true;

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
        }
    }

    /**
     * sitemap.xml から「バイク収納の市区町村ページ」URL のみを重複なく抽出する。
     * ^https://www\.kase3535\.com/type/bike/[a-z]+/[a-z]+/$ に一致する <loc> だけを採る
     * （バイク収納以外の物件URLは対象外＝バイク可の絞り込みを維持）。
     *
     * @return array<int, string>
     */
    private function extractCityUrlsFromSitemap(string $sitemap): array
    {
        if (! preg_match_all('#<loc>\s*([^<]+?)\s*</loc>#u', $sitemap, $m)) {
            return [];
        }

        $urls = [];
        foreach ($m[1] as $loc) {
            if (preg_match('#^https://www\.kase3535\.com/type/bike/[a-z]+/[a-z]+/$#', $loc)) {
                $urls[$loc] = true;
            }
        }

        return array_keys($urls);
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
