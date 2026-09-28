<?php

declare(strict_types=1);

namespace App\Support\RentalBike\PriceFetchers;

use Illuminate\Support\Facades\Http;

/**
 * 料金フェッチャの共通処理（取得・待機・HTMLの表パース補助）。
 * 取得作法は店舗フェッチャ（AbstractFetcher）と同じ: 連絡先入りUA・1リクエスト2秒待ち・並列にしない。
 * ★重複を避けるため本来は共通トレイトにしたいが、店舗側に手を入れないよう最小限で自前保持している。
 */
abstract class AbstractPriceFetcher implements PriceFetcher
{
    /** ★匿名でクロールしない。相手が問い合わせできるよう連絡先を入れる（店舗フェッチャと同一）。 */
    protected const USER_AGENT = 'MotoHub/1.0 (+https://motohub.jp; info@motohub.jp)';

    /** 1リクエストごとの待機秒数。 */
    protected const REQUEST_INTERVAL_SEC = 2;

    /** MotoHub の5車格（表示・保存で使う正準値）。 */
    public const CLASS_MOPED = '原付';

    public const CLASS_125 = '125cc';

    public const CLASS_250 = '250cc';

    public const CLASS_400 = '400cc';

    public const CLASS_LARGE = '大型';

    /** URLを取得して本文を返す。失敗時は null。日本語で要求する。 */
    protected function get(string $url): ?string
    {
        $res = Http::withHeaders([
            'User-Agent' => self::USER_AGENT,
            'Accept-Language' => 'ja-JP,ja;q=0.9',
        ])
            ->withOptions(['allow_redirects' => ['max' => 5, 'referer' => true]])
            ->timeout(20)
            ->get($url);

        return $res->successful() ? $res->body() : null;
    }

    /** リクエスト間の待機（並列にしない・連続で叩かない）。今は1社1ページなので実質使わないが作法として持つ。 */
    protected function pause(): void
    {
        sleep(self::REQUEST_INTERVAL_SEC);
    }

    /** 「1,234円」「¥1,234」等から整数の円を取り出す。取れなければ null。 */
    protected function yen(string $text): ?int
    {
        if (preg_match('/([0-9][0-9,]{1,7})\s*円|[¥￥]\s*([0-9][0-9,]{1,7})/u', $text, $m) === 1) {
            $digits = (string) preg_replace('/[^\d]/', '', ($m[1] ?? '').($m[2] ?? ''));

            return $digits !== '' ? (int) $digits : null;
        }

        return null;
    }

    /**
     * HTMLの1つの表を「行ごとのセル配列」に開く。<br> は空白、タグは除去、空セルは落とす。
     *
     * @return array<int, array<int, string>>
     */
    protected function tableRows(string $tableHtml): array
    {
        $rows = [];
        foreach (preg_match_all('~<tr[^>]*>(.*?)</tr>~is', $tableHtml, $trm) ? $trm[1] : [] as $tr) {
            $cells = [];
            foreach (preg_match_all('~<t[hd][^>]*>(.*?)</t[hd]>~is', $tr, $cm) ? $cm[1] : [] as $c) {
                $c = (string) preg_replace('~<br\s*/?>~i', ' ', $c);
                $c = html_entity_decode(strip_tags($c), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $c = trim((string) preg_replace('/[\s\x{3000}]+/u', ' ', $c));
                if ($c !== '') {
                    $cells[] = $c;
                }
            }
            if ($cells !== []) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    /**
     * HTMLから全 <table> の中身を順に返す。
     *
     * @return array<int, string>
     */
    protected function tables(string $html): array
    {
        return preg_match_all('~<table[^>]*>(.*?)</table>~is', $html, $m) ? $m[1] : [];
    }
}
