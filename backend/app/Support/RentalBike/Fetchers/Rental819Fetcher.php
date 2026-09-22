<?php

declare(strict_types=1);

namespace App\Support\RentalBike\Fetchers;

/**
 * レンタル819（rental819.com）。国内最大規模・全国展開。
 *
 * 構造（2026-09 時点で確認。robots.txt は無し＝Disallow無し）:
 *   - 一覧 /store/ の1ページに全国 約103店舗が地域別に並ぶ（ページ送り無し）。
 *     各店舗は詳細ページ /store/{数値ID} へのリンクを持つ。一覧には店舗名・住所のみ（〒/TEL/営業時間は無い）。
 *   - 詳細 /store/{id} に 店舗名・〒・住所・電話・営業時間 がある。official_url は詳細ページURL。
 *
 * ★リクエストは「一覧1回 ＋ 店舗詳細 約103回」。1リクエストごとに2秒待つ・並列にしない・同じページを取り直さない。
 * ★img は解析対象にしない（写真・ロゴ・紹介文・料金・車種・在庫は扱わない）。
 * ★返す配列に画像キーを持たせない。
 *
 * NOTE: サイトは接続元の地域で言語が変わる（英語版が返る場合がある）。本番（日本）からは日本語HTMLが返る前提で、
 *   住所抽出は言語非依存の 〒/都道府県 判定（AddressParser）に寄せ、TEL は日本の電話番号パターンで拾う。
 */
final class Rental819Fetcher extends AbstractFetcher
{
    /** 事業者の公式サイト（トップ）。 */
    private const SITE = 'https://www.rental819.com/';

    /** 店舗一覧（全国が1ページ・ページ送り無し）。 */
    private const LIST_URL = 'https://www.rental819.com/store/';

    public function slug(): string
    {
        return 'rental819';
    }

    public function company(): string
    {
        return 'レンタル819';
    }

    public function officialUrl(): string
    {
        return self::SITE;
    }

    public function fetch(): array
    {
        $list = $this->get(self::LIST_URL);
        if ($list === null) {
            return [];
        }

        $records = [];
        foreach ($this->extractStoreUrls($list) as $url) {
            $this->pause(); // ★店舗詳細を1つ開くごとに待つ
            $body = $this->get($url);
            if ($body === null) {
                continue;
            }
            $record = $this->parseDetail($body, $url);
            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * 一覧HTMLから店舗詳細URL（/store/{数値ID} のみ）を重複無しで取り出す（テスト用に公開）。
     * 一覧トップ /store/ 自身（数値IDを持たない）は対象外。
     *
     * @return array<int, string>
     */
    public function extractStoreUrls(string $html): array
    {
        if (preg_match_all('~/store/(\d+)~', $html, $m) === false || empty($m[0])) {
            return [];
        }

        $urls = [];
        foreach ($m[1] as $id) {
            $abs = self::SITE.'store/'.$id;
            $urls[$abs] = true; // 重複（同一店舗への複数リンク）を潰す
        }

        return array_keys($urls);
    }

    /**
     * 店舗詳細HTMLから1店舗を組み立てる（テスト用に公開）。住所が取れない場合は null（地図・エリアに出せないため）。
     *
     * @return array<string, mixed>|null
     */
    public function parseDetail(string $html, string $url): ?array
    {
        $text = $this->htmlToText($html);

        $name = $this->extractName($html);
        $address = $this->firstAddressLine($text);
        if ($name === null || $address === null) {
            return null;
        }

        // external_id は URL の数値ID（company_slug と組で dedup_key の安定キーになる）。
        $externalId = preg_match('~/store/(\d+)~', $url, $mm) === 1 ? $mm[1] : null;

        return $this->makeRecord(
            name: $name,
            address: $address,
            postalCode: $this->extractPostal($text),
            tel: $this->extractTel($text),
            openingHours: $this->extractHours($text),
            externalId: $externalId,
            officialUrl: $url,
        );
    }

    /** 店舗名を <h1>、無ければ <title>（サイト名サフィックスを落とす）から取る。 */
    private function extractName(string $html): ?string
    {
        if (preg_match('/<h1\b[^>]*>(.*?)<\/h1>/is', $html, $m) === 1) {
            $name = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($name !== '') {
                return $name;
            }
        }
        if (preg_match('/<title\b[^>]*>(.*?)<\/title>/is', $html, $m) === 1) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            // 「店名｜レンタル819…」等のサフィックスを落とす（区切りは全角/半角の縦棒・ハイフン）。
            $title = (string) preg_split('/\s*[｜|\-–—]\s*/u', $title, 2)[0];
            $title = trim($title);
            if ($title !== '') {
                return $title;
            }
        }

        return null;
    }

    /** テキストから最初の「住所らしい行」（都道府県で始まる行＝AddressParser で都道府県が取れる行）を返す。 */
    private function firstAddressLine(string $text): ?string
    {
        foreach (explode("\n", $text) as $line) {
            // 行頭の 〒NNN-NNNN を落として住所本体だけにする（同一行に郵便番号が付くケース）。
            $line = trim((string) preg_replace('/^〒?\s*\d{3}[-ー－]?\d{4}\s*/u', '', trim($line)));
            if ($line === '') {
                continue;
            }
            if ($this->splitAddress($line)['prefecture'] !== null) {
                return $line;
            }
        }

        return null;
    }

    /** テキストから郵便番号（NNN-NNNN）を取る。無ければ null。 */
    private function extractPostal(string $text): ?string
    {
        if (preg_match('/〒?\s*(\d{3})[-ー－]?(\d{4})/u', $text, $m) === 1) {
            return $this->cleanPostal($m[1].'-'.$m[2]);
        }

        return null;
    }

    /** 電話番号を取る。ラベル（TEL/電話）優先、無ければ日本の電話番号パターンで拾う。無ければ null。 */
    private function extractTel(string $text): ?string
    {
        if (preg_match('/(?:TEL|Tel|ＴＥＬ|電話番号|電話|お問い合わせ)[:：]?\s*(0[\d\-ー－()（）\s]{7,18})/u', $text, $m) === 1) {
            $tel = $this->cleanTel($m[1]);

            return $tel !== '' ? $tel : null;
        }
        if (preg_match('/(0\d{1,3}[-ー－]\d{1,4}[-ー－]\d{3,4})/u', $text, $m) === 1) {
            $tel = $this->cleanTel($m[1]);

            return $tel !== '' ? $tel : null;
        }

        return null;
    }

    /** 営業時間を取る。「営業時間」ラベルのある行の以降を返す。無ければ null。 */
    private function extractHours(string $text): ?string
    {
        foreach (explode("\n", $text) as $line) {
            if (preg_match('/営業時間[:：]?\s*(.+)$/u', trim($line), $m) === 1) {
                $hours = trim($m[1]);
                if ($hours !== '') {
                    return mb_substr($hours, 0, 100); // 過剰な連結を避けて上限を切る
                }
            }
        }

        return null;
    }
}
