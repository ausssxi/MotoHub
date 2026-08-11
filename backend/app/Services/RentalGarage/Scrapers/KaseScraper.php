<?php

declare(strict_types=1);

namespace App\Services\RentalGarage\Scrapers;

use App\Models\RentalGarage;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

/**
 * 加瀬倉庫「レンタルボックス」（kase3535.com）スクレイパー。
 *
 * ■ 設計方針（2026-08 実測に基づく）
 * - /type/bike/ カテゴリページ起点でバイク収納可の絞り込みを維持する。
 * - 市区ページのページ送りは機能しないため（1ページ20件で頭打ち）、
 *   不足がある都道府県は sitemap 上の駅ページで補完する。
 *
 * robots.txt は Allow: /（禁止は /favorite/ /history/ /search/ *.pdf /api/ のみ、2026-08 確認）。
 *
 * 導線:
 *   - sitemap: https://www.kase3535.com/sitemap.xml
 *       市区町村ページ /type/bike/{県}/{市区}/ と
 *       駅ページ /type/bike/{県}/{駅}-station/ を抽出する。
 *   - 市区町村ページ: 物件カード最大20件。総件数は HTML 内「全<!---->N<!---->件」で判定。
 *   - 駅ページ: 不足のある都道府県でのみ取得。同じく最大20件。
 *   - 物件詳細: /{県スラッグ}/{市区スラッグ}/{数字ID}/ — JSON-LD（SelfStorage / Product）。
 *
 * 取得HTMLはすべて Http でメモリ上に取り込むだけで、リポジトリにはファイルを書き出さない
 * （デバッグ用のHTMLダンプが要る場合も /tmp に置き、リポジトリへはコミットしないこと）。
 */
final class KaseScraper extends AbstractRentalGarageScraper
{
    private const BASE = 'https://www.kase3535.com';
    private const SITEMAP_URL = self::BASE.'/sitemap.xml';
    private const OPERATOR = '加瀬倉庫';

    /** 市区・駅ページの1ページ表示上限。 */
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
            Log::warning('KaseScraper: sitemap の取得に失敗');

            return;
        }

        $cityUrls = $this->extractCityUrlsFromSitemap($sitemap);
        if ($cityUrls === []) {
            Log::warning('KaseScraper: sitemap に市区町村ページが0件');

            return;
        }

        // ── Phase 1: 市区ページから物件詳細URLを収集 ──
        Log::info('KaseScraper: 市区ページ取得開始', ['count' => count($cityUrls)]);

        $allDetailUrls = [];     // url => true（重複排除用）
        $shortageByPref = [];    // prefSlug => true
        $cityStats = [];         // "pref/city" => [total, fetched, shortage]
        $cityPageCount = 0;

        foreach ($cityUrls as $cityUrl) {
            $this->throttle();
            $html = $this->get($cityUrl);
            if ($html === null) {
                continue;
            }
            $cityPageCount++;

            if (! preg_match('#/type/bike/([a-z]+)/([a-z]+)/$#', $cityUrl, $m)) {
                continue;
            }
            $prefSlug = $m[1];
            $citySlug = $m[2];

            $detailUrls = $this->extractDetailUrls($html);
            $totalCount = $this->extractTotalCount($html);

            foreach ($detailUrls as $url) {
                $allDetailUrls[$url] = true;
            }

            $fetched = count($detailUrls);
            $shortage = ($totalCount !== null && $totalCount > $fetched)
                ? $totalCount - $fetched
                : 0;

            $cityStats["{$prefSlug}/{$citySlug}"] = [
                'total' => $totalCount,
                'fetched' => $fetched,
                'shortage' => $shortage,
            ];

            if ($shortage > 0) {
                $shortageByPref[$prefSlug] = true;
                Log::info('KaseScraper: 不足検知', [
                    'city_url' => $cityUrl,
                    'total' => $totalCount,
                    'fetched' => $fetched,
                    'shortage' => $shortage,
                ]);
            }
        }

        Log::info('KaseScraper: 市区ページ取得完了', [
            'city_pages' => $cityPageCount,
            'detail_urls_so_far' => count($allDetailUrls),
            'prefs_with_shortage' => array_keys($shortageByPref),
        ]);

        // ── Phase 2: 不足のある都道府県の駅ページで補完 ──
        $stationPageCount = 0;

        if ($shortageByPref !== []) {
            $stationUrlsByPref = $this->extractStationUrlsFromSitemap($sitemap);

            foreach (array_keys($shortageByPref) as $prefSlug) {
                $stationUrls = $stationUrlsByPref[$prefSlug] ?? [];
                Log::info("KaseScraper: 駅ページ補完開始 [{$prefSlug}]", [
                    'station_pages' => count($stationUrls),
                ]);

                foreach ($stationUrls as $stationUrl) {
                    $this->throttle();
                    $html = $this->get($stationUrl);
                    if ($html === null) {
                        continue;
                    }
                    $stationPageCount++;

                    $stationTotal = $this->extractTotalCount($html);
                    if ($stationTotal !== null && $stationTotal > self::PAGE_SIZE) {
                        Log::warning('KaseScraper: 駅ページが20件超。取りこぼしの可能性', [
                            'station_url' => $stationUrl,
                            'total' => $stationTotal,
                        ]);
                    }

                    $stationDetailUrls = $this->extractDetailUrls($html);
                    foreach ($stationDetailUrls as $url) {
                        $allDetailUrls[$url] = true;
                    }
                }
            }

            Log::info('KaseScraper: 駅ページ補完完了', [
                'station_pages' => $stationPageCount,
                'detail_urls_total' => count($allDetailUrls),
            ]);
        }

        // ── 補完後の不足チェック ──
        $fetchedByCity = [];
        foreach (array_keys($allDetailUrls) as $url) {
            $path = parse_url($url, PHP_URL_PATH);
            if ($path !== null && preg_match('#^/([a-z]+)/([a-z]+)/[0-9]+/$#', $path, $pm)) {
                $key = "{$pm[1]}/{$pm[2]}";
                $fetchedByCity[$key] = ($fetchedByCity[$key] ?? 0) + 1;
            }
        }

        foreach ($cityStats as $key => $info) {
            if ($info['shortage'] <= 0) {
                continue;
            }
            $newFetched = $fetchedByCity[$key] ?? $info['fetched'];
            $remaining = ($info['total'] !== null) ? max(0, $info['total'] - $newFetched) : 0;
            if ($remaining > 0) {
                Log::warning('KaseScraper: 駅ページ補完後もなお不足', [
                    'city' => $key,
                    'total' => $info['total'],
                    'fetched' => $newFetched,
                    'shortage' => $remaining,
                ]);
            }
        }

        // ── Phase 3: 物件詳細ページを取得し yield ──
        $detailUrlList = array_keys($allDetailUrls);
        $emitted = 0;
        $excluded = ['fetch_failed' => 0, 'parse_failed' => 0];

        $totalExpected = 0;
        foreach ($cityStats as $info) {
            $totalExpected += $info['total'] ?? $info['fetched'];
        }

        Log::info('KaseScraper: 詳細ページ取得開始', ['unique_urls' => count($detailUrlList)]);

        try {
            foreach ($detailUrlList as $i => $detailUrl) {
                $this->throttle();
                $detailHtml = $this->get($detailUrl);
                if ($detailHtml === null) {
                    $excluded['fetch_failed']++;

                    continue;
                }

                $row = $this->parseDetail($detailHtml, $detailUrl);
                if ($row === null) {
                    $excluded['parse_failed']++;

                    continue;
                }

                yield $row;
                $emitted++;

                if ($emitted % 100 === 0) {
                    Log::info('KaseScraper: 進捗', [
                        'emitted' => $emitted,
                        'processed' => $i + 1,
                        'total' => count($detailUrlList),
                    ]);
                }

                if ($limit !== null && $emitted >= $limit) {
                    return;
                }
            }
        } finally {
            Log::info('KaseScraper: 取得完了', [
                'city_pages' => $cityPageCount,
                'station_pages' => $stationPageCount,
                'unique_detail_urls' => count($detailUrlList),
                'total_expected' => $totalExpected,
                'emitted' => $emitted,
                'difference' => $totalExpected - count($detailUrlList),
                'excluded' => array_sum($excluded),
                'exclusion_reasons' => $excluded,
            ]);
        }
    }

    /**
     * sitemap.xml から「バイク収納の市区町村ページ」URL を重複なく抽出する。
     * ^https://www\.kase3535\.com/type/bike/[a-z]+/[a-z]+/$ に一致する <loc> だけを採る。
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
     * sitemap.xml から駅ページ URL を都道府県スラッグ別に抽出する。
     * /type/bike/{県}/{slug}-station/ の形。
     *
     * @return array<string, array<int, string>>  prefSlug => [url, ...]
     */
    private function extractStationUrlsFromSitemap(string $sitemap): array
    {
        if (! preg_match_all('#<loc>\s*([^<]+?)\s*</loc>#u', $sitemap, $m)) {
            return [];
        }

        $byPref = [];
        foreach ($m[1] as $loc) {
            if (preg_match('#^https://www\.kase3535\.com/type/bike/([a-z]+)/[a-z0-9-]+-station/$#', $loc, $pm)) {
                $byPref[$pm[1]][] = $loc;
            }
        }

        return $byPref;
    }

    /**
     * 一覧ページ（市区／駅）から物件詳細URL（^/[a-z]+/[a-z]+/[0-9]+/$）を絶対URLで重複なく抽出する。
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
     * 一覧ページ HTML から総件数（「全N件」）を読み取る。
     * 加瀬サイトでは 全<!-- -->20<!-- -->件 の形で出力される。取れなければ null。
     */
    private function extractTotalCount(string $html): ?int
    {
        if (preg_match('/全<!--\s*-->(\d+)<!--\s*-->件/u', $html, $m)) {
            return (int) $m[1];
        }

        return null;
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
