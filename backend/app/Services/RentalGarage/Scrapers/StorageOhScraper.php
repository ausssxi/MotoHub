<?php

declare(strict_types=1);

namespace App\Services\RentalGarage\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

/**
 * ストレージ王（storageoh.jp）スクレイパー。
 *
 * robots.txt は Allow: / （全許可）＋ Sitemap 行あり。店舗一覧は server-sitemap に
 * /search/detail/{id} として列挙されるため、サイトマップを正規導線として全店舗を辿る
 * （ページネーションの推測は不要）。
 *
 * 実HTML構造（2026-08 時点で確認・Next.js SSR / クラス名はハッシュ化）:
 *   - <script type="application/ld+json"> の SelfStorage に構造化データ:
 *       name / url / telephone / address(streetAddress,addressLocality,addressRegion,postalCode)
 *       / priceRange("8250 - 30800") / geo(lat,lng) / description(アクセス説明)
 *   - garage_type: 本文「屋外型」→container /「屋内型」→indoor / 不明→other
 *   - is_24h: 本文「24時間利用 可能/不可」/ 記載なし→null
 *   - has_security: 本文「防犯カメラ 設置/なし」/ 記載なし→null
 *   - size_text: 本文「サイズ 1.6〜8.0帖」
 *   - is_active: 本文に「OPEN予定/オープン予定」があれば false
 *
 * ※ バイク専用の絞り込み導線は無い（屋内/屋外バイクのタブは全店舗共通の静的ナビで、
 *    その店舗がバイク区画を持つ根拠にならない）。よって全店舗を取得し garage_type で分類する。
 */
final class StorageOhScraper extends AbstractRentalGarageScraper
{
    private const SITEMAP_URL = 'https://www.storageoh.jp/server-sitemap/index.xml';
    private const OPERATOR = 'ストレージ王';

    // JSON-LD geo 採用時の妥当性チェック（日本のおおよその緯度経度範囲）。
    private const JP_LAT_MIN = 20.0;
    private const JP_LAT_MAX = 46.0;
    private const JP_LNG_MIN = 122.0;
    private const JP_LNG_MAX = 154.0;

    public function key(): string
    {
        return 'storageoh';
    }

    public function label(): string
    {
        return self::OPERATOR;
    }

    public function fetch(?int $limit = null): iterable
    {
        $sitemap = $this->get(self::SITEMAP_URL);
        if ($sitemap === null) {
            return; // サイトマップが取れなければ何も出さない
        }

        // サイトマップから店舗詳細URL（/search/detail/{id}）のみ抽出。
        preg_match_all('#<loc>(https://www\.storageoh\.jp/search/detail/[^<]+)</loc>#', $sitemap, $m);
        $urls = array_values(array_unique($m[1]));

        $emitted = 0;
        foreach ($urls as $url) {
            $this->throttle(); // 1リクエスト1秒以上
            $html = $this->get($url);
            if ($html === null) {
                continue; // この店舗は飛ばす（他は続行）
            }

            $row = $this->parseDetail($html, $url);
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

    /**
     * 店舗詳細ページ（JSON-LD + 本文）を1件の配列にする。取れなければ null。
     *
     * @return array<string, mixed>|null
     */
    private function parseDetail(string $html, string $url): ?array
    {
        $crawler = new Crawler($html);

        $data = $this->extractSelfStorageJsonLd($crawler);
        if ($data === null) {
            return null;
        }

        $name = $this->ensureOperatorPrefix($this->normalizeText((string) ($data['name'] ?? '')), self::OPERATOR);
        $sourceUrl = $this->normalizeUrl((string) ($data['url'] ?? $url));
        if ($name === '' || $sourceUrl === '') {
            return null;
        }

        // 住所は JSON-LD の構造化フィールドから組み立てる（「ナビ用住所」は別物なので使わない）。
        $addr = is_array($data['address'] ?? null) ? $data['address'] : [];
        $region = $this->normalizeText((string) ($addr['addressRegion'] ?? ''));
        $locality = $this->normalizeText((string) ($addr['addressLocality'] ?? ''));
        $street = $this->normalizeText((string) ($addr['streetAddress'] ?? ''));
        $rawAddress = $this->normalizeText($region.$locality.$street);
        $cleanAddress = $this->normalizeAddress($rawAddress);

        $bodyText = $crawler->filter('body')->count() > 0
            ? $this->normalizeText($crawler->filter('body')->text(''))
            : '';

        // === バイク区画の抽出（このスクレイパーはバイク区画のある店舗のみ対象） ===
        // 料金表の区画タイプラベル（ShopDetailContents_size__*）に「バイク」を含めばバイク区画あり。
        // 施設全体の型ではなく「バイク区画の型」を採る（施設が屋外でもバイク区画が屋内なら indoor）。
        $hasIndoorBike = false;
        $hasOutdoorBike = false;
        $crawler->filter('[class*="ShopDetailContents_size__"]')->each(function (Crawler $n) use (&$hasIndoorBike, &$hasOutdoorBike): void {
            $t = $n->text('');
            if (mb_strpos($t, 'バイク') === false) {
                return;
            }
            if (mb_strpos($t, '屋外') !== false) {
                $hasOutdoorBike = true;
            } else {
                // 「屋外」明記が無いバイクラベル（屋内バイク／月極バイク駐車場等）は屋内扱い。
                $hasIndoorBike = true;
            }
        });
        if (! $hasIndoorBike && ! $hasOutdoorBike) {
            return null; // バイク区画なし＝対象外（yield しない）
        }

        // バイク区画の寸法・料金を、空白を除いた圧縮テキストから行単位で抽出。
        // 例:「屋内バイク１B1階幅1.22m×奥行2.40m×高さ1.88m月額賃料（税込）11,000円」
        $compact = preg_replace('/\s+/u', '', $bodyText) ?? $bodyText;
        preg_match_all(
            '/屋(?:内|外)バイク[^幅]{0,40}幅([0-9.]+)m×奥行([0-9.]+)m×高さ([0-9.]+)m月額賃料（税込）([0-9,]+)円/u',
            $compact,
            $units,
            PREG_SET_ORDER
        );

        // garage_type: バイク区画の型で判定（屋外優先）。読めなければ施設の型→other にフォールバック。
        if ($hasOutdoorBike) {
            $garageType = 'container';
        } elseif ($hasIndoorBike) {
            $garageType = 'indoor';
        } elseif (str_contains($bodyText, '屋外型')) {
            $garageType = 'container';
        } elseif (str_contains($bodyText, '屋内型')) {
            $garageType = 'indoor';
        } else {
            $garageType = 'other';
        }

        // size_text: バイク区画の寸法を優先。無ければ施設のサイズ表記「サイズ …帖」。
        $sizeText = null;
        if ($units !== []) {
            $sizeText = sprintf('幅%sm×奥行%sm×高さ%sm', $units[0][1], $units[0][2], $units[0][3]);
        } elseif (preg_match('/サイズ\s*([0-9.〜～\-]+帖)/u', $bodyText, $mm)) {
            $sizeText = $mm[1];
        }

        // monthly_fee: バイク区画の料金を優先。無ければ施設全体の priceRange。
        if ($units !== []) {
            $prices = array_map(static fn (array $u): int => (int) str_replace(',', '', $u[4]), $units);
            $feeMin = min($prices);
            $feeMax = max($prices);
        } else {
            [$feeMin, $feeMax] = $this->parsePriceRange((string) ($data['priceRange'] ?? ''));
        }

        // is_24h: 「24時間利用 可能/不可」。記載が無ければ null（false と区別）。
        $is24h = null;
        if (preg_match('/24時間利用\s*(可能|不可)/u', $bodyText, $mm)) {
            $is24h = $mm[1] === '可能';
        }

        // has_security: 「防犯カメラ 設置/なし」。記載が無ければ null。
        $hasSecurity = null;
        if (preg_match('/防犯カメラ\s*(設置|なし|無し)/u', $bodyText, $mm)) {
            $hasSecurity = $mm[1] === '設置';
        }

        // 電源・シャッターに対応する明示項目が無いため null 固定。
        $hasPower = null;
        $hasShutter = null;

        // is_active: 「OPEN予定/オープン予定」があれば開店前=非公開。
        $isActive = ! (bool) preg_match('/(OPEN|オープン)\s*予定/u', $bodyText);

        // description: アクセス説明 + 除去が起きた場合の所在地原文。
        $descParts = [];
        $access = $this->normalizeText((string) ($data['description'] ?? ''));
        if ($access !== '') {
            $descParts[] = $access;
        }
        if ($cleanAddress !== $rawAddress && $rawAddress !== '') {
            $descParts[] = '所在地表記: '.$rawAddress;
        }
        $description = $descParts !== [] ? implode("\n", $descParts) : null;

        // JSON-LD geo（権威データ）を採用。日本範囲内なら座標＋geocode_status='source' を付与し、
        // 以降のジオコーディング（GSI 等）の対象外にする（代表点丸め問題を回避）。
        $geoFields = [];
        $geo = $data['geo'] ?? null;
        if (is_array($geo) && isset($geo['latitude'], $geo['longitude'])) {
            $glat = (float) $geo['latitude'];
            $glng = (float) $geo['longitude'];
            if ($glat >= self::JP_LAT_MIN && $glat <= self::JP_LAT_MAX && $glng >= self::JP_LNG_MIN && $glng <= self::JP_LNG_MAX) {
                $geoFields = [
                    'latitude' => $glat,
                    'longitude' => $glng,
                    'geocode_status' => 'source',
                ];
            }
        }

        return [
            'name' => $name,
            'operator' => self::OPERATOR,
            'garage_type' => $garageType,
            'address' => $cleanAddress,
            // prefecture/city は JSON-LD を優先し、無ければ住所から推定。
            'prefecture' => $region !== '' ? $region : ($cleanAddress !== '' ? $this->extractPrefecture($cleanAddress) : null),
            'city' => $locality !== '' ? $locality : ($cleanAddress !== '' ? $this->extractCity($cleanAddress) : null),
            'monthly_fee_min' => $feeMin,
            'monthly_fee_max' => $feeMax,
            'size_text' => $sizeText,
            'is_24h' => $is24h,
            'has_power' => $hasPower,
            'has_security' => $hasSecurity,
            'has_shutter' => $hasShutter,
            'website_url' => $sourceUrl,
            'source_url' => $sourceUrl,
            'description' => $description,
            'is_active' => $isActive,
        ] + $geoFields;
    }

    /**
     * ページ内の JSON-LD から SelfStorage（address を持つオブジェクト）を1つ返す。無ければ null。
     *
     * @return array<string, mixed>|null
     */
    private function extractSelfStorageJsonLd(Crawler $crawler): ?array
    {
        $scripts = $crawler->filter('script[type="application/ld+json"]');
        if ($scripts->count() === 0) {
            return null;
        }

        $found = null;
        $scripts->each(function (Crawler $node) use (&$found): void {
            if ($found !== null) {
                return;
            }
            $decoded = json_decode($node->text(''), true);
            if (is_array($decoded) && (($decoded['@type'] ?? '') === 'SelfStorage' || isset($decoded['address']))) {
                $found = $decoded;
            }
        });

        return $found;
    }

    /**
     * priceRange 文字列（例「8250 - 30800」）から [min, max] を取り出す。
     *
     * @return array{0: int|null, 1: int|null}
     */
    private function parsePriceRange(string $priceRange): array
    {
        preg_match_all('/[0-9][0-9,]*/u', $priceRange, $m);
        $nums = array_map(static fn (string $n): int => (int) str_replace(',', '', $n), $m[0]);

        if (count($nums) === 0) {
            return [null, null];
        }
        if (count($nums) === 1) {
            return [$nums[0], null];
        }

        return [$nums[0], $nums[1]];
    }
}
