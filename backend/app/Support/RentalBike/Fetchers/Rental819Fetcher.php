<?php

declare(strict_types=1);

namespace App\Support\RentalBike\Fetchers;

/**
 * レンタル819（rental819.com）。国内最大規模・全国展開。
 *
 * 構造（2026-09 時点で本番HTMLを確認。robots.txt は無し＝Disallow無し）:
 *   - ★接続先は canonical な https://rental819.com/（non-www）。www は non-www へ 301（AbstractFetcher が追従）。
 *   - ★取得は Accept-Language: ja を送る（acceptLanguage()）。ヘッダ無しだと詳細ページが英語
 *     （例: "144-19 Namiki-cho, Kitami-shi, Hokkaido, Japan"）で返り、日本語専用の AddressParser が
 *     都道府県を取れず buildRecord が全件 null 落ちして 3件しか残らない（2026-09 本番検証）。
 *   - 一覧 /store/ の1ページに全国 約108店舗（ページ送り無し・都道府県別グルーピング）。構造は
 *       <div class="p-store-list__area-wrap">
 *         <p class="p-store-list__pref">北海道</p>            ← ★都道府県見出し（日本語フルネーム）
 *         <div class="p-store-list__area-inner">
 *           <a href="/store/{id}">
 *             <span class="p-store-list__store-name"><i class="las ..."></i>店名</span>
 *             <span class="p-store-list__store-adress">市区町村＋番地（都道府県は省略）</span>
 *           </a>
 *         </div> ...
 *       </div> ...
 *     ★店名は name span のテキスト（内側の <i> アイコンをタグ除去で落とす）。
 *     ★都道府県は「直前の p-store-list__pref 見出し」を正とする（一覧の住所・詳細の都道府県行に依存しない）。
 *       詳細ページに都道府県が出ない店（特別区・一般市）があり、以前は 103→32 に脱落していたため。
 *   - 詳細 /store/{id} に 〒・住所（市区町村＋番地）・電話・営業時間 がある。official_url は詳細ページURL。
 *     市区町村・番地は詳細から、都道府県は一覧見出しから取り、両者を連結して AddressParser に渡す。
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

    /**
     * ★日本語を要求する。ヘッダ無しだと詳細ページが英語で返り、AddressParser が都道府県を取れず
     *   buildRecord が全件 null 落ちして 3件だけになる（2026-09 本番検証: このヘッダ有りで「北海道」が返る）。
     */
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
     * 一覧HTMLから店舗（店名＋詳細URL＋ID＋都道府県）を重複無しで取り出す（テスト用に公開）。
     * 一覧は「都道府県見出し（p.p-store-list__pref）→ その都道府県の店舗（a[href=/store/N]）」の順で並ぶので、
     * 見出しと店舗リンクを出現順に走査し、直近の都道府県を各店舗へ紐づける（詳細ページの都道府県行に依存しない）。
     * 店名は span.p-store-list__store-name のテキスト（内側の <i> アイコン等はタグ除去で落とす）。
     * name span を持たない /store/ リンク（ナビ・フッター等）は店舗ではないので除外する。
     *
     * @return array<int, array{id: string, url: string, name: string, prefecture: ?string}>
     */
    public function extractStores(string $html): array
    {
        // 都道府県見出しと店舗アンカーを1本のパターンで出現順に拾う（どちらが来たかは名前付きグループで判別）。
        $pattern = '~<p\b[^>]*class="[^"]*p-store-list__pref[^"]*"[^>]*>(?<pref>.*?)</p>'
            .'|<a\b[^>]*href="/store/(?<id>\d+)"[^>]*>(?<inner>.*?)</a>~is';

        if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        $stores = [];
        $seen = [];
        $currentPref = null;

        foreach ($matches as $m) {
            // 都道府県見出し（id グループが空＝アンカーではない）→ 直近の都道府県を更新する。
            if (($m['id'] ?? '') === '') {
                $pref = trim(html_entity_decode(strip_tags($m['pref'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if ($pref !== '') {
                    $currentPref = $pref;
                }

                continue;
            }

            // 店舗アンカー。
            $id = $m['id'];
            if (isset($seen[$id])) {
                continue; // 同一店舗への複数リンクを潰す
            }
            if (preg_match('~<span[^>]*class="[^"]*p-store-list__store-name[^"]*"[^>]*>(.*?)</span>~is', $m['inner'], $nm) !== 1) {
                continue; // 店名 span を持たない /store/ リンクは店舗ではない
            }
            // ★内側の <i class="las ..."> アイコン等をタグ除去で落としてから店名を取る（アイコンが名前に混入するのを防ぐ）。
            $name = trim(html_entity_decode(strip_tags($nm[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($name === '') {
                continue;
            }
            $seen[$id] = true;
            $stores[] = [
                'id' => $id,
                'url' => self::SITE.'store/'.$id,
                'name' => $name,
                'prefecture' => $currentPref, // ★一覧の地域見出しから（詳細に依存しない）
            ];
        }

        return $stores;
    }

    /**
     * 一覧の店舗（店名・都道府県）と詳細HTML（〒・住所・電話・営業時間）から1レコードを組み立てる（テスト用に公開）。
     * ★都道府県は一覧見出しを正とし、市区町村・番地は詳細ページの住所から取る。両者を連結して AddressParser に渡す。
     *   詳細に都道府県が出ない店（特別区・一般市）でも都道府県が埋まる。市区町村すら取れなければ null。
     *
     * @param  array{id: string, url: string, name: string, prefecture?: ?string}  $store
     * @return array<string, mixed>|null
     */
    public function buildRecord(array $store, string $detailHtml): ?array
    {
        $text = $this->htmlToText($detailHtml);

        $address = $this->firstAddressLine($text);
        if ($address === null) {
            return null; // 市区町村すら取れない＝地図/エリアに出せないのでレコードにしない
        }

        // ★都道府県は一覧見出しを正とする。住所行の先頭に都道府県が付いていれば剥がしてから前置し、
        //   AddressParser が prefecture=一覧値・city=詳細住所 で解決できるようにする（二重都道府県を防ぐ）。
        $prefecture = $store['prefecture'] ?? null;
        if ($prefecture !== null && $prefecture !== '') {
            $address = $prefecture.$this->stripLeadingPrefecture($address);
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

    /**
     * 詳細テキストから最初の「住所らしい行」を返す。
     * ★都道府県は一覧見出しから付けるので、ここでは「番地（数字）を含み、市区町村が取れる行」であれば足りる。
     *   これで詳細に都道府県が出ない特別区・一般市（例: 杉並区阿佐谷北4-27-3）も住所行として拾える。
     */
    private function firstAddressLine(string $text): ?string
    {
        foreach (explode("\n", $text) as $line) {
            // 行頭の 〒NNN-NNNN を落として住所本体だけにする（同一行に郵便番号が付くケース）。
            $line = trim((string) preg_replace('/^〒?\s*\d{3}[-ー－]?\d{4}\s*/u', '', trim($line)));
            if ($line === '') {
                continue;
            }
            if (preg_match('/[0-9０-９]/u', $line) !== 1) {
                continue; // 番地（数字）が無い行は住所本体ではない（見出し・都道府県名だけの行など）
            }
            if ($this->splitAddress($line)['city'] !== null) {
                return $line;
            }
        }

        return null;
    }

    /** 住所行の先頭に都道府県が付いていれば剥がす（一覧見出しの都道府県を前置し直すため。二重都道府県の防止）。 */
    private function stripLeadingPrefecture(string $address): string
    {
        $pref = $this->splitAddress($address)['prefecture'];
        if ($pref !== null && str_starts_with($address, $pref)) {
            return ltrim(mb_substr($address, mb_strlen($pref)));
        }

        return $address;
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
