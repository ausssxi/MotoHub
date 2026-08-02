<?php

declare(strict_types=1);

namespace App\Services\RentalGarage\Scrapers;

use App\Support\JapanCityPrefecture;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * レンタルガレージ事業者スクレイパーの共通基盤。
 * 社ごとの実装は fetch() で rental_garages 用の配列を1件ずつ yield する。
 *
 * ネットワーク・リトライ・住所/料金パースの共通処理をここに集約し、
 * 各社サブクラスはセレクタとフィールド抽出に専念できるようにする。
 */
abstract class AbstractRentalGarageScraper
{
    // 必ずこの UA を送る（ブラウザ偽装はしない）。
    protected const USER_AGENT = 'MotoHubBot/1.0 (+https://motohub.jp/)';

    // リトライ設定（FetchPois::fetchRegion と同流儀）。
    protected const MAX_RETRIES = 3;
    protected const RETRY_BACKOFF = [5, 15, 45]; // 秒（リトライ1/2/3回目の待機）
    protected const RETRYABLE_STATUSES = [429, 502, 503, 504];

    /** スクレイパー識別子（config/rental_garages.php のキーと一致）。例: 'inaba' */
    abstract public function key(): string;

    /** 表示名。例: 'イナバボックス' */
    abstract public function label(): string;

    /**
     * rental_garages に入れる配列を1件ずつ yield するジェネレータ。
     *
     * @return iterable<int, array<string, mixed>>
     */
    abstract public function fetch(?int $limit = null): iterable;

    /**
     * HTML を取得。429/502/503/504 と接続タイムアウトは指数バックオフでリトライ（最大 MAX_RETRIES 回）。
     * 429 以外の 4xx（Not Found 等）は即失敗して null。取得できなければ null。
     */
    protected function get(string $url): ?string
    {
        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                    ->timeout(15)
                    ->get($url);

                if ($response->successful()) {
                    return $response->body();
                }

                $status = $response->status();

                // 429 以外の 4xx はリトライしない。
                if ($status >= 400 && $status < 500 && $status !== 429) {
                    return null;
                }

                // リトライ対象外のステータス（想定外）も失敗扱い。
                if (! in_array($status, self::RETRYABLE_STATUSES, true)) {
                    return null;
                }

                if ($attempt >= self::MAX_RETRIES) {
                    return null;
                }

                sleep(self::RETRY_BACKOFF[$attempt]);
            } catch (ConnectionException $e) {
                if ($attempt >= self::MAX_RETRIES) {
                    return null;
                }

                sleep(self::RETRY_BACKOFF[$attempt]);
            }
        }

        return null;
    }

    /**
     * 1リクエストにつき最低1秒空ける（相手サーバーへの配慮）。
     */
    protected function throttle(): void
    {
        sleep(1);
    }

    /**
     * 料金テキストから [min, max] を取り出す。
     * 「8,800円～16,500円」→ [8800, 16500] / 「8,800円」→ [8800, null] / 取れなければ [null, null]。
     *
     * @return array{0: int|null, 1: int|null}
     */
    protected function parseFee(string $text): array
    {
        if ($text === '') {
            return [null, null];
        }

        // 「9,900 円」「9,900円」いずれも「数字（カンマ可）＋円」で拾う。
        preg_match_all('/([0-9][0-9,]*)\s*円/u', $text, $m);
        $nums = array_map(static fn (string $n): int => (int) str_replace(',', '', $n), $m[1]);

        if (count($nums) === 0) {
            return [null, null];
        }
        if (count($nums) === 1) {
            return [$nums[0], null];
        }

        return [$nums[0], $nums[1]];
    }

    /**
     * 住所先頭に都道府県が「文字として」付いていればそれを返す。無ければ null。
     * （フォールバックはしない純粋な前方一致判定。extractCity で prefix 剥がしにも使う。）
     */
    private function matchPrefecturePrefix(string $address): ?string
    {
        if (preg_match('/^(東京都|北海道|(?:京都|大阪)府|.{2,3}県)/u', $address, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * 住所から都道府県を得る。先頭に都道府県表記があればそれ、無ければ
     * 政令市・23区の市区名から引く（岡山市…等、市名始まり表記の救済）。取れなければ null。
     */
    protected function extractPrefecture(string $address): ?string
    {
        $literal = $this->matchPrefecturePrefix($address);
        if ($literal !== null) {
            return $literal;
        }

        // 「岡山市北区…」のように都道府県が省略された市区名始まりを救済。
        return JapanCityPrefecture::fromCity($address);
    }

    /**
     * 都道府県の次の市区町村を抜く。「〇〇郡△△町」は1単位として扱う。取れなければ null。
     * 都道府県が住所先頭に「文字として」ある場合のみ剥がす。市区名始まり（フォールバックで
     * 都道府県を引いたケース）は住所そのものから先頭の市区町村を拾う。
     */
    protected function extractCity(string $address): ?string
    {
        $literal = $this->matchPrefecturePrefix($address);
        $rest = $literal !== null ? mb_substr($address, mb_strlen($literal)) : $address;

        // 郡＋町/村 は「郡」単独で切らず町/村まで含める。それ以外は先頭の市/区/町/村まで。
        if (preg_match('/^(.+?郡.+?[町村]|.+?[市区町村])/u', $rest, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * 店舗名の先頭に事業者名を補って表記を揃える（例「北長瀬店」→「イナバボックス北長瀬店」）。
     * 一覧HTMLが事業者名を省く物件がまれにあるため。既に事業者名を含む名前には付け足さない。
     */
    protected function ensureOperatorPrefix(string $name, string $operator): string
    {
        if ($name === '' || $operator === '') {
            return $name;
        }
        if (str_contains($name, $operator)) {
            return $name;
        }

        return $operator.$name;
    }

    /**
     * 全角空白・改行・連続空白を正規化して trim。
     */
    protected function normalizeText(string $s): string
    {
        $s = str_replace("\u{3000}", ' ', $s); // 全角空白→半角
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s; // 改行・連続空白→単一空白

        return trim($s);
    }

    /**
     * 店舗名の先頭に付く【…】ブロック（開店予定日などの注記）を切り離す。
     * 例「【2026年8月下旬OPEN予定】イナバボックス蓮田黒浜店」
     *   → ['name' => 'イナバボックス蓮田黒浜店', 'annotation' => '2026年8月下旬OPEN予定']
     *
     * ※ 全角【】・半角[]・全角［］に対応。先頭に無い括弧には触れない（^ アンカー必須）。
     *
     * @return array{name: string, annotation: string|null}
     */
    protected function splitNameAnnotation(string $rawName): array
    {
        $name = $this->normalizeText($rawName);

        // 先頭の括弧ブロックのみ。中身を捕捉し、括弧ごと除去する。
        if (preg_match('/^\s*[【\[［](.*?)[】\]］]\s*/u', $name, $m)) {
            $annotation = $this->normalizeText($m[1]);
            $name = $this->normalizeText(preg_replace('/^\s*[【\[［].*?[】\]］]\s*/u', '', $name) ?? $name);

            return [
                'name' => $name,
                'annotation' => $annotation !== '' ? $annotation : null,
            ];
        }

        return ['name' => $name, 'annotation' => null];
    }

    /**
     * ジオコーディング精度を上げるための住所正規化。
     * ※ 末尾の位置説明除去は必ず末尾アンカー($)で判定し、住所途中の地名（「西の京」「南北町」等）は壊さない。
     *
     * 実測（GSI AddressSearch）で、番地レベル解決には表記統一が有効なため以下を適用する。
     */
    protected function normalizeAddress(string $address): string
    {
        $s = $this->normalizeText($address);

        // 1) ハイフン類を半角ハイフン(U+002D)に統一。番地区切りが機種依存記号だと解決精度が落ちるため。
        //    ｰ(U+FF70 半角ｶﾀｶﾅ長音) ー(U+30FC 全角長音) −(U+2212) ―(U+2015) ‐(U+2010) ‑(U+2011) ­(U+00AD)
        $s = str_replace(["\u{FF70}", "\u{30FC}", "\u{2212}", "\u{2015}", "\u{2010}", "\u{2011}", "\u{00AD}"], '-', $s);

        // 2) 全角数字→半角数字（「２丁目」等を「2丁目」に）。
        $s = mb_convert_kana($s, 'n');

        // 3) 末尾の補足語「付近」「周辺」「地内」「内」を除去（方角の外側に付くため先行）。
        //    地内 を 内 より前に並べ "地内" は2文字まとめて消す。
        $s = preg_replace('/(付近|周辺|地内|内)$/u', '', $s) ?? $s;

        // 4) 末尾の「の＋方角(＋側/方向)」を除去。例「…4013-1の南東」。複合方角を単一方角より先に。
        $s = preg_replace('/の(北東|北西|南東|南西|東|西|南|北)(側|方向)?$/u', '', $s) ?? $s;

        // 5) 末尾の「の＋位置語」を除去。例「…2-4の隣」「…の裏」「…の向かい」「…の正面」「…の角」。
        $s = preg_replace('/の(隣|裏|向かい|正面|角)$/u', '', $s) ?? $s;

        // 6) 漢数字の丁目を算用数字へ（一〜二十まで）。例「植田西二丁目」→「植田西2丁目」。
        $s = preg_replace_callback('/([一二三四五六七八九十]+)丁目/u', function (array $m): string {
            return $this->kanjiNumToInt($m[1]).'丁目';
        }, $s) ?? $s;

        // 7) 「N丁目M番K号」「N丁目M番地」「N丁目M-K」「N丁目M」→「N-M-K」形式へ統一。
        //    番地区切りを半角ハイフンに揃えると GSI/Nominatim とも解決しやすい。
        $s = preg_replace('/(\d+)丁目(\d+)番(\d+)号?/u', '$1-$2-$3', $s) ?? $s;
        $s = preg_replace('/(\d+)丁目(\d+)番地?/u', '$1-$2', $s) ?? $s;
        $s = preg_replace('/(\d+)丁目(\d+)-(\d+)/u', '$1-$2-$3', $s) ?? $s;
        $s = preg_replace('/(\d+)丁目(\d+)/u', '$1-$2', $s) ?? $s;
        $s = preg_replace('/(\d+)丁目/u', '$1', $s) ?? $s;

        return trim($s);
    }

    /**
     * 漢数字（一〜二十程度）を整数に変換。丁目変換の補助。
     */
    private function kanjiNumToInt(string $k): int
    {
        $digits = ['一' => 1, '二' => 2, '三' => 3, '四' => 4, '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9];

        if ($k === '十') {
            return 10;
        }
        if (isset($digits[$k])) {
            return $digits[$k];
        }
        // 「十M」「N十」「N十M」（十一〜二十一程度）に対応。
        if (str_contains($k, '十')) {
            [$tens, $ones] = array_pad(explode('十', $k), 2, '');
            $t = $tens === '' ? 1 : ($digits[$tens] ?? 0);
            $o = $ones === '' ? 0 : ($digits[$ones] ?? 0);

            return $t * 10 + $o;
        }

        return $digits[$k] ?? 0;
    }

    /**
     * URL からクエリ文字列・フラグメントを除去して正規化（source_url を updateOrCreate キーにするため）。
     */
    protected function normalizeUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        if (($hash = strpos($url, '#')) !== false) {
            $url = substr($url, 0, $hash);
        }
        if (($q = strpos($url, '?')) !== false) {
            $url = substr($url, 0, $q);
        }

        return $url;
    }
}
