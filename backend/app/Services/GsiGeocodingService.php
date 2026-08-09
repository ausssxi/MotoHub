<?php

declare(strict_types=1);

namespace App\Services;

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
            // needle の決め方:
            //  - 政令市の区（「〜市〜区」）: 区は再編・廃止で title に現れないことがある
            //    （実測: city「浜松市東区」→ GSIの title は「静岡県浜松市」/「静岡県浜松市浜名区」）。
            //    市の部分だけ（「浜松市」）を照合語にし、title が市を含めば採用する。
            //  - 東京23区など「〜区」のみ（例「大田区」）: 従来どおり city 全体で照合。
            //  - 市・町・村（例「三木市」）: 従来どおり city 全体で照合。
            $needle = $city;
            if (preg_match('/^(.+?市).+区$/u', $city, $mm)) {
                $needle = $mm[1];
            }
            if ($needle !== '' && ! str_contains($title, $needle)) {
                Log::warning('GSI geocoding rejected: title lacks city (県レベルfallbackの疑い)', [
                    'query' => $query,
                    'title' => $title,
                    'needle' => $needle,
                ]);

                return null;
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
}
