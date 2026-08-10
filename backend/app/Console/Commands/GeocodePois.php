<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Poi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * POIの住所を国土地理院の逆ジオコーディングで埋めるバッチ（毎日4:30）。
 *
 * ★Nominatim(OpenStreetMap)フォールバックは 2026-08-10 に完全撤去した。理由:
 *   1. 利用ポリシー違反: Nominatim の利用ポリシーは「大量データの一括ジオコーディング」を
 *      明確に禁止している。本バッチは毎日最大5000件を対象に叩いており、これに反していた。
 *   2. 費用対効果が成立しない: 実測で住所付きPOIは2日間で19件しか増えず(12,805→12,824)、
 *      その間毎日1万リクエスト規模を投げて成果は1日10件程度だった。
 *   加えて 2026-08-08 に国土数値情報N03を導入し、都道府県・市区町村は全48,005件を
 *   APIなしで座標から判定できるようになった(prefecture/city/municipality_code列)。
 *   Nominatim が担っていたのは番地レベルの住所のみで、上記の通り成果が乏しいため撤去する。
 *
 * さらに失敗の再試行を止めるため geocode_failed_at を記録する。GSIが失敗した行には時刻を刻み、
 * 既定ではその行を翌日以降の対象から外す(--retry-failed で明示的に再挑戦できる)。対象クエリには
 * orderBy('id') を付け、毎日同じ先頭を舐め続けずに処理が前へ進むようにする。
 */
final class GeocodePois extends Command
{
    protected $signature = 'poi:geocode
        {--limit=5000 : 1回の実行で処理する最大件数}
        {--retry-failed : geocode_failed_at が記録済みの失敗行も対象に含める}
        {--sleep=1000 : 1件ごとの待機ミリ秒（GSIは公共API）}';

    protected $description = '国土地理院でPOIの住所を逆ジオコーディング（失敗は記録し既定では再試行しない）';

    private const GSI_GEOCODE_URL = 'https://mreversegeocoder.gsi.go.jp/reverse-geocoder/LonLatToAddress';
    private const GSI_MUNI_URL = 'https://maps.gsi.go.jp/js/muni.js';

    /** @var array<string, string> muniCd => "都道府県市区町村" */
    private array $muniMap = [];

    public function handle(): int
    {
        $limit = (int) $this->option('limit');
        $sleepMs = (int) $this->option('sleep');

        // 市区町村コード→名称テーブルを取得
        $this->info('市区町村コードテーブルを取得中...');
        if (! $this->loadMuniTable()) {
            $this->error('市区町村テーブルの取得に失敗しました。');
            return self::FAILURE;
        }
        $this->info('  ' . count($this->muniMap) . '件の市区町村を読み込み');

        // addressがNULLまたは空文字のPOIのみ取得（既存の住所は絶対に上書きし��い）
        // 既定では失敗記録済み(geocode_failed_at IS NOT NULL)を除外し、毎日同じ失敗行を叩かない。
        // --retry-failed を付けたときだけ失敗行も対象に含める。
        // orderBy('id') で毎回同じ先頭を舐めず、未処理の後方へ処理を前進させる。
        $pois = Poi::where(function ($q) {
                $q->whereNull('address')->orWhere('address', '');
            })
            ->when(! $this->option('retry-failed'), function ($q) {
                $q->whereNull('geocode_failed_at');
            })
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($pois->isEmpty()) {
            $this->info('住所未取得のPOIはありません。');
            return self::SUCCESS;
        }

        $this->info("対象: {$pois->count()}件");
        $gsiSuccess = 0;
        $failed = 0;

        foreach ($pois as $poi) {
            // 安全チェック: 万が一addressが入っていたらスキップ
            if ($poi->address !== null && $poi->address !== '') {
                continue;
            }

            // 国土地理院APIで試行（Nominatimフォールバックは撤去済み。クラスコメント参照）。
            $address = $this->tryGsi($poi);

            if ($address !== null) {
                // 成功。過去に失敗記録があっても住所が取れたのでクリアする。
                $poi->update(['address' => $address, 'geocode_failed_at' => null]);
                $gsiSuccess++;
                $this->logProgress($gsiSuccess);
                $this->pause($sleepMs);
                continue;
            }

            // 失敗 → geocode_failed_at を刻んで既定では翌日以降の対象から外す。
            $poi->update(['geocode_failed_at' => now()]);
            $this->warn("  [{$poi->id}] 住所取得不可 (座標: {$poi->latitude},{$poi->longitude})");
            $failed++;
            $this->pause($sleepMs);
        }

        $this->newLine();
        $this->info("完了: 成功 {$gsiSuccess}件 (国土地理院) / 失敗 {$failed}件");

        return self::SUCCESS;
    }

    /** GSI は公共APIのため1件ごとに待機する（shops:geocode と同作法）。 */
    private function pause(int $ms): void
    {
        if ($ms > 0) {
            usleep($ms * 1000);
        }
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
