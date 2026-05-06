<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Poi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

final class GeocodePois extends Command
{
    protected $signature = 'poi:geocode {--limit=5000 : 1回の実行で処理する最大件数}';

    protected $description = '国土地理院の逆ジオコーディングAPIでPOIの住所を取得';

    private const GSI_GEOCODE_URL = 'https://mreversegeocoder.gsi.go.jp/reverse-geocoder/LonLatToAddress';
    private const GSI_MUNI_URL = 'https://maps.gsi.go.jp/js/muni.js';

    /** @var array<string, string> muniCd => "都道府県市区町村" */
    private array $muniMap = [];

    public function handle(): int
    {
        $limit = (int) $this->option('limit');

        // 市区町村コード→名称テーブルを取得
        $this->info('市区町村コードテーブルを取得中...');
        if (! $this->loadMuniTable()) {
            $this->error('市区町村テーブルの取得に失敗しました。');
            return self::FAILURE;
        }
        $this->info('  ' . count($this->muniMap) . '件の市区町村を読み込み');

        $pois = Poi::where(function ($q) {
                $q->whereNull('address')->orWhere('address', '');
            })
            ->limit($limit)
            ->get();

        if ($pois->isEmpty()) {
            $this->info('住所未取得のPOIはありません。');
            return self::SUCCESS;
        }

        $this->info("対象: {$pois->count()}件（0.2秒/件）");
        $success = 0;
        $failed = 0;

        foreach ($pois as $poi) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders(['User-Agent' => 'MotoHub/1.0 (https://motohub.jp)'])
                    ->get(self::GSI_GEOCODE_URL, [
                        'lat' => $poi->latitude,
                        'lon' => $poi->longitude,
                    ]);

                if (! $response->successful()) {
                    $this->warn("  [{$poi->id}] HTTPエラー: {$response->status()}");
                    $failed++;
                    usleep(200000);
                    continue;
                }

                $data = $response->json();
                $results = $data['results'] ?? null;

                if (! $results || empty($results['muniCd'])) {
                    $this->warn("  [{$poi->id}] 住所取得不可 (座標: {$poi->latitude},{$poi->longitude})");
                    $failed++;
                    usleep(200000);
                    continue;
                }

                $muniCd = ltrim($results['muniCd'], '0');
                $lv01Nm = $results['lv01Nm'] ?? '';
                $muniName = $this->muniMap[$muniCd] ?? '';

                if (! $muniName) {
                    $this->warn("  [{$poi->id}] 市区町村コード不明: {$muniCd}");
                    $failed++;
                    usleep(200000);
                    continue;
                }

                $address = $muniName . $lv01Nm;

                $poi->update(['address' => $address]);
                $success++;

                if ($success % 100 === 0) {
                    $this->info("  {$success}件完了...");
                }
            } catch (\Exception $e) {
                $this->error("  [{$poi->id}] エラー: {$e->getMessage()}");
                $failed++;
            }

            usleep(200000);
        }

        $this->info("完了: 成功 {$success}件 / 失敗 {$failed}件");

        return self::SUCCESS;
    }

    /**
     * GSIの市区町村コードJSファイルをパースしてmuniMapを構築
     * 形式: GSI.MUNI_ARRAY["13101"]='13,東京都,13101,千代田区';
     */
    private function loadMuniTable(): bool
    {
        try {
            $response = Http::timeout(30)
                ->withHeaders(['User-Agent' => 'MotoHub/1.0 (https://motohub.jp)'])
                ->get(self::GSI_MUNI_URL);

            if (! $response->successful()) {
                return false;
            }

            $js = $response->body();

            // パターン: GSI.MUNI_ARRAY["13101"]='13,東京都,13101,千代田区';
            preg_match_all('/MUNI_ARRAY\["(\d+)"\]\s*=\s*\'([^\']+)\'/', $js, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                $code = $match[1];
                $parts = explode(',', $match[2]);
                // parts: [prefCode, prefName, muniCode, muniName]
                if (count($parts) >= 4) {
                    $prefName = $parts[1];
                    $muniName = str_replace("\u{3000}", '', $parts[3]); // 全角スペース除去
                    $this->muniMap[$code] = $prefName . $muniName;
                }
            }

            return count($this->muniMap) > 0;
        } catch (\Exception $e) {
            $this->error("muniテーブル取得エラー: {$e->getMessage()}");
            return false;
        }
    }
}
