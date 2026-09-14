<?php

declare(strict_types=1);

namespace App\Support\RentalBike\Fetchers;

/**
 * Motobase（motobase.jp）。東京都の拠点。
 *
 * 構造（2026-09 時点で実サイトを1回ずつ確認）:
 *   - ハブ /rental-bike/tokyo に「◯◯区(N拠点)」の区リンクが並ぶ（href=/rental-bike/tokyo/{slug}）。
 *   - 各区ページに拠点が <h3>拠点名</h3> で並び、「住所」ラベルの直後に住所、
 *     「詳細を見る」リンク href=/rental-bike/tokyo/{slug}/{loc} が拠点の公式詳細ページ。
 *   - 〒・TEL・営業時間は区ページには無い → null（スキーマ上 nullable）。
 *
 * ★リクエストは「ハブ1回 ＋ 区ページ数回」。1リクエストごとに2秒待つ・並列にしない。
 * ★img は解析対象にしない（写真・ロゴ・紹介文は扱わない）。
 */
final class MotobaseFetcher extends AbstractFetcher
{
    /** 事業者の公式サイト（トップ）。 */
    private const SITE = 'https://motobase.jp/';

    /** 東京都拠点のハブ（区リンクの一覧）。 */
    private const HUB_URL = 'https://motobase.jp/rental-bike/tokyo';

    public function slug(): string
    {
        return 'motobase';
    }

    public function company(): string
    {
        return 'Motobase';
    }

    public function officialUrl(): string
    {
        return self::SITE;
    }

    public function fetch(): array
    {
        $hub = $this->get(self::HUB_URL);
        if ($hub === null) {
            return [];
        }

        $records = [];
        foreach ($this->extractWardUrls($hub) as $wardUrl) {
            $this->pause(); // ★区ページを1つ開くごとに待つ
            $body = $this->get($wardUrl);
            if ($body === null) {
                continue;
            }
            foreach ($this->parseWard($body) as $record) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * ハブHTMLから区ページのURL（/rental-bike/tokyo/{一区画} のみ）を重複無しで取り出す。
     * ハブ自身（/rental-bike/tokyo）や拠点詳細（さらに1階層深い）は除く。
     *
     * @return array<int, string>
     */
    public function extractWardUrls(string $html): array
    {
        if (preg_match_all('/href="([^"]+)"/i', $html, $m) === false || empty($m[1])) {
            return [];
        }

        $urls = [];
        foreach ($m[1] as $href) {
            // 「/rental-bike/tokyo/{slug}」ちょうど1階層だけを対象にする。
            if (preg_match('~^(?:https?://[^/]+)?/rental-bike/tokyo/[^/?#]+/?$~i', $href) !== 1) {
                continue;
            }
            $abs = $this->absoluteUrl($href);
            $urls[rtrim($abs, '/')] = true; // 末尾スラッシュ差の重複を潰す
        }

        return array_keys($urls);
    }

    /**
     * 区ページHTMLから拠点を抽出（テスト用に公開）。
     * 各 <h3> を拠点の境界にし、そのブロック内の「住所」直後の住所行と「詳細を見る」リンクを拾う。
     *
     * @return array<int, array<string, mixed>>
     */
    public function parseWard(string $html): array
    {
        // <h3> の位置でブロック分割する（拠点名の境界）。
        if (preg_match_all('/<h3\b[^>]*>(.*?)<\/h3>/is', $html, $heads, PREG_OFFSET_CAPTURE) === false
            || empty($heads[0])
        ) {
            return [];
        }

        $records = [];
        $count = count($heads[0]);
        for ($i = 0; $i < $count; $i++) {
            $name = trim(html_entity_decode(strip_tags($heads[1][$i][0]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($name === '') {
                continue;
            }

            // このブロック = 現在の h3 開始位置〜次の h3 開始位置（無ければ末尾）。
            $start = (int) $heads[0][$i][1];
            $end = $i + 1 < $count ? (int) $heads[0][$i + 1][1] : mb_strlen($html, '8bit');
            $block = substr($html, $start, $end - $start);

            $address = $this->firstAddressLine($block);
            if ($address === null) {
                continue; // 住所が取れない拠点はレコードにしない
            }

            $records[] = $this->makeRecord(
                name: $name,
                address: $address,
                officialUrl: $this->detailUrl($block),
            );
        }

        return $records;
    }

    /** ブロック内テキストから最初の「住所らしい行」（都道府県で始まる行）を返す。無ければ null。 */
    private function firstAddressLine(string $blockHtml): ?string
    {
        foreach (explode("\n", $this->htmlToText($blockHtml)) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($this->splitAddress($line)['prefecture'] !== null) {
                return $line;
            }
        }

        return null;
    }

    /** ブロック内の「詳細を見る」リンク（無ければ最初の拠点詳細URL）を絶対URLで返す。無ければ null。 */
    private function detailUrl(string $blockHtml): ?string
    {
        if (preg_match('/href="([^"]+)"[^>]*>\s*詳細を見る/iu', $blockHtml, $m) === 1) {
            return $this->absoluteUrl($m[1]);
        }
        // フォールバック: /rental-bike/tokyo/{ward}/{loc} の2階層目リンク。
        if (preg_match('~href="((?:https?://[^/]+)?/rental-bike/tokyo/[^/"]+/[^/"?#]+)"~i', $blockHtml, $m) === 1) {
            return $this->absoluteUrl($m[1]);
        }

        return null;
    }
}
