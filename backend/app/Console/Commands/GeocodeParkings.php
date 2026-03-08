<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeParking;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class GeocodeParkings extends Command
{
    protected $signature = 'parking:geocode
        {--limit=100 : 処理件数の上限}
        {--force : 座標取得済みのデータも再取得する}';

    protected $description = '座標が未設定の駐車場に対してGeocodingを実行します（Nominatim API）';

    private const USER_AGENT = 'MotoHub/1.0 (https://www.motohub.jp; bike parking map)';

    public function handle(): void
    {
        $limit = (int) $this->option('limit');
        $force = $this->option('force');

        $query = BikeParking::query()->whereNotNull('address')->where('address', '!=', '');

        if (!$force) {
            $query->where(function ($q) {
                $q->whereNull('latitude')->orWhereNull('longitude');
            });
        }

        $parkings = $query->limit($limit)->get();

        if ($parkings->isEmpty()) {
            $this->info('対象となる駐車場はありません。');
            return;
        }

        $this->info("{$parkings->count()}件の駐車場を座標変換します...");
        $bar = $this->output->createProgressBar($parkings->count());
        $bar->start();

        $success = 0;
        $fail = 0;

        foreach ($parkings as $parking) {
            $coords = $this->geocode($parking->address);

            if ($coords) {
                $parking->update([
                    'latitude' => $coords['lat'],
                    'longitude' => $coords['lon'],
                ]);
                $success++;
            } else {
                $fail++;
            }

            usleep(1100000); // Nominatim: 1秒1リクエスト
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("完了！ (成功: {$success}件 / 失敗: {$fail}件)");
    }

    private function geocode(string $address): ?array
    {
        try {
            $response = Http::withHeaders(['User-Agent' => self::USER_AGENT])
                ->get('https://nominatim.openstreetmap.org/search', [
                    'format' => 'json',
                    'q' => $address,
                    'countrycodes' => 'jp',
                    'limit' => 1,
                ]);

            if ($response->successful() && !empty($response->json())) {
                $result = $response->json()[0];
                return [
                    'lat' => (float) $result['lat'],
                    'lon' => (float) $result['lon'],
                ];
            }
        } catch (\Exception $e) {
            // 個別エラーは無視して次へ
        }

        return null;
    }
}
