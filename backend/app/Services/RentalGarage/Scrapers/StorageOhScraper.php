<?php

declare(strict_types=1);

namespace App\Services\RentalGarage\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

/**
 * ストレージ王（storageoh.jp）スクレイパー。
 *
 * robots.txt は Allow: / （全許可）。バイク設備のある店舗は店舗一覧
 * （/search/tenpo・242店舗・ページネーションなし）の各店舗カードのアイコンで判別する。
 * カード内の alt="屋内型バイク駐車場" / "屋外型バイク駐車場" が、その店舗のバイク設備を示す。
 *
 * ★経緯（重要）: 当初は「一覧の屋内/屋外バイクのアイコンは全店舗共通の静的ナビで、その店舗が
 *   バイク区画を持つ根拠にならない」と判断していたが、これは誤りだった。事業者（ストレージ王
 *   経営企画室 坂上様）への確認により、一覧の各店舗アイコンがその店舗のバイク設備を示すと
 *   判明した（2026-08-31）。よって一覧アイコンをバイク設備判定の正本とする。
 *   （凡例のアイコンは alt="" なので filter に掛からず自然に除外される。バイク設備あり＝108店舗：
 *    屋内のみ89 / 屋外のみ12 / 屋内屋外両方7。）
 *
 * garage_type は一覧アイコンで決める:
 *   屋内バイクのみ → indoor ／ 屋外バイクを含む（屋外のみ・屋内屋外両方）→ container
 *
 * 各店舗の残りの属性は詳細 /search/detail/{id} の JSON-LD(SelfStorage) + 本文から補完する
 * （2026-08 時点で確認・Next.js SSR / クラス名はハッシュ化）:
 *   - <script type="application/ld+json"> の SelfStorage:
 *       name / url / telephone / address(streetAddress,addressLocality,addressRegion,postalCode)
 *       / priceRange("8250 - 30800") / geo(lat,lng) / description(アクセス説明)
 *   - size_text/料金: 本文のバイク区画寸法（幅×奥行×高さ・月額賃料）を優先、無ければ施設の priceRange /「サイズ …帖」
 *   - is_24h: 本文「24時間利用 可能/不可」/ 記載なし→null
 *   - has_security: 本文「防犯カメラ 設置/なし」/ 記載なし→null
 *   - is_active: 本文に「OPEN予定/オープン予定」があれば false
 *
 * upsert キーは source_url（= JSON-LD の url = https://www.storageoh.jp/search/detail/{id}）。既存レコードと一致する。
 */
final class StorageOhScraper extends AbstractRentalGarageScraper
{
    private const BASE_URL = 'https://www.storageoh.jp';

    private const LIST_URL = 'https://www.storageoh.jp/search/tenpo';

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
        $listHtml = $this->get(self::LIST_URL);
        if ($listHtml === null) {
            return; // 一覧が取れなければ何も出さない
        }

        // 一覧のアイコンで「バイク設備あり」の店舗だけを抽出し、garage_type も一覧で確定させる。
        $targets = $this->parseList($listHtml);

        $emitted = 0;
        foreach ($targets as $target) {
            $this->throttle(); // 1リクエスト1秒以上
            $html = $this->get($target['url']);
            if ($html === null) {
                continue; // この店舗は飛ばす（他は続行）
            }

            // garage_type は一覧アイコンが正本。詳細は住所・座標・料金等の補完にのみ使う。
            $row = $this->parseDetail($html, $target['url'], $target['garage_type']);
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
     * 店舗一覧（/search/tenpo）から「バイク設備あり」の店舗だけを抽出する。
     *
     * 各店舗カード（ul.ShopContents_prefectureList__ > li）内のアイコン alt でバイク設備を判定する。
     * 事業者確認により、この各店舗アイコンがその店舗のバイク設備を示す（2026-08-31）。
     * 凡例のアイコンは alt="" のため img[alt="屋内型バイク駐車場"] 等に一致せず自然に除外される。
     *
     * @return list<array{url: string, garage_type: string}>
     */
    private function parseList(string $html): array
    {
        $crawler = new Crawler($html);
        $targets = [];
        $seen = [];

        $crawler->filter('ul[class*="ShopContents_prefectureList__"] > li')->each(
            function (Crawler $li) use (&$targets, &$seen): void {
                $hasIndoorBike = $li->filter('img[alt="屋内型バイク駐車場"]')->count() > 0;
                $hasOutdoorBike = $li->filter('img[alt="屋外型バイク駐車場"]')->count() > 0;
                if (! $hasIndoorBike && ! $hasOutdoorBike) {
                    return; // バイク設備なし＝対象外
                }

                $link = $li->filter('a[class*="ShopContents_shopName__"]');
                if ($link->count() === 0) {
                    return;
                }
                $href = (string) $link->attr('href');
                if (! preg_match('#^/search/detail/\d+#', $href)) {
                    return;
                }

                $url = $this->normalizeUrl(self::BASE_URL.$href);
                if (isset($seen[$url])) {
                    return; // 同一店舗の重複カードは1回だけ
                }
                $seen[$url] = true;

                $targets[] = [
                    'url' => $url,
                    // 屋外バイクを含む（屋外のみ・屋内屋外両方）→ container ／ 屋内のみ → indoor。
                    'garage_type' => $hasOutdoorBike ? 'container' : 'indoor',
                ];
            }
        );

        return $targets;
    }

    /**
     * 店舗詳細ページ（JSON-LD + 本文）を1件の配列にする。取れなければ null。
     *
     * バイク設備の有無と garage_type は一覧アイコンで確定済み（$garageType）。ここでは
     * 住所・座標・料金・サイズ等の属性を補完するだけで、バイク区画の有無で足切りはしない。
     *
     * @param  string  $garageType  一覧アイコンから確定した 'indoor'|'container'
     * @return array<string, mixed>|null
     */
    private function parseDetail(string $html, string $url, string $garageType): ?array
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

        // バイク区画の寸法・料金を、空白を除いた圧縮テキストから行単位で抽出（size_text/料金の補完用）。
        // 例:「屋内バイク１B1階幅1.22m×奥行2.40m×高さ1.88m月額賃料（税込）11,000円」
        // ※ バイク設備の有無・型は一覧アイコンで確定済み（$garageType）。ここは属性補完のみ。
        $compact = preg_replace('/\s+/u', '', $bodyText) ?? $bodyText;
        preg_match_all(
            '/屋(?:内|外)バイク[^幅]{0,40}幅([0-9.]+)m×奥行([0-9.]+)m×高さ([0-9.]+)m月額賃料（税込）([0-9,]+)円/u',
            $compact,
            $units,
            PREG_SET_ORDER
        );

        // size_text: バイク区画の寸法を優先。無ければ施設のサイズ表記「サイズ …帖」。
        // ※ 一部店舗は高さ（まれに幅/奥行も）を 0.00m で掲載している（＝未計測/上限なし）。
        //   0 や取得不可の寸法は size_text に載せない（「高さ0.00m」の誤表示を防ぐ）。
        //   3辺すべてが 0/取得不可なら寸法としては空 → null（帖のフォールバックには回さない）。
        $sizeText = null;
        if ($units !== []) {
            $sizeText = $this->formatBikeDimensions($units[0][1], $units[0][2], $units[0][3]);
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
     * バイク区画の寸法（幅・奥行・高さ）を「幅Xm×奥行Ym×高さZm」形式に整形する。
     * 0 または空（取得不可）の辺は省く。全辺が省かれた場合は null を返す（空文字にはしない）。
     *
     * ※ 一部のストレージ王店舗は高さ（まれに幅/奥行も）を 0.00m で掲載しており、そのまま出すと
     *   画面に「高さ0.00m」と実在しない寸法が出てしまうため、辺ごとに 0 を除外する。
     */
    private function formatBikeDimensions(string $width, string $depth, string $height): ?string
    {
        $parts = [];
        foreach ([['幅', $width], ['奥行', $depth], ['高さ', $height]] as [$label, $value]) {
            if ($value !== '' && (float) $value > 0) {
                $parts[] = $label.$value.'m';
            }
        }

        return $parts !== [] ? implode('×', $parts) : null;
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
