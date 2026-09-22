<?php

declare(strict_types=1);

namespace App\Support\RentalBike\Fetchers;

/**
 * レンタル819（rental819.com）。国内最大規模・全国展開。
 *
 * 構造（2026-09 時点で本番HTMLを確認。robots.txt は無し＝Disallow無し）:
 *   - ★接続先は canonical な https://rental819.com/（non-www）。www は non-www へ 301（AbstractFetcher が追従）。
 *   - 一覧 /store/ の1ページに全国 約108店舗（ページ送り無し・地域別）。各店舗は
 *       <a href="/store/{id}">
 *         <span class="p-store-list__store-name"><i class="las ..."></i>店名</span>
 *         <span class="p-store-list__store-adress">市区町村＋番地（都道府県は省略）</span>
 *       </a>
 *     ★店名は name span のテキスト（内側の <i> アイコンをタグ除去で落とす）。
 *     ★一覧の住所は都道府県が省略されるので使わない → 都道府県込みの住所は詳細から取る。
 *   - 詳細 /store/{id} に 〒・住所（都道府県込み）・電話・営業時間 がある。official_url は詳細ページURL。
 *
 * ★リクエストは「一覧1回 ＋ 店舗詳細 約108回」。1リクエストごとに2秒待つ・並列にしない・同じページを取り直さない。
 * ★img は解析対象にしない（写真・ロゴ・紹介文・料金・車種・在庫は扱わない）。返す配列に画像キーを持たせない。
 */
final class Rental819Fetcher extends AbstractFetcher
{
    /** 事業者の公式サイト（トップ・canonical=non-www）。 */
    private const SITE = 'https://rental819.com/';

    /** 店舗一覧（全国が1ページ・ページ送り無し）。 */
    private const LIST_URL = 'https://rental819.com/store/';

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
        foreach ($this->extractStores($list) as $store) {
            $this->pause(); // ★店舗詳細を1つ開くごとに待つ
            $body = $this->get($store['url']);
            if ($body === null) {
                continue;
            }
            $record = $this->buildRecord($store, $body);
            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * 一覧HTMLから店舗（店名＋詳細URL＋ID）を重複無しで取り出す（テスト用に公開）。
     * 店名は span.p-store-list__store-name のテキスト（内側の <i> アイコン等はタグ除去で落とす）。
     * name span を持たない /store/ リンク（ナビ・フッター等）は店舗ではないので除外する。
     *
     * @return array<int, array{id: string, url: string, name: string}>
     */
    public function extractStores(string $html): array
    {
        if (preg_match_all('~<a\b[^>]*href="/store/(\d+)"[^>]*>(.*?)</a>~is', $html, $anchors, PREG_SET_ORDER) === false) {
            return [];
        }

        $stores = [];
        $seen = [];
        foreach ($anchors as $a) {
            $id = $a[1];
            if (isset($seen[$id])) {
                continue; // 同一店舗への複数リンクを潰す
            }
            if (preg_match('~<span[^>]*class="[^"]*p-store-list__store-name[^"]*"[^>]*>(.*?)</span>~is', $a[2], $nm) !== 1) {
                continue; // 店名 span を持たない /store/ リンクは店舗ではない
            }
            // ★内側の <i class="las ..."> アイコン等をタグ除去で落としてから店名を取る（アイコンが名前に混入するのを防ぐ）。
            $name = trim(html_entity_decode(strip_tags($nm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($name === '') {
                continue;
            }
            $seen[$id] = true;
            $stores[] = ['id' => $id, 'url' => self::SITE.'store/'.$id, 'name' => $name];
        }

        return $stores;
    }

    /**
     * 一覧の店舗（店名）と詳細HTML（〒・住所・電話・営業時間）から1レコードを組み立てる（テスト用に公開）。
     * ★住所は詳細ページから取る（一覧は都道府県が省略されているため）。都道府県込みの住所が取れなければ null。
     *
     * @param  array{id: string, url: string, name: string}  $store
     * @return array<string, mixed>|null
     */
    public function buildRecord(array $store, string $detailHtml): ?array
    {
        $text = $this->htmlToText($detailHtml);

        $address = $this->firstAddressLine($text);
        if ($address === null) {
            return null; // 都道府県が取れない＝地図/エリアに出せないのでレコードにしない
        }

        return $this->makeRecord(
            name: $store['name'],
            address: $address,
            postalCode: $this->extractPostal($text),
            tel: $this->extractTel($text),
            openingHours: $this->extractHours($text),
            externalId: $store['id'],
            officialUrl: $store['url'],
        );
    }

    /** 詳細テキストから最初の「住所らしい行」（都道府県で始まる行＝AddressParser で都道府県が取れる行）を返す。 */
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
