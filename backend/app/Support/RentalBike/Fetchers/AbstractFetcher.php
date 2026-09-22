<?php

declare(strict_types=1);

namespace App\Support\RentalBike\Fetchers;

use App\Services\Parking\AddressParser;
use App\Support\RentalBike\ShopFetcher;
use Illuminate\Support\Facades\Http;

/**
 * 共通処理: 連絡先入りUAでの取得、リクエスト間の待機、HTML→テキスト整形、店舗レコード組み立て。
 * ★ 並列にしない・同じページを取り直さない・1リクエストごとに待つ（相手に負荷をかけない）。
 */
abstract class AbstractFetcher implements ShopFetcher
{
    /** ★匿名でクロールしない。相手が問い合わせできるよう連絡先を入れる。 */
    protected const USER_AGENT = 'MotoHub/1.0 (+https://motohub.jp; info@motohub.jp)';

    /** 1リクエストごとの待機秒数。 */
    protected const REQUEST_INTERVAL_SEC = 2;

    /**
     * URL を取得して本文を返す。失敗時は null。
     * ★リダイレクトを追従する（例: rental819 は www → non-www の 301。追従しないと一覧が取れない）。
     */
    protected function get(string $url): ?string
    {
        $res = Http::withHeaders(['User-Agent' => self::USER_AGENT])
            ->withOptions(['allow_redirects' => ['max' => 5, 'referer' => true]])
            ->timeout(20)
            ->get($url);

        return $res->successful() ? $res->body() : null;
    }

    /** リクエスト間の待機（並列にしない・連続で叩かない）。 */
    protected function pause(): void
    {
        sleep(self::REQUEST_INTERVAL_SEC);
    }

    /**
     * HTML をブロック境界で改行したプレーンテキストにする。
     * ★img は解析対象にしない（写真は扱わない）ため src 等は一切拾わない。
     */
    protected function htmlToText(string $html): string
    {
        // ブロック要素の閉じ・改行を \n に。
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html) ?? $html;
        $html = preg_replace('/<\/\s*(p|div|li|tr|td|th|h[1-6]|section|article|dt|dd)\s*>/i', "\n", $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // 各行を trim し、行内の空白を1つに。空行は残す（ブロック区切りとして使う）。
        $lines = array_map(
            static fn (string $l): string => trim((string) preg_replace('/[\t\x{3000} ]+/u', ' ', $l)),
            preg_split('/\n/u', $text) ?: []
        );

        return implode("\n", $lines);
    }

    /** 電話番号の整形（数字とハイフンのみ・全角→半角）。 */
    protected function cleanTel(string $raw): string
    {
        $s = mb_convert_kana(trim($raw), 'n'); // 全角数字→半角
        $s = str_replace(['－', 'ー', '(', ')', '（', '）', ' '], ['-', '-', '-', '', '-', '', ''], $s);

        return trim((string) preg_replace('/[^\d\-]/', '', $s), '-');
    }

    /** 郵便番号の整形（NNN-NNNN）。取れなければ null。 */
    protected function cleanPostal(?string $raw): ?string
    {
        if ($raw === null) {
            return null;
        }
        $s = mb_convert_kana($raw, 'n');
        if (preg_match('/(\d{3})[-ー－]?(\d{4})/u', $s, $m) === 1) {
            return $m[1].'-'.$m[2];
        }

        return null;
    }

    /**
     * 住所から都道府県・市区町村を切り出す。抽出は既存の App\Services\Parking\AddressParser に
     * 一本化している（駐車場/POI/ジオコーディングと同じ実装）。理由:
     *   - AddressFormatter は「都道府県・市区町村が既知の前提で町名だけを取り出す」役割で、
     *     生の住所からの都道府県/市区町村抽出は持たない（別の役割）。
     *   - 一方 AddressParser::parse() はまさに生住所→(prefecture, city) を返し、権威データ
     *     (municipalities.full_name) と特例（四日市市/市川市/大和郡山市 等）を扱う。ここで独自の
     *     分割器を持つと、いつか GSI ジオコーディング側と表記が食い違う。
     * AddressParser は '' を返すので、DB 保存に合わせて null へ寄せる。
     *
     * @return array{prefecture: ?string, city: ?string}
     */
    protected function splitAddress(string $address): array
    {
        $parsed = (new AddressParser)->parse($address);

        return [
            'prefecture' => $parsed['prefecture'] !== '' ? $parsed['prefecture'] : null,
            'city' => $parsed['city'] !== '' ? $parsed['city'] : null,
        ];
    }

    /**
     * 店舗レコードを組み立てる。都道府県・市区町村は住所から切り出す（AddressParser）。
     * ★画像キーは持たせない。
     *
     * @return array<string, mixed>
     */
    protected function makeRecord(
        string $name,
        string $address,
        ?string $postalCode = null,
        ?string $tel = null,
        ?string $openingHours = null,
        ?string $externalId = null,
        ?string $officialUrl = null,
    ): array {
        $split = $this->splitAddress($address);

        return [
            'external_id' => $externalId,
            'name' => trim($name),
            'postal_code' => $this->cleanPostal($postalCode),
            'address' => trim($address),
            'prefecture' => $split['prefecture'],
            'city' => $split['city'],
            'tel' => $tel !== null && $tel !== '' ? $tel : null,
            'opening_hours' => $openingHours !== null && trim($openingHours) !== '' ? trim($openingHours) : null,
            'official_url' => $officialUrl !== null && $officialUrl !== '' ? $officialUrl : $this->officialUrl(),
        ];
    }

    /**
     * 「店舗名 → 〒＋住所 → TEL」の並びから店舗を拾う汎用パーサ（住所行の直前を店舗名、直後付近をTELとみなす）。
     * 郵便番号の直後が都道府県で始まる（＝住所らしい）行だけを店舗とみなし、TEL等の数字列の誤検出を弾く。
     *
     * @return array<int, array<string, mixed>>
     */
    protected function parseNamePostalTel(string $html): array
    {
        $lines = array_values(array_filter(
            explode("\n", $this->htmlToText($html)),
            static fn (string $l): bool => $l !== '',
        ));

        $records = [];
        foreach ($lines as $i => $line) {
            if (preg_match('/〒?\s*(\d{3})[-ー－]?(\d{4})\s*(.+)$/u', $line, $m) !== 1) {
                continue;
            }
            $address = trim($m[3]);
            if ($this->splitAddress($address)['prefecture'] === null) {
                continue; // 住所らしくない（TEL/FAX の数字列など）は捨てる
            }

            $name = $this->findNameBefore($lines, $i);
            if ($name === null) {
                continue; // 店舗名が取れないものはレコードにしない
            }

            $records[] = $this->makeRecord(
                name: $name,
                address: $address,
                postalCode: $m[1].'-'.$m[2],
                tel: $this->findTelAfter($lines, $i),
            );
        }

        return $records;
    }

    /** 住所行の直前から、店舗名らしい行を1つ探す（TEL/FAX/〒/数字始まり/裸の都道府県見出しは除外）。 */
    private function findNameBefore(array $lines, int $index): ?string
    {
        for ($j = $index - 1; $j >= 0 && $j >= $index - 4; $j--) {
            $l = trim((string) $lines[$j]);
            if ($l === '' || preg_match('/^(?:TEL|Tel|ＴＥＬ|FAX|Fax|〒|\d)/u', $l) === 1) {
                continue;
            }
            $split = $this->splitAddress($l);
            if ($split['prefecture'] !== null && mb_strlen($l) <= 8) {
                continue; // 「千葉県」等の裸の見出し行
            }

            return $l;
        }

        return null;
    }

    /** 住所行の直後付近から TEL を探す。無ければ null。 */
    private function findTelAfter(array $lines, int $index): ?string
    {
        $count = count($lines);
        for ($j = $index; $j < $count && $j <= $index + 4; $j++) {
            if (preg_match('/(?:TEL|Tel|ＴＥＬ)[:：]?\s*([\d\-ー－()（）\s]{8,20})/u', (string) $lines[$j], $m) === 1) {
                return $this->cleanTel($m[1]);
            }
        }

        return null;
    }

    /** 相対URLを officialUrl のホスト基準で絶対URLにする。 */
    protected function absoluteUrl(string $href): string
    {
        if (preg_match('/^https?:\/\//i', $href) === 1) {
            return $href;
        }
        $base = rtrim($this->officialUrl(), '/');
        $parts = parse_url($base);
        $origin = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');

        return str_starts_with($href, '/') ? $origin.$href : $base.'/'.ltrim($href, '/');
    }
}
