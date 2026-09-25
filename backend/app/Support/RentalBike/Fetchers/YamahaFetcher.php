<?php

declare(strict_types=1);

namespace App\Support\RentalBike\Fetchers;

/**
 * ヤマハ バイクレンタル（bike-rental.yamaha-motor.co.jp）。全国のYSP/直営店。
 *
 * 判定: docs/plans/rental-bike-provider-checklist.md「判定済み」= OK（2026-09-25・会員規約のみ）。
 *
 * 構造（2026-09 時点で本番HTMLを確認。robots.txt は実在し、店舗一覧・詳細は Disallow 対象外）:
 *   - 一覧 /jp/region/common/include/shop.html は静的HTMLで全国が1ページ（ページ送り無し）。
 *       <li><a href="/jp/shop/info/detail/shopcd/S0103"><span>北海道／YSP帯広</span></a></li>
 *     ★span テキストは「都道府県／店名」（全角スラッシュ ／ 区切り）。店名は ／ の後ろ、都道府県は前。
 *     ★shopcd（S0103 等）を external_id とする。
 *   - 詳細 /jp/shop/info/detail/shopcd/{shopcd} の <table class="shopInfo ..."> に
 *       <tr class="address"><th>住所</th><td>〒080-0026<br>北海道帯広市西16条南30丁目2-23 <a>アクセス</a></td></tr>
 *       <tr><th>電話番号</th><td><b data-tel="...">0155-48-1417</b></td></tr>
 *       <tr><th>営業時間</th><td>10:00～18:00</td></tr>
 *     がある。★この shopInfo テーブルに限定し、<th> ラベルで <td> を引く（位置ではなくラベル）。
 *     ★住所は 〒＋都道府県から始まる（rental819 と違い都道府県が入る）ので、そのまま AddressParser に渡す。
 *       末尾の「アクセス」リンクは <a> ごと除去してから住所本体にする。
 *     official_url は詳細ページURL。
 *
 * ★リクエストは「一覧1回 ＋ 店舗詳細 約81回」。1リクエストごとに2秒待つ・並列にしない・同じページを取り直さない。
 * ★img は解析対象にしない（写真・ロゴ・紹介文・料金・車種・在庫は扱わない）。返す配列に画像キーを持たせない。
 */
final class YamahaFetcher extends AbstractFetcher
{
    /** 事業者サイト（トップ）。 */
    private const SITE = 'https://bike-rental.yamaha-motor.co.jp/';

    /** 店舗一覧（全国が1ページ・ページ送り無し）。 */
    private const LIST_URL = 'https://bike-rental.yamaha-motor.co.jp/jp/region/common/include/shop.html';

    public function slug(): string
    {
        return 'yamaha';
    }

    public function company(): string
    {
        return 'ヤマハ バイクレンタル';
    }

    public function officialUrl(): string
    {
        return self::SITE;
    }

    /** ★日本語で要求する（チェックリスト手順4）。/jp/ 配下で日本語固定だが安全側で明示。 */
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
     * 一覧HTMLから店舗（店名＋詳細URL＋shopcd＋都道府県）を重複無しで取り出す（テスト用に公開）。
     * 各 <a href="/jp/shop/info/detail/shopcd/{code}"><span>都道府県／店名</span></a> を拾い、
     * span を「都道府県」「店名」に分ける（全角スラッシュ ／ で分割）。同一 shopcd への複数リンクは潰す。
     *
     * @return array<int, array{id: string, url: string, name: string, prefecture: ?string}>
     */
    public function extractStores(string $html): array
    {
        $pattern = '~<a\b[^>]*href="(?<href>/jp/shop/info/detail/shopcd/(?<code>[A-Za-z0-9]+))"[^>]*>\s*'
            .'<span[^>]*>(?<label>.*?)</span>~is';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $stores = [];
        $seen = [];

        foreach ($matches as $m) {
            $code = $m['code'];
            if (isset($seen[$code])) {
                continue;
            }

            $label = trim(html_entity_decode(strip_tags($m['label']), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($label === '') {
                continue;
            }

            // 「都道府県／店名」。前＝都道府県、後ろ＝店名。／ が無ければ全体を店名とみなす。
            $parts = preg_split('~／~u', $label, 2) ?: [$label];
            $prefecture = count($parts) === 2 ? trim($parts[0]) : null;
            $name = trim(count($parts) === 2 ? $parts[1] : $parts[0]);
            if ($name === '') {
                continue;
            }

            $seen[$code] = true;
            $stores[] = [
                'id' => $code,
                'url' => $this->absoluteUrl($m['href']),
                'name' => $name,
                'prefecture' => $prefecture !== null && $prefecture !== '' ? $prefecture : null,
            ];
        }

        return $stores;
    }

    /**
     * 一覧の店舗と詳細HTMLから1レコードを組み立てる（テスト用に公開）。
     * ★住所・電話・営業時間は詳細の shopInfo テーブルから <th> ラベルで <td> を引く（本文は走査しない）。
     * ★住所は都道府県込みなので、末尾の「アクセス」リンクを除いてそのまま AddressParser へ。市区町村すら取れなければ null。
     *
     * @param  array{id: string, url: string, name: string, prefecture?: ?string}  $store
     * @return array<string, mixed>|null
     */
    public function buildRecord(array $store, string $detailHtml): ?array
    {
        $table = $this->storeInfoTable($detailHtml);
        if ($table === null) {
            return null; // 情報テーブルが無い＝住所を信頼できないのでレコードにしない
        }

        // 住所は「アクセス」リンク（<a>）を丸ごと落としてから本体にする。
        $addressField = $this->thTdField($table, '住所', dropAnchors: true);
        if ($addressField === null) {
            return null;
        }

        $postal = $this->extractPostal($addressField);
        // 〒 を落とし、住所内の空白を除く。ヤマハの元データは「札幌市 西区…」のように政令市名と区名の間に
        // 空白が入る店があり、そのままだと AddressParser が区（札幌市西区 等）を解決できない。日本語の
        // 地理表記に空白は不要なので内部空白を除去する。
        $address = (string) preg_replace('/[\s\x{3000}]+/u', '', $this->stripLeadingPostal($addressField));

        if ($this->splitAddress($address)['city'] === null) {
            return null; // 市区町村すら取れない＝地図/エリアに出せない
        }

        $tel = $this->thTdField($table, '電話番号');
        $hours = $this->thTdField($table, '営業時間');

        return $this->makeRecord(
            name: $store['name'],
            address: $address,
            postalCode: $postal,
            tel: $tel !== null ? $this->cleanTel($tel) : null,
            openingHours: $hours !== null ? mb_substr($hours, 0, 100) : null,
            externalId: $store['id'],
            officialUrl: $store['url'],
        );
    }

    /** 詳細HTMLから店舗情報テーブル（table.shopInfo）の中身を取り出す。無ければ null。 */
    private function storeInfoTable(string $html): ?string
    {
        if (preg_match('~<table[^>]*class="[^"]*shopInfo[^"]*"[^>]*>(.*?)</table>~is', $html, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    /**
     * 情報テーブルから <th>{ラベル}</th><td>値</td> の値をラベル一致で取る（位置ではなくラベルで引く）。
     * $dropAnchors=true のときは td 内の <a>…</a>（「アクセス」等）を丸ごと落とす。<br> は空白に。空なら null。
     */
    private function thTdField(string $table, string $label, bool $dropAnchors = false): ?string
    {
        $pattern = '~<th[^>]*>\s*'.preg_quote($label, '~').'\s*</th>\s*<td[^>]*>(.*?)</td>~is';
        if (preg_match($pattern, $table, $m) !== 1) {
            return null;
        }

        $inner = $m[1];
        if ($dropAnchors) {
            $inner = (string) preg_replace('~<a\b[^>]*>.*?</a>~is', ' ', $inner);
        }
        $inner = (string) preg_replace('~<br\s*/?>~i', ' ', $inner);
        $text = html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = trim((string) preg_replace('/[\t\x{3000} ]+/u', ' ', $text));

        return $text !== '' ? $text : null;
    }

    /** テキストから郵便番号（NNN-NNNN）を取る。無ければ null。 */
    private function extractPostal(string $text): ?string
    {
        if (preg_match('/〒?\s*(\d{3})[-ー－]?(\d{4})/u', $text, $m) === 1) {
            return $this->cleanPostal($m[1].'-'.$m[2]);
        }

        return null;
    }

    /** 行頭の 〒NNN-NNNN を落として住所本体だけにする。 */
    private function stripLeadingPostal(string $address): string
    {
        return trim((string) preg_replace('/^〒?\s*\d{3}[-ー－]?\d{4}\s*/u', '', trim($address)));
    }
}
