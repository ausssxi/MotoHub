<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Station;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GenerateStationParkingContent extends Command
{
    protected $signature = 'station:generate-parking-content
        {--prefecture= : 特定の都道府県だけ処理}
        {--limit=0 : 処理件数の上限（0=無制限）}
        {--dry-run : 対象件数だけ表示しAPIは呼ばない}
        {--force : 既存テキストを上書き}';

    protected $description = '駅別駐車場ページのAI紹介文を生成';

    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const MAX_TOKENS = 1000;

    private const SLEEP_SECONDS = 1;

    private const MIN_PARKINGS = 3;

    public function handle(): int
    {
        $apiKey = config('services.anthropic.api_key');
        $isDryRun = $this->option('dry-run');
        $isForce = $this->option('force');
        $prefecture = $this->option('prefecture');
        $limit = (int) $this->option('limit');

        if (! $isDryRun && ! $apiKey) {
            $this->error('ANTHROPIC_API_KEY が .env に設定されていません。');

            return self::FAILURE;
        }

        // 駐車場3件以上の駅を取得
        $query = Station::query()
            ->withCount(['bikeParkings' => fn ($q) => $q->where('is_active', true)])
            ->having('bike_parkings_count', '>=', self::MIN_PARKINGS);

        if (! $isForce) {
            $query->whereNull('ai_parking_content');
        }
        if ($prefecture) {
            $query->where('prefecture', $prefecture);
        }

        $query->orderByDesc('bike_parkings_count');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $stations = $query->get();

        if ($stations->isEmpty()) {
            $this->info('処理対象の駅がありません。');

            return self::SUCCESS;
        }

        $this->info("対象: {$stations->count()} 駅");

        if ($isDryRun) {
            $stations->each(fn ($s) => $this->line("  {$s->name}駅（{$s->prefecture}）- {$s->bike_parkings_count}件"));

            return self::SUCCESS;
        }

        // 都道府県別の平均駐車場数を事前計算
        $prefAvg = DB::table('bike_parkings')
            ->join('stations', 'bike_parkings.station_id', '=', 'stations.id')
            ->where('bike_parkings.is_active', true)
            ->selectRaw('stations.prefecture, COUNT(*) as cnt, COUNT(DISTINCT stations.id) as station_cnt')
            ->groupBy('stations.prefecture')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->prefecture => $r->station_cnt > 0 ? round($r->cnt / $r->station_cnt, 1) : 0]);

        $completed = 0;
        $errors = 0;
        $total = $stations->count();

        foreach ($stations as $index => $station) {
            $num = $index + 1;
            $prefix = "[{$num}/{$total}] {$station->name}駅";

            try {
                $data = $this->collectParkingData($station, $prefAvg->get($station->prefecture, 0));
                $this->info("{$prefix}: データ収集OK（{$data['count']}件）");

                $text = $this->callClaudeApi($apiKey, $data);

                if (! $text) {
                    $this->error("{$prefix}: レスポンスのパースに失敗");
                    $errors++;

                    continue;
                }

                $station->update(['ai_parking_content' => $text]);
                $this->info("{$prefix}: 保存完了");
                $completed++;
            } catch (\Throwable $e) {
                $this->error("{$prefix}: エラー - {$e->getMessage()}");
                Log::error('GenerateStationParkingContent: 失敗', [
                    'station_id' => $station->id,
                    'error' => $e->getMessage(),
                ]);
                $errors++;
            }

            if ($index < $total - 1) {
                sleep(self::SLEEP_SECONDS);
            }
        }

        $this->newLine();
        $this->info("完了: {$completed}駅の紹介文を生成（エラー: {$errors}件）");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function collectParkingData(Station $station, float $prefAvg): array
    {
        $parkings = $station->nearbyParkings(0.5)->get();

        $hourlyPrices = $parkings->pluck('price_per_hour')->filter()->values();
        $monthlyPrices = $parkings->pluck('price_per_month')->filter()->values();
        $count24h = $parkings->where('available_24h', true)->count();
        $countCovered = $parkings->where('is_covered', true)->count();
        $totalCapacity = $parkings->sum('capacity');
        $total = $parkings->count();

        // 大型バイク制限
        $largeKeywords = ['大型', '400', '750', '制限なし', '排気量制限なし'];
        $restrictedCount = $parkings->filter(function ($p) use ($largeKeywords) {
            if (! $p->vehicle_restriction) {
                return false;
            }
            foreach ($largeKeywords as $kw) {
                if (mb_strpos($p->vehicle_restriction, $kw) !== false) {
                    return true;
                }
            }

            return false;
        })->count();

        $restrictedInfo = $restrictedCount > 0
            ? "{$restrictedCount}件が大型バイク対応"
            : '大型バイク対応の明記なし（要問合せ）';

        // 月極情報
        $monthlyInfo = $monthlyPrices->isNotEmpty()
            ? number_format((int) $monthlyPrices->min()).'〜'.number_format((int) $monthlyPrices->max()).'円（'.$monthlyPrices->count().'件）'
            : 'なし';

        // 各駐車場リスト
        $parkingList = $parkings->map(function ($p) {
            $prices = [];
            if ($p->is_free) {
                $prices[] = '無料';
            }
            if ($p->price_per_hour) {
                $prices[] = number_format($p->price_per_hour).'円/時';
            }
            if ($p->price_per_month) {
                $prices[] = number_format($p->price_per_month).'円/月';
            }

            return $p->name.'（'.(implode('、', $prices) ?: '料金不明').'）';
        })->implode("\n");

        return [
            'station_name' => $station->name,
            'line_names' => $station->line_names ?: '不明',
            'prefecture' => $station->prefecture,
            'count' => $total,
            'pref_avg' => $prefAvg,
            'min_hourly' => $hourlyPrices->isNotEmpty() ? (int) $hourlyPrices->min() : null,
            'max_hourly' => $hourlyPrices->isNotEmpty() ? (int) $hourlyPrices->max() : null,
            'avg_hourly' => $hourlyPrices->isNotEmpty() ? (int) round($hourlyPrices->avg()) : null,
            'monthly_info' => $monthlyInfo,
            'count_24h' => $count24h,
            'percent_24h' => $total > 0 ? round($count24h / $total * 100) : 0,
            'count_covered' => $countCovered,
            'percent_covered' => $total > 0 ? round($countCovered / $total * 100) : 0,
            'total_capacity' => $totalCapacity ?: '不明',
            'restriction_info' => $restrictedInfo,
            'parking_list' => $parkingList,
        ];
    }

    private function callClaudeApi(string $apiKey, array $data): ?string
    {
        $systemPrompt = <<<'PROMPT'
あなたはバイク通勤・通学者向けの駐車場ガイドライターです。
提供されたデータに基づいて、実用的で具体的な紹介文を書いてください。

ルール：
- 200〜300文字で書く
- 提供されたデータに基づく事実のみを書く
- 具体的な駐車場名と料金を必ず含める
- 「です・ます」調で統一
- HTMLタグは使わない
- テキストのみを出力（JSON不要）
PROMPT;

        $hourlyInfo = $data['min_hourly'] !== null
            ? "- 時間料金: {$data['min_hourly']}〜{$data['max_hourly']}円（平均{$data['avg_hourly']}円）"
            : '- 時間料金: データなし';

        $userPrompt = <<<PROMPT
以下のデータをもとに、{$data['station_name']}駅（{$data['line_names']}、{$data['prefecture']}）周辺のバイク駐車場事情について200〜300文字の紹介文を生成してください。

駐車場データ:
- 件数: {$data['count']}件（{$data['prefecture']}平均: {$data['pref_avg']}件）
{$hourlyInfo}
- 月極: {$data['monthly_info']}
- 24時間利用可能: {$data['count_24h']}件（{$data['percent_24h']}%）
- 屋根付き: {$data['count_covered']}件（{$data['percent_covered']}%）
- 収容台数合計: {$data['total_capacity']}台
- 大型バイク制限: {$data['restriction_info']}
- 各駐車場:
{$data['parking_list']}

以下のポイントを自然に織り込んでください:
- 駐車場の多さ/少なさ（都道府県平均との比較）
- 料金の安い駐車場の具体名
- 通勤利用なら月極がおすすめ等のアドバイス
- 大型バイクの注意点（制限がある場合）
- 屋根付きが少ない場合の注意
- 具体的な台数が少ない場合の混雑注意

読者はバイクで通勤・通学・買い物をする人です。
具体的な駐車場名と料金を入れて実用的な内容にしてください。
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post(self::API_ENDPOINT, [
            'model' => config('services.anthropic.model'),
            'max_tokens' => self::MAX_TOKENS,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("API error: {$response->status()} - {$response->body()}");
        }

        $body = $response->json();
        $text = $body['content'][0]['text'] ?? null;

        if (! $text) {
            Log::error('GenerateStationParkingContent: API応答にtextなし', ['body' => $body]);

            return null;
        }

        return trim($text);
    }
}
