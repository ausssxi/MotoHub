<?php

declare(strict_types=1);

namespace App\Services\RentalGarage\Scrapers;

use Symfony\Component\DomCrawler\Crawler;

/**
 * イナバボックス（バイク収納可）スクレイパー。
 * 一覧: https://www.inaba-box.jp/shop/special/bike/ （2ページ目以降は .../page/{n}/）
 *
 * 実HTML構造（2026-08 時点で確認）:
 *   1件 = <div class="shopListDetail">
 *     店名/詳細URL = .shopListDetailTtl h2 a （href が絶対URL・クエリ無し）
 *     種別ラベル   = .labelBox.shopPlace .label（例: class="label label-outside" テキスト「屋外型」）
 *     テーブル     = table tr の th ラベルで分岐:
 *                     「月額価格(税込)」→ 料金（例「9,900 円～42,900 円」／～はU+FF5E）
 *                     「広さ」          → サイズ（例「2.4畳～14.0畳」）
 *                     「所在地」        → 住所（例「埼玉県蓮田市黒浜4013-1の南東」）
 *                     「アクセス」      → 最寄アクセス（description に格納）
 *     設備アイコン = .iconBox li.icon-*（img src の -on/-off で ON/OFF、li 自体が無ければ不明）
 *   ページネーション = .paginate .pageNum テキスト「1/21ページ」から総ページ数
 */
final class InabaBoxScraper extends AbstractRentalGarageScraper
{
    private const LIST_URL = 'https://www.inaba-box.jp/shop/special/bike/';
    private const OPERATOR = 'イナバボックス';

    public function key(): string
    {
        return 'inaba';
    }

    public function label(): string
    {
        return self::OPERATOR;
    }

    public function fetch(?int $limit = null): iterable
    {
        $firstHtml = $this->get(self::LIST_URL);
        if ($firstHtml === null) {
            return; // 1ページ目が取れなければ何も出さない
        }

        $totalPages = $this->extractTotalPages($firstHtml);
        $emitted = 0;

        for ($page = 1; $page <= $totalPages; $page++) {
            if ($page === 1) {
                $html = $firstHtml;
            } else {
                $this->throttle(); // ページ遷移ごとに1秒空ける
                $html = $this->get(self::LIST_URL.'page/'.$page.'/');
                if ($html === null) {
                    continue; // このページは飛ばす（他ページは続行）
                }
            }

            foreach ($this->parseItems($html) as $row) {
                yield $row;
                $emitted++;
                if ($limit !== null && $emitted >= $limit) {
                    return;
                }
            }
        }
    }

    /**
     * 一覧HTMLから総ページ数を得る。「1/21ページ」→ 21。取れなければ page-numbers 最大値、最終的に1。
     */
    private function extractTotalPages(string $html): int
    {
        $crawler = new Crawler($html);

        $pageNum = $crawler->filter('.paginate .pageNum');
        if ($pageNum->count() > 0 && preg_match('/\/\s*(\d+)\s*ページ/u', $pageNum->text(''), $m)) {
            return max(1, (int) $m[1]);
        }

        // フォールバック: 数字の page-numbers リンク最大値。
        $max = 1;
        $crawler->filter('a.page-numbers')->each(function (Crawler $a) use (&$max): void {
            $t = trim($a->text(''));
            if (ctype_digit($t)) {
                $max = max($max, (int) $t);
            }
        });

        return $max;
    }

    /**
     * 1ページ分の全 .shopListDetail をパースして配列群を返す。
     *
     * @return array<int, array<string, mixed>>
     */
    private function parseItems(string $html): array
    {
        $crawler = new Crawler($html);

        $items = [];
        $crawler->filter('.shopListDetail')->each(function (Crawler $node) use (&$items): void {
            $row = $this->parseItem($node);
            if ($row !== null) {
                $items[] = $row;
            }
        });

        return $items;
    }

    /**
     * 1件（.shopListDetail）をパース。名前 or 詳細URLが取れない要素は null。
     *
     * @return array<string, mixed>|null
     */
    private function parseItem(Crawler $node): ?array
    {
        $link = $node->filter('.shopListDetailTtl h2 a');
        if ($link->count() === 0) {
            return null;
        }

        // 先頭の【…】注記（開店予定日等）を名前から切り離す。
        $split = $this->splitNameAnnotation($link->text(''));
        $name = $split['name'];
        $annotation = $split['annotation'];
        $sourceUrl = $this->normalizeUrl($link->attr('href') ?? '');
        if ($name === '' || $sourceUrl === '') {
            return null;
        }

        // 開店前物件（注記に「予定」を含む）は非公開。イナバは開店後に注記を外すため、
        // 再取得時に自動で公開へ切り替わる（fetch 側で is_active を更新対象にすること）。
        $isActive = ! ($annotation !== null && str_contains($annotation, '予定'));

        // garage_type: 「屋外型」(label-outside)→container /「屋内型」(label-inside)→indoor / 不明→other。
        // 判定に使う文字列 = クラス 'label-outside'/'label-inside' とテキスト '屋外'/'屋内'。
        $garageType = 'other';
        $label = $node->filter('.labelBox.shopPlace .label');
        if ($label->count() > 0) {
            $labelClass = $label->attr('class') ?? '';
            $labelText = $this->normalizeText($label->text(''));
            if (str_contains($labelClass, 'label-outside') || str_contains($labelText, '屋外')) {
                $garageType = 'container';
            } elseif (str_contains($labelClass, 'label-inside') || str_contains($labelText, '屋内')) {
                $garageType = 'indoor';
            }
        }

        // テーブル行を th ラベルで分岐。
        $feeText = '';
        $sizeText = '';
        $address = '';
        $access = '';
        $node->filter('table tr')->each(function (Crawler $tr) use (&$feeText, &$sizeText, &$address, &$access): void {
            $th = $tr->filter('th');
            $td = $tr->filter('td');
            if ($th->count() === 0 || $td->count() === 0) {
                return;
            }
            $key = $this->normalizeText($th->text(''));
            $value = $this->normalizeText($td->text(''));
            if (str_contains($key, '月額')) {
                $feeText = $value;
            } elseif (str_contains($key, '広さ')) {
                $sizeText = $value;
            } elseif (str_contains($key, '所在地')) {
                $address = $value;
            } elseif (str_contains($key, 'アクセス')) {
                $access = $value;
            }
        });

        [$feeMin, $feeMax] = $this->parseFee($feeText);

        // 所在地末尾の位置説明（例「…4013-1の南東」）を除去してからジオコーディングへ渡す。
        // 除去が発生した場合のみ、後から検証できるよう原文を description に「所在地表記:」で残す。
        $rawAddress = $address;
        $cleanAddress = $this->normalizeAddress($rawAddress);
        $descParts = [];
        if ($annotation !== null) {
            $descParts[] = '開店情報: '.$annotation;
        }
        if ($access !== '') {
            $descParts[] = $access;
        }
        if ($cleanAddress !== $rawAddress && $rawAddress !== '') {
            $descParts[] = '所在地表記: '.$rawAddress;
        }
        // 複数ある場合は改行で連結（開店情報／所在地表記のラベル行を分けて検証しやすくする）。
        $description = $descParts !== [] ? implode("\n", $descParts) : null;

        // 設備アイコン: img src の -on/-off で判定。li が無ければ「書いていない」= null。
        $is24h = $this->iconState($node, 'icon-use-24h');   // 「24時間利用可」
        $hasSecurity = $this->iconState($node, 'icon-camera'); // 「防犯設備あり」
        // 一覧には「電源/コンセント」「シャッター」に対応するアイコンが存在しないため null 固定
        // （詳細ページは今回未取得）。将来詳細ページを見るなら埋める。
        $hasPower = null;
        $hasShutter = null;

        return [
            'name' => $name,
            'operator' => self::OPERATOR,
            'garage_type' => $garageType,
            'address' => $cleanAddress,
            'prefecture' => $cleanAddress !== '' ? $this->extractPrefecture($cleanAddress) : null,
            'city' => $cleanAddress !== '' ? $this->extractCity($cleanAddress) : null,
            'monthly_fee_min' => $feeMin,
            'monthly_fee_max' => $feeMax,
            'size_text' => $sizeText !== '' ? $sizeText : null,
            'is_24h' => $is24h,
            'has_power' => $hasPower,
            'has_security' => $hasSecurity,
            'has_shutter' => $hasShutter,
            'website_url' => $sourceUrl,
            'source_url' => $sourceUrl,
            'description' => $description,
            'is_active' => $isActive,
        ];
    }

    /**
     * 設備アイコンの ON/OFF を返す。img src に -on→true / -off→false / アイコン自体が無ければ null。
     */
    private function iconState(Crawler $node, string $iconClass): ?bool
    {
        $li = $node->filter('.iconBox li.'.$iconClass);
        if ($li->count() === 0) {
            return null; // アイコンが無い＝記載なし（不明）
        }

        $img = $li->filter('img');
        if ($img->count() === 0) {
            return null;
        }

        $src = $img->attr('src') ?? '';
        if (str_contains($src, '-on')) {
            return true;
        }
        if (str_contains($src, '-off')) {
            return false;
        }

        return null;
    }
}
