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
    protected $description = 'ショップの住所から緯度経度を取得して保存します（OSM Nominatim使用）';

    public function handle(): void
    {
        $this->info('ショップの座標取得を開始します...');

        $query = Shop::query();
        
        // 強制フラグがない場合は、まだ座標がない店舗のみ対象
        if (!$this->option('force')) {
            $query->whereNull('latitude')->orWhereNull('longitude');
        }

        // 住所がある店舗のみ
        $shops = $query->whereNotNull('address')->get();

        if ($shops->isEmpty()) {
            $this->info('対象となるショップはありませんでした。');
            return;
        }

        $bar = $this->output->createProgressBar($shops->count());
        $bar->start();

        foreach ($shops as $shop) {
            // 住所が短すぎる場合はスキップ
            if (mb_strlen($shop->address) < 3) {
                $bar->advance();
                continue;
            }

            try {
                // OpenStreetMap Nominatim API を使用
                // User-Agentの指定が必須です
                $response = Http::withHeaders([
                    'User-Agent' => 'MotoHub/1.0 (contact@motohub.jp)' 
                ])->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json',
                    'q' => $shop->address,
                    'limit' => 1,
                    'countrycodes' => 'jp' // 日本国内限定
                ]);

                if ($response->successful() && !empty($response->json())) {
                    $data = $response->json()[0];
                    
                    $shop->update([
                        'latitude' => $data['lat'],
                        'longitude' => $data['lon'],
                    ]);
                } else {
                    // 見つからない場合はログに残してスキップ
                    // Log::warning("Geocode failed for shop ID: {$shop->id} ({$shop->address})");
                }

            } catch (\Exception $e) {
                Log::error("Geocode Error: " . $e->getMessage());
            }

            // APIの負荷軽減のため、必ず1秒以上待機する（重要）
            sleep(1);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('座標取得が完了しました！');
    }
}