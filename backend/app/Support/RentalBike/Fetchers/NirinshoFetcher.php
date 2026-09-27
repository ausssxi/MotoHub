<?php

declare(strict_types=1);

namespace App\Support\RentalBike\Fetchers;

/**
 * 二輪処グループ（bike-rental.jp）。大阪中心の独立系レンタルチェーン（8店前後）。
 *
 * 判定: docs/plans/rental-bike-provider-checklist.md = OK（robots.txt は /wp-admin/ のみ Disallow・
 *   店舗一覧は対象外・利用規約はプライバシーのみで再利用制限なし・819/モトオーク非提携）。
 *
 * 構造（2026-09 時点で本番HTMLを確認・全店が1ページ /store/ に載る＝詳細ページ不要）:
 *   <article class="m-store">
 *     <div class="anc" id="storeNN"></div>
 *     <section><div class="store_content">
 *       <div class="thumb"><img ...></div>                       ← 画像は扱わない
 *       <div class="text"><h2>店名</h2>
 *         <table>
 *           <tr><th>住所</th><td>〒NNN-NNNN<br>住所</td></tr>      ← 都道府県が付かない店もある
 *           <tr><th>電話番号</th><td>070-… レンタル 06-… 修理・販売</td></tr> ← ★複数番号に役割ラベル
 *           <tr><th>営業時間</th><td>…</td></tr>
 *           <tr><th>定休日</th><td>…</td></tr>
 *         </table>
 *   ★店名は <h2>。事実情報はテーブルの <th> ラベルで <td> を引く。
 *   ★電話は「レンタル/予約」とラベルされた番号を優先（修理・販売の番号を拾わない）。無ければ最初の番号。
 *   ★住所は 〒 を落とし内部空白を除去。都道府県が無くても AddressParser が市区町村→都道府県を解決する。
 *   external_id は店舗アンカー（storeNN）、official_url は一覧ページの当該アンカー。
 *
 * ★リクエストは一覧1回のみ（詳細ページを開かない）。
 * ★img は解析対象にしない（写真・ロゴ・紹介文・料金・車種・在庫は扱わない）。返す配列に画像キーを持たせない。
 */
final class NirinshoFetcher extends AbstractFetcher
{
    /** 事業者サイト（トップ）。 */
    private const SITE = 'https://www.bike-rental.jp/';

    /** 店舗一覧（全店が1ページ）。 */
    private const LIST_URL = 'https://www.bike-rental.jp/store/';

    public function slug(): string
    {
        return 'nirinsho';
    }

    public function company(): string
    {
        return '二輪処グループ';
    }

    public function officialUrl(): string
    {
        return self::SITE;
    }

    /** ★日本語で要求する（チェックリスト手順）。 */
    protected function acceptLanguage(): ?string
    {
        return 'ja-JP,ja;q=0.9';
    }

    public function fetch(): array
    {
        $list = $this->get(self::LIST_URL);
        if ($list === null) {
            return [];
        }

        $records = [];
        foreach ($this->extractShops($list) as $shop) {
            $record = $this->buildRecord($shop);
            if ($record !== null) {
                $records[] = $record;
            }
        }

        return $records;
    }

    /**
     * 一覧HTMLから店舗（アンカーid・店名・住所td・電話td・営業時間）を取り出す（テスト用に公開）。
     * 店舗アンカー <div class="anc" id="storeNN"> ごとに区切り、直後の <h2> と <table> から拾う。
     *
     * @return array<int, array{id: string, name: string, address_td: string, tel_td: string, hours: string, url: string}>
     */
    public function extractShops(string $html): array
    {
        $parts = preg_split('~<div class="anc" id="(store\d+)"~', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return [];
        }

        $shops = [];
        $seen = [];
        // $parts = [先頭, id1, block1, id2, block2, ...]
        for ($i = 1; $i + 1 < count($parts); $i += 2) {
            $id = $parts[$i];
            $block = $parts[$i + 1];
            if (isset($seen[$id])) {
                continue;
            }

            if (preg_match('~<h2[^>]*>(.*?)</h2>~is', $block, $hm) !== 1) {
                continue; // 店舗でない（h2 が無い）
            }
            $name = trim((string) preg_replace('/[\s\x{3000}]+/u', ' ', html_entity_decode(strip_tags($hm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            if ($name === '') {
                continue;
            }

            $seen[$id] = true;
            $shops[] = [
                'id' => $id,
                'name' => $name,
                'address_td' => $this->tdByLabel($block, '住所'),
                'tel_td' => $this->tdByLabel($block, '電話番号'),
                'hours' => $this->tdByLabel($block, '営業時間'),
                'url' => self::LIST_URL.'#'.$id,
            ];
        }

        return $shops;
    }

    /**
     * 店舗1件をレコードに組み立てる（テスト用に公開）。住所を正規化し、市区町村が取れなければ null。
     * ★電話はレンタル/予約の番号を優先して選び、ハイフン正規化する。
     *
     * @param  array{id: string, name: string, address_td?: string, tel_td?: string, hours?: string, url?: string}  $shop
     * @return array<string, mixed>|null
     */
    public function buildRecord(array $shop): ?array
    {
        $addressField = (string) ($shop['address_td'] ?? '');
        $postal = preg_match('/〒?\s*(\d{3}-?\d{4})/u', $addressField, $pm) === 1 ? $pm[1] : null;
        // 〒 を落とし、内部空白を除去（都道府県は付かなくても AddressParser が解決する）。
        $address = (string) preg_replace('/[\s\x{3000}]+/u', '', (string) preg_replace('/^〒?\s*\d{3}-?\d{4}/u', '', trim($addressField)));

        if ($address === '' || $this->splitAddress($address)['city'] === null) {
            return null;
        }

        $tel = $this->pickRentalTel((string) ($shop['tel_td'] ?? ''));
        $hours = trim((string) ($shop['hours'] ?? ''));

        return $this->makeRecord(
            name: $shop['name'],
            address: $address,
            postalCode: $postal,
            tel: $tel,
            openingHours: $hours !== '' ? mb_substr($hours, 0, 100) : null,
            externalId: $shop['id'] ?? null,
            officialUrl: $shop['url'] ?? null,
        );
    }

    /** <th>{ラベル}</th><td>値</td> の td テキストを取る（<br> は空白、タグ除去）。無ければ ''。 */
    private function tdByLabel(string $html, string $label): string
    {
        if (preg_match('~<th[^>]*>\s*'.preg_quote($label, '~').'\s*</th>\s*<td[^>]*>(.*?)</td>~is', $html, $m) !== 1) {
            return '';
        }
        $inner = (string) preg_replace('~<br\s*/?>~i', ' ', $m[1]);
        $text = html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/[\t\x{3000} ]+/u', ' ', $text));
    }

    /**
     * 電話番号 td から「レンタル/予約」とラベルされた番号を優先して1つ選び、ハイフン正規化する。
     * 例「070-… レンタル 06-… 修理・販売」→ レンタルの 070-…。ラベルが無ければ最初の番号。無ければ null。
     */
    private function pickRentalTel(string $td): ?string
    {
        if (preg_match('/(0[\d\-ー－]{8,13})\s*(?:レンタル|予約)/u', $td, $m) === 1
            || preg_match('/(0[\d\-ー－]{8,13})/u', $td, $m) === 1) {
            return $this->hyphenateJpTel($m[1]);
        }

        return null;
    }
}
