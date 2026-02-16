<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Shop;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeocodeShops extends Command
{
    protected $signature = 'shops:geocode {--force : 取得済みデータも再取得する}';
    protected $description = 'ショップの住所から緯度経度を取得して保存します（国土地理院API使用）';

    public function handle(): void
    {
        $this->info('ショップの座標取得を開始します (国土地理院API)...');

        $query = Shop::query();
        
        if (!$this->option('force')) {
            $query->whereNull('latitude')->orWhereNull('longitude');
        }

        $shops = $query->whereNotNull('address')->get();

        if ($shops->isEmpty()) {
            $this->info('対象となるショップはありませんでした。');
            return;
        }

        $bar = $this->output->createProgressBar($shops->count());
        $bar->start();

        $successCount = 0;
        $failCount = 0;

        foreach ($shops as $shop) {
            if (mb_strlen($shop->address) < 3) {
                $bar->advance();
                continue;
            }

            // 1. 住所を整形
            $cleanAddress = $this->cleanAddress($shop->address);
            
            // 2. 検索実行（見つかるまで住所を短くして再トライ）
            $coords = $this->searchCoordinatesWithFallback($cleanAddress);

            if ($coords) {
                $shop->update([
                    'latitude' => $coords['lat'],
                    'longitude' => $coords['lon'],
                ]);
                $successCount++;
            } else {
                // Log::warning("Geocode failed. ID:{$shop->id}, Address:{$shop->address}");
                $failCount++;
            }

            // サーバー負荷軽減のため0.5秒待機
            usleep(500000); 
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("完了しました！ (成功: {$successCount}件 / 失敗: {$failCount}件)");
    }

    /**
     * 住所を短くしながら再帰的に検索する
     */
    private function searchCoordinatesWithFallback(string $address): ?array
    {
        // ベースの検索
        $result = $this->callApi($address);
        if ($result) return $result;

        // 見つからない場合、末尾の「数字」や「ハイフン」を削って再トライ
        // 例: "山形市あかねケ丘3-7-26" -> "山形市あかねケ丘3-7" -> "山形市あかねケ丘3"
        
        $shorterAddress = preg_replace('/[-－][0-9]+$/u', '', $address);
        
        if ($shorterAddress === $address) {
            $shorterAddress = preg_replace('/[0-9]+$/u', '', $address);
        }

        if ($shorterAddress === $address || mb_strlen($shorterAddress) < 5) {
            return null;
        }

        return $this->searchCoordinatesWithFallback($shorterAddress);
    }

    /**
     * 国土地理院APIを叩く
     */
    private function callApi(string $address): ?array
    {
        try {
            // 国土地理院 地名検索API
            $response = Http::get('https://msearch.gsi.go.jp/address-search/AddressSearch', [
                'q' => $address,
            ]);

            if ($response->successful() && !empty($response->json())) {
                $data = $response->json()[0];
                $geometry = $data['geometry'] ?? null;
                $coords = $geometry['coordinates'] ?? null;

                // 国土地理院APIは [経度, 緯度] の順で返ってくるので注意
                if ($coords && count($coords) === 2) {
                    return [
                        'lat' => $coords[1], // 2番目が緯度
                        'lon' => $coords[0], // 1番目が経度
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::error("API Error: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * 住所のクリーニング
     */
    private function cleanAddress(string $address): string
    {
        $address = mb_convert_kana($address, 'n'); // 全角数字を半角に
        $address = preg_replace('/[0-9]+[F|階].*$/u', '', $address); // フロア削除
        $address = preg_replace('/[0-9]+号室.*$/u', '', $address); // 号室削除
        
        // ビル名などを削除
        $parts = preg_split('/[\s　]+/u', $address);
        if (count($parts) > 1) {
            return $parts[0];
        }
        return $address;
    }
}