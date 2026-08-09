<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Parking\AddressParser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 国土地理院（GSI）住所検索APIによるジオコーディング（品質ガード付き）。
 * 承認時にのみ・住所がある場合にのみ呼ぶ（市区町村中心・県庁への誤ピンを避ける）。
 *
 * 過去の事故: city「都筑区」(横浜市欠け)で連結 → GSIが市区レベルまで解決できず
 * 県代表点(県庁)にフォールバック一致し、NULLより悪い「もっともらしく間違った座標」を保存。
 * → 結果の properties.title に市区名が含まれることを採用条件にする（A-2）。
 */
final class GsiGeocodingService
{
    private const ENDPOINT = 'https://msearch.gsi.go.jp/address-search/AddressSearch';

    public function __construct(
        private readonly AddressParser $addressParser,
    ) {}

    /**
     * prefecture + city + address からジオコーディング。見つからない/検証NG/失敗時は null。
     *
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $prefecture, string $city, string $address): ?array
    {
        $prefecture = $this->normalize($prefecture);
        $city = $this->normalize($city);
        $address = $this->normalize($address);

        if ($address === '') {
            return null;
        }

        // city の先頭に prefecture が二重に入っている行の対策（本番ログに
        // 「福岡県福岡県古賀市…」「東京都東京都北区…」等）。city 列が「福岡県古賀市」のように
        // 都道府県名を含むのが原因。以降のクエリ組み立て・A-1・needle すべてで、
        // prefecture を剥がした city（例「古賀市」）を使う。
        if ($prefecture !== '' && str_starts_with($city, $prefecture)) {
            $city = mb_substr($city, mb_strlen($prefecture));
        }

        // A-1: address が city（または政令市の区名末尾要素）で始まる場合は重複を除去。
        // 例: city「横浜市都筑区」に対し address「都筑区荏田南…」→ 「荏田南…」
        $cityTail = null;
        if (preg_match('/市(.+?区)$/u', $city, $m)) {
            $cityTail = $m[1];
        }
        if ($city !== '' && str_starts_with($address, $city)) {
            $address = ltrim(mb_substr($address, mb_strlen($city)));
        } elseif ($cityTail !== null && str_starts_with($address, $cityTail)) {
            $address = ltrim(mb_substr($address, mb_strlen($cityTail)));
        }

        $query = $prefecture.$city.$address;

        // A-3: 合併等で「愛知県長久手町愛知県長久手市菖蒲池1015」のように、先頭以外の位置に
        // prefecture が再出現し、その位置から現在の正しい住所（合併後の住所）が丸ごと始まって
        // いる行が本番ログにある。再出現位置から後ろだけをクエリとして使う。
        // このケースは city による needle 判定を行わず、代わりに「title が prefecture だけで
        // ないこと（市区町村以下が含まれること）」を採用条件にする（下の A-2 分岐参照）。
        $prefectureRestart = false;
        if ($prefecture !== '') {
            $pos = mb_strpos($query, $prefecture, 1);
            if ($pos !== false) {
                $query = mb_substr($query, $pos);
                $prefectureRestart = true;
            }
        }

        try {
            $response = Http::timeout(5)->get(self::ENDPOINT, ['q' => $query]);

            if (! $response->successful()) {
                Log::warning('GSI geocoding non-2xx', ['status' => $response->status(), 'query' => $query]);

                return null;
            }

            $features = $response->json();
            if (! is_array($features) || count($features) === 0) {
                return null; // 見つからず
            }

            $title = (string) ($features[0]['properties']['title'] ?? '');

            // A-2: title に市区町村レベルの照合語（needle）が含まれること。県名のみ＝上位レベルへの
            // フォールバック結果は不採用（誤って県庁所在地の座標を掴むのを防ぐ）。
            if ($prefectureRestart) {
                // A-3 でクエリを prefecture 再出現位置から切り出したケース。渡された city は元の
                // （合併前等の）値でクエリと食い違うため使わない。切り出し後のクエリから
                // AddressParser で市区町村を取り直し、その needle が title に含まれることを要求する。
                //   例: 「埼玉県鳩ヶ谷市南1-1-1」→ city「鳩ヶ谷市」。title「埼玉県鳩山」（鳩山町）は
                //       含まないので棄却（約40km 離れた誤自治体の座標を掴むのを防ぐ）。
                $parsedCity = $this->addressParser->parse($query)['city'];
                if ($parsedCity !== '') {
                    $needle = $this->cityNeedle($parsedCity);
                    if ($needle !== '' && ! str_contains(self::matchNormalize($title), self::matchNormalize($needle))) {
                        Log::warning('GSI geocoding rejected: title lacks city (A-3切り出し後・誤自治体の疑い)', [
                            'query' => $query,
                            'title' => $title,
                            'needle' => $needle,
                        ]);

                        return null;
                    }
                } else {
                    // 市区町村を取り直せないときは保険として「title が prefecture だけ」を棄却する。
                    $normTitle = self::matchNormalize($title);
                    $normPref = self::matchNormalize($prefecture);
                    $rest = str_starts_with($normTitle, $normPref)
                        ? mb_substr($normTitle, mb_strlen($normPref))
                        : $normTitle;
                    if ($rest === '') {
                        Log::warning('GSI geocoding rejected: title is prefecture only (県レベルfallbackの疑い)', [
                            'query' => $query,
                            'title' => $title,
                            'prefecture' => $prefecture,
                        ]);

                        return null;
                    }
                }
            } else {
                // 比較は matchNormalize を通す（title は「龍ケ崎市」、needle は「龍ヶ崎市」のように
                // 「ヶ／ケ」等の表記ゆれがあるため）。クエリ文字列そのものは変えない。
                $needle = $this->cityNeedle($city);
                if ($needle !== '' && ! str_contains(self::matchNormalize($title), self::matchNormalize($needle))) {
                    Log::warning('GSI geocoding rejected: title lacks city (県レベルfallbackの疑い)', [
                        'query' => $query,
                        'title' => $title,
                        'needle' => $needle,
                    ]);

                    return null;
                }
            }

            // GeoJSON: coordinates は [経度, 緯度] の順（lng, lat）
            $coords = $features[0]['geometry']['coordinates'] ?? null;
            if (! is_array($coords) || count($coords) < 2) {
                return null;
            }

            return [
                'lat' => (float) $coords[1],
                'lng' => (float) $coords[0],
            ];
        } catch (\Throwable $e) {
            Log::warning('GSI geocoding failed: '.$e->getMessage(), ['query' => $query]);

            return null;
        }
    }

    /**
     * A-2 の照合語（needle）を city から決める。
     *  - 政令市の区（「〜市〜区」）: 区は再編・廃止で title に現れないことがある
     *    （実測: city「浜松市東区」→ GSIの title は「静岡県浜松市」/「静岡県浜松市浜名区」）。
     *    市の部分だけ（「浜松市」）を照合語にし、title が市を含めば採用する。
     *  - 郡下の町村（「〜郡〜町」「〜郡〜村」）: 地理院の title は郡を省略する
     *    （実測: city「高市郡明日香村」→ title「奈良県明日香村…」）。郡より後ろの町村名
     *    （「明日香村」）を照合語にする。
     *  - 東京23区など「〜区」のみ（例「大田区」）／市・町・村（例「三木市」）: city 全体で照合。
     */
    private function cityNeedle(string $city): string
    {
        if (preg_match('/^(.+?市).+区$/u', $city, $mm)) {
            return $mm[1];
        }
        if (preg_match('/郡(.+?[町村])$/u', $city, $mg)) {
            return $mg[1];
        }

        return $city;
    }

    /**
     * クエリ前の正規化: 全角英数記号→半角（全角ハイフン含む）、各種ダッシュ→半角ハイフン、
     * スペース（半/全角）除去、trim。
     */
    private function normalize(string $s): string
    {
        $s = mb_convert_kana($s, 'a');           // 全角英数記号→半角（全角ハイフンU+FF0Dも半角化）
        $s = str_replace(['−', '—', '―', 'ｰ'], '-', $s); // マイナス/ダッシュ/半角長音→ハイフン
        $s = preg_replace('/[\s\x{3000}]+/u', '', $s) ?? $s; // スペース除去（半角・全角）

        return trim($s);
    }

    /**
     * title と needle の「比較専用」正規化。地理院へ送るクエリ文字列には使わない。
     * ・「ヶ」→「ケ」、「ヵ」→「カ」（例: 龍ヶ崎市 vs 龍ケ崎市 / 保土ヶ谷区 vs 保土ケ谷区 の表記ゆれ吸収）
     * ・半角/全角スペース除去、trim
     */
    private static function matchNormalize(string $s): string
    {
        $s = str_replace(['ヶ', 'ヵ'], ['ケ', 'カ'], $s);
        $s = preg_replace('/[\s\x{3000}]+/u', '', $s) ?? $s;

        return trim($s);
    }
}
