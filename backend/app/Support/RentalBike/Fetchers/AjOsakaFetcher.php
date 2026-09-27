<?php

declare(strict_types=1);

namespace App\Support\RentalBike\Fetchers;

/**
 * 大阪オートバイ事業協同組合（aj-rentalbike.com）。大阪中心の独立系加盟店（22店前後）。
 *
 * 判定: docs/plans/rental-bike-provider-checklist.md = OK（robots.txt 無し=Disallow無し・
 *   利用規約なし=再利用制限の文言なし・レンタル819/モトオーク非提携で既存データと重複なし）。
 *
 * 構造（2026-09 時点で本番HTMLを確認・全店が1ページ /tenpo.php に載る＝詳細ページ不要）:
 *   <div class='boxs'>
 *     <div class='sn'>店名</div>
 *     <a href='shop_bike.php?shop=s_XXXX'><img ...></a>          ← 画像は扱わない
 *     <ul><div id='jyouhou_box'>
 *       <li class='b1'>〒NNN-NNNN</li>
 *       <li>住所</li>                                            ← ★〒直後の<li>が住所
 *       <li class='b1'>電話：0X-XXXX-XXXX</li>
 *       <li>定休日：…</li>                                       ← 営業時間は無い→ opening_hours は null
 *     </div>…</div>
 *   </div>
 *   ★住所は 〒 の <li> の直後の <li> を取る（都道府県の有無に依存しない。伊丹本店等は都道府県が付かず
 *     「伊丹市…」で始まる）。元データに「大阪府豊中市大阪府豊中市…」「…東大阪市東大阪市…」の重複バグが
 *     あるので、隣接重複した市区町村チャンクを畳む。改行も空白へ寄せる。
 *   ★外部リンク（店舗ホームページ）はスキーム無し・欠損・表記ゆれがあるため official_url には使わず、
 *     組合の店舗ページ（shop_bike.php?shop=ID）を official_url にする。
 *
 * ★リクエストは一覧1回のみ（詳細ページを開かない）。
 * ★img は解析対象にしない（写真・ロゴ・紹介文・料金・車種・在庫は扱わない）。返す配列に画像キーを持たせない。
 */
final class AjOsakaFetcher extends AbstractFetcher
{
    /** 組合サイト（トップ）。 */
    private const SITE = 'https://aj-rentalbike.com/';

    /** レンタル取り扱い加盟店の一覧（全店が1ページ）。 */
    private const LIST_URL = 'https://aj-rentalbike.com/tenpo.php';

    public function slug(): string
    {
        return 'aj-osaka';
    }

    public function company(): string
    {
        return '大阪オートバイ事業協同組合';
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
     * 一覧HTMLから加盟店（店名・shop id・〒・住所・電話）を取り出す（テスト用に公開）。
     * 各店は <div class='boxs'> ブロック。店名は <div class='sn'>、事実情報は #jyouhou_box の <li> 群。
     *
     * @return array<int, array{id: string, name: string, postal: ?string, address: string, tel: ?string, url: string}>
     */
    public function extractShops(string $html): array
    {
        // <div class='boxs'> 単位に切り出す（次の boxs か末尾まで）。
        $blocks = preg_split("~<div class='boxs'>~", $html);
        if ($blocks === false) {
            return [];
        }

        $shops = [];
        $seen = [];
        foreach ($blocks as $b) {
            if (preg_match("~shop_bike\.php\?shop=([A-Za-z0-9_]+)~", $b, $idm) !== 1) {
                continue; // 店舗ブロックでない（ヘッダ等）
            }
            $id = $idm[1];
            if (isset($seen[$id])) {
                continue;
            }

            if (preg_match("~<div class='sn'>(.*?)</div>~is", $b, $nm) !== 1) {
                continue;
            }
            $name = trim(html_entity_decode(strip_tags($nm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $name = trim((string) preg_replace('/[\s\x{3000}]+/u', ' ', $name));
            if ($name === '') {
                continue;
            }

            $postal = preg_match('/〒\s*(\d{3}-?\d{4})/u', $b, $pm) === 1 ? $pm[1] : null;

            // ★住所＝〒の <li> の直後の <li>（都道府県の有無に依存しない）。
            $address = '';
            if (preg_match('~〒[^<]*</li>\s*<li[^>]*>(.*?)</li>~is', $b, $am) === 1) {
                $address = trim(html_entity_decode(strip_tags($am[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            }

            $tel = preg_match('/電話[：:]\s*([\d\-ー－]+)/u', $b, $tm) === 1 ? $tm[1] : null;

            $seen[$id] = true;
            $shops[] = [
                'id' => $id,
                'name' => $name,
                'postal' => $postal,
                'address' => $address,
                'tel' => $tel,
                'url' => self::SITE.'shop_bike.php?shop='.$id,
            ];
        }

        return $shops;
    }

    /**
     * 加盟店1件をレコードに組み立てる（テスト用に公開）。住所を正規化し、市区町村が取れなければ null。
     * ★営業時間は一覧に無いので常に null（定休日は opening_hours ではない）。
     *
     * @param  array{id: string, name: string, postal?: ?string, address?: string, tel?: ?string, url?: string}  $shop
     * @return array<string, mixed>|null
     */
    public function buildRecord(array $shop): ?array
    {
        $address = $this->normalizeAddress((string) ($shop['address'] ?? ''));
        if ($address === '' || $this->splitAddress($address)['city'] === null) {
            return null; // 市区町村すら取れない＝地図/エリアに出せない
        }

        return $this->makeRecord(
            name: $shop['name'],
            address: $address,
            postalCode: $shop['postal'] ?? null,
            tel: ($shop['tel'] ?? null) !== null ? $this->formatJpTel((string) $shop['tel']) : null,
            openingHours: null,
            externalId: $shop['id'] ?? null,
            officialUrl: $shop['url'] ?? null,
        );
    }

    /**
     * 電話番号をハイフン付きに正規化する。共通の cleanTel（全角→半角・数字とハイフンのみ）を通したうえで、
     * ハイフン無しの数字列だけ日本の桁構成でハイフンを挿入する（元からハイフンがあれば元の区切りを尊重）。
     * Rental819Fetcher::formatJpTel（携帯 3-4-4 のみ）を、市外局番にも対応するよう拡張したもの。
     * ★市外局番は 03/06 を2桁、050/0120/0800/携帯を既知区切り、それ以外の10桁は3桁市外局番(3-3-4)として扱う。
     *   4桁市外局番の地域（0942 等）は完全一致しないが、AJ の掲載範囲（06/072/075/078/079/092）には出ない。
     */
    private function formatJpTel(string $raw): ?string
    {
        $t = $this->cleanTel($raw);
        if ($t === '') {
            return null;
        }
        if (str_contains($t, '-')) {
            return $t; // 元からハイフンがある＝元データの区切りを尊重する
        }

        $d = $t; // ここは数字だけ
        if (preg_match('/^0[789]0\d{8}$/', $d) === 1 || preg_match('/^050\d{8}$/', $d) === 1) {
            return substr($d, 0, 3).'-'.substr($d, 3, 4).'-'.substr($d, 7, 4); // 携帯・IP = 3-4-4
        }
        if (preg_match('/^0120(\d{3})(\d{3})$/', $d, $m) === 1) {
            return '0120-'.$m[1].'-'.$m[2];
        }
        if (preg_match('/^0800(\d{3})(\d{4})$/', $d, $m) === 1) {
            return '0800-'.$m[1].'-'.$m[2];
        }
        if (strlen($d) === 10) {
            return preg_match('/^0[36]/', $d) === 1
                ? substr($d, 0, 2).'-'.substr($d, 2, 4).'-'.substr($d, 6, 4)  // 03/06 = 2-4-4
                : substr($d, 0, 3).'-'.substr($d, 3, 3).'-'.substr($d, 6, 4); // 3桁市外局番 = 3-3-4
        }

        return $d; // 桁が読めないものは数字のまま
    }

    /**
     * 住所を正規化する。改行/空白を1つに寄せ、隣接して重複した市区町村チャンク
     * （「大阪府豊中市大阪府豊中市」「東大阪市東大阪市」等の元データのバグ）を畳む。
     */
    private function normalizeAddress(string $raw): string
    {
        $a = trim((string) preg_replace('/[\s\x{3000}]+/u', '', html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        // 隣接重複した「…市/区/町/村/郡」チャンクを1つに畳む（2〜12文字の非空白チャンク）。
        return (string) preg_replace('/(\S{2,12}?[市区町村郡])\1/u', '$1', $a);
    }
}
