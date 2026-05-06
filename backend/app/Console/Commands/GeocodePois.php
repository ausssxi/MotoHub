<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Poi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

final class GeocodePois extends Command
{
    protected $signature = 'poi:geocode {--limit=5000 : 1回の実行で処理する最大件数}';

    protected $description = '国土地理院 + Nominatim(フォールバック)でPOIの住所を逆ジ���コーディング';

    private const GSI_GEOCODE_URL = 'https://mreversegeocoder.gsi.go.jp/reverse-geocoder/LonLatToAddress';
    private const GSI_MUNI_URL = 'https://maps.gsi.go.jp/js/muni.js';
    private const NOMINATIM_URL = 'https://nominatim.openstreetmap.org/reverse';

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

        // addressがNULLまたは空文字のPOIのみ取得（既存の住所は絶対に上書きし��い）
        $pois = Poi::where(function ($q) {
                $q->whereNull('address')->orWhere('address', '');
            })
            ->limit($limit)
            ->get();

        if ($pois->isEmpty()) {
            $this->info('住所未取得のPOIはありません。');
            return self::SUCCESS;
        }

        $this->info("対象: {$pois->count()}件");
        $gsiSuccess = 0;
        $nominatimSuccess = 0;
        $failed = 0;

        foreach ($pois as $poi) {
            // 安全チェック: 万が一addressが入っていたらスキップ
            if ($poi->address !== null && $poi->address !== '') {
                continue;
            }

            // 1. 国土地理院APIで試行
            $address = $this->tryGsi($poi);

            if ($address !== null) {
                $poi->update(['address' => $address]);
                $gsiSuccess++;
                $this->logProgress($gsiSuccess + $nominatimSuccess);
                usleep(200000); // 0.2秒
                continue;
            }

            // 2. フォールバック: Nominatim (OpenStreetMap)
            $address = $this->tryNominatim($poi);

            if ($address !== null) {
                $poi->update(['address' => $address]);
                $nominatimSuccess++;
                $this->logProgress($gsiSuccess + $nominatimSuccess);
                sleep(1); // Nominatimのレート制限: 1リクエスト/秒
                continue;
            }

            // 3. 両方失敗 → スキップ
            $this->warn("  [{$poi->id}] 住所取得不可 (座標: {$poi->latitude},{$poi->longitude})");
            $failed++;
            sleep(1); // Nominatimを呼んだ後なので1秒待機
        }

        $total = $gsiSuccess + $nominatimSuccess;
        $this->newLine();
        $this->info("完了: 成功 {$total}件 (国土地理院: {$gsiSuccess} / Nominatim: {$nominatimSuccess}) / 失敗 {$failed}件");

        return self::SUCCESS;
    }

    /**
     * 国土地理院の逆ジオコーディングAPIで住所を取得
     */
    private function tryGsi(Poi $poi): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'MotoHub/1.0 (https://motohub.jp)'])
                ->get(self::GSI_GEOCODE_URL, [
                    'lat' => $poi->latitude,
                    'lon' => $poi->longitude,
                ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();
            $results = $data['results'] ?? null;

            if (! $results || empty($results['muniCd'])) {
                return null;
            }

            $muniCd = ltrim($results['muniCd'], '0');
            $lv01Nm = $results['lv01Nm'] ?? '';
            $muniName = $this->muniMap[$muniCd] ?? '';

            if (! $muniName) {
                return null;
            }

            return $muniName . $lv01Nm;
        } catch (\Exception $e) {
            $this->error("  [{$poi->id}] GSIエラー: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Nominatim (OpenStreetMap) の逆ジオコーディングAPIで住所を取得
     */
    private function tryNominatim(Poi $poi): ?string
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders(['User-Agent' => 'MotoHub/1.0 (https://motohub.jp)'])
                ->get(self::NOMINATIM_URL, [
                    'lat' => $poi->latitude,
                    'lon' => $poi->longitude,
                    'format' => 'json',
                    'accept-language' => 'ja',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $data = $response->json();

            // display_name から住所を取得（日本語で返される）
            $displayName = $data['display_name'] ?? null;
            if (! $displayName) {
                return null;
            }

            // Nominatimのdisplay_nameは「番地, 町名, 区, 市, 都道府県, 国」の逆順
            // address構造体からも組み立て可能
            $addr = $data['address'] ?? [];
            $parts = [];

            // 都道府県
            $pref = $addr['province'] ?? $addr['state'] ?? '';
            if ($pref) {
                $parts[] = $pref;
            }

            // 市区町村
            $city = $addr['city'] ?? $addr['town'] ?? $addr['village'] ?? $addr['county'] ?? '';
            if ($city) {
                $parts[] = $city;
            }

            // 区（政令指定都市）
            $suburb = $addr['suburb'] ?? $addr['city_district'] ?? '';
            if ($suburb && $suburb !== $city) {
                $parts[] = $suburb;
            }

            // 町名・番地
            $neighbourhood = $addr['neighbourhood'] ?? $addr['quarter'] ?? '';
            if ($neighbourhood) {
                $parts[] = $neighbourhood;
            }

            if (empty($parts)) {
                return null;
            }

            return implode('', $parts);
        } catch (\Exception $e) {
            $this->error("  [{$poi->id}] Nominatimエラー: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * 進捗ログ出力（100件ごと）
     */
    private function logProgress(int $count): void
    {
        if ($count % 100 === 0) {
            $this->info("  {$count}件完了...");
        }
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
