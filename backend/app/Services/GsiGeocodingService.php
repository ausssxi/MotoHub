<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 国土地理院（GSI）住所検索APIによるジオコーディング。
 * 承認時にのみ・住所がある場合にのみ呼ぶ（市区町村中心へのピン誤誘導を避けるため）。
 */
final class GsiGeocodingService
{
    private const ENDPOINT = 'https://msearch.gsi.go.jp/address-search/AddressSearch';

    /**
     * 住所 → 緯度経度。見つからない/失敗時は null。
     *
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $address): ?array
    {
        $address = trim($address);
        if ($address === '') {
            return null;
        }

        try {
            $response = Http::timeout(5)->get(self::ENDPOINT, ['q' => $address]);

            if (! $response->successful()) {
                Log::warning('GSI geocoding non-2xx', ['status' => $response->status(), 'address' => $address]);

                return null;
            }

            $features = $response->json();
            if (! is_array($features) || count($features) === 0) {
                return null; // 見つからず
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
            Log::warning('GSI geocoding failed: '.$e->getMessage(), ['address' => $address]);

            return null;
        }
    }
}
