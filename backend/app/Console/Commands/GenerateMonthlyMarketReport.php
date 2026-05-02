<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\BikeNews;
use App\Models\Listing;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GenerateMonthlyMarketReport extends Command
{
    protected $signature = 'news:generate-monthly-report
                            {--publish : 即時公開（デフォルトはdraft）}
                            {--force : 重複チェックをスキップ}
                            {--dry-run : APIを呼ばずデータ確認のみ}';

    protected $description = '前月の中古バイク市場データを集計し、月次レポートを自動生成';

    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const MODEL_ID = 'claude-sonnet-4-20250514';
    private const MAX_TOKENS = 4000;

    private const DISPLACEMENT_BANDS = [
        '〜125cc'     => [0, 125],
        '126〜250cc'  => [126, 250],
        '251〜400cc'  => [251, 400],
        '401cc〜'     => [401, 99999],
    ];

    private const PRICE_BANDS = [
        '〜30万'       => [0, 300000],
        '30〜60万'     => [300001, 600000],
        '60〜100万'    => [600001, 1000000],
        '100〜150万'   => [1000001, 1500000],
        '150万〜'      => [1500001, 999999999],
    ];

    public function handle(): int
    {
        $apiKey = config('services.anthropic.api_key');
        $isDryRun = $this->option('dry-run');

        if (!$isDryRun && !$apiKey) {
            $this->error('ANTHROPIC_API_KEY が .env に設定されていません。');
            return self::FAILURE;
        }

        $targetMonth = now()->subMonth();
        $monthStart = $targetMonth->copy()->startOfMonth();
        $monthEnd = $targetMonth->copy()->endOfMonth()->endOfDay();
        $prevMonthStart = $targetMonth->copy()->subMonth()->startOfMonth();
        $prevMonthEnd = $targetMonth->copy()->subMonth()->endOfMonth()->endOfDay();
        $monthLabel = $targetMonth->format('Y年n月');

        $slug = 'monthly-report-' . $targetMonth->format('Y-m');

        // 重複チェック
        if (!$this->option('force') && BikeNews::where('title', 'like', "%{$monthLabel}%中古バイク市場レポート%")->exists()) {
            $this->warn("既に{$monthLabel}のレポートが存在します。");
            return self::SUCCESS;
        }

        $this->info("=== {$monthLabel} 月次市場レポート生成 ===");

        // データ集計
        $this->info('データ集計中...');
        $summary = $this->collectSummary($monthStart, $monthEnd, $prevMonthStart, $prevMonthEnd);
        $displacementData = $this->collectDisplacementData($monthStart, $monthEnd, $prevMonthStart, $prevMonthEnd);
        $makerData = $this->collectMakerData($monthStart, $monthEnd);
        $topModels = $this->collectTopModels($monthStart, $monthEnd);
        $priceBands = $this->collectPriceBands($monthStart, $monthEnd);

        if ($isDryRun) {
            $this->printDryRun($summary, $displacementData, $makerData, $topModels, $priceBands, $monthLabel);
            return self::SUCCESS;
        }

        // Claude APIでレポート生成
        $this->info('Claude APIでレポート生成中...');

        try {
            $result = $this->callClaudeApi(
                $apiKey, $monthLabel, $summary, $displacementData, $makerData, $topModels, $priceBands
            );
        } catch (\Throwable $e) {
            $this->error("API呼び出しエラー: {$e->getMessage()}");
            Log::error('GenerateMonthlyMarketReport: API呼び出し失敗', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        if ($result === null) {
            $this->error('Claude APIのレスポンスパースに失敗しました。');
            return self::FAILURE;
        }

        // HTML本文 = AI生成テキスト + データテーブル
        $content = $result['body'];
        $content .= $this->buildDataTables($summary, $displacementData, $makerData, $topModels, $priceBands, $monthLabel);

        // サムネイル: 売れ筋1位の車種画像
        $thumbnailUrl = null;
        $topModelId = null;
        if (!empty($topModels)) {
            $firstModel = BikeModel::with('manufacturer')->find($topModels[0]->bike_model_id);
            $thumbnailUrl = $this->resolveImageUrl($firstModel);
            $topModelId = $topModels[0]->bike_model_id;
        }

        $publishedAt = $this->option('publish') ? now() : null;

        $news = BikeNews::create([
            'title'           => $result['title'],
            'url'             => '',
            'source'          => 'MotoHub',
            'content'         => $content,
            'thumbnail_url'   => $thumbnailUrl,
            'published_at'    => $publishedAt,
            'bike_model_id'   => $topModelId,
            'manufacturer_id' => null,
            'is_featured'     => true,
        ]);

        $news->update(['url' => route('news.show', $news->id)]);

        $status = $publishedAt ? '公開' : '下書き';
        $this->info("記事を生成しました（{$status}）: {$result['title']}");

        return self::SUCCESS;
    }

    /**
     * 全体サマリー集計
     */
    private function collectSummary(Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd): array
    {
        $soldQuery = fn (Carbon $s, Carbon $e) => Listing::where('listings.is_sold_out', true)
            ->whereBetween('listings.updated_at', [$s, $e]);

        $newQuery = fn (Carbon $s, Carbon $e) => Listing::whereBetween('listings.created_at', [$s, $e]);

        $soldCount = $soldQuery($start, $end)->count();
        $prevSoldCount = $soldQuery($prevStart, $prevEnd)->count();

        $avgPrice = (int) $soldQuery($start, $end)->whereNotNull('listings.total_price')->avg('listings.total_price');
        $prevAvgPrice = (int) $soldQuery($prevStart, $prevEnd)->whereNotNull('listings.total_price')->avg('listings.total_price');

        $newCount = $newQuery($start, $end)->count();
        $prevNewCount = $newQuery($prevStart, $prevEnd)->count();

        return [
            'sold_count' => $soldCount,
            'prev_sold_count' => $prevSoldCount,
            'sold_change_rate' => $prevSoldCount > 0 ? round(($soldCount - $prevSoldCount) / $prevSoldCount * 100, 1) : 0,
            'avg_price' => $avgPrice,
            'prev_avg_price' => $prevAvgPrice,
            'price_change_rate' => $prevAvgPrice > 0 ? round(($avgPrice - $prevAvgPrice) / $prevAvgPrice * 100, 1) : 0,
            'new_count' => $newCount,
            'prev_new_count' => $prevNewCount,
            'new_change_rate' => $prevNewCount > 0 ? round(($newCount - $prevNewCount) / $prevNewCount * 100, 1) : 0,
        ];
    }

    /**
     * 排気量別データ集計
     */
    private function collectDisplacementData(Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd): array
    {
        $results = [];

        foreach (self::DISPLACEMENT_BANDS as $label => [$min, $max]) {
            $baseQuery = fn (Carbon $s, Carbon $e) => Listing::where('listings.is_sold_out', true)
                ->whereBetween('listings.updated_at', [$s, $e])
                ->whereHas('bikeModel', fn ($q) => $q->whereBetween('bike_models.displacement', [$min, $max]));

            $count = $baseQuery($start, $end)->count();
            $prevCount = $baseQuery($prevStart, $prevEnd)->count();
            $avg = (int) $baseQuery($start, $end)->whereNotNull('listings.total_price')->avg('listings.total_price');

            $results[] = [
                'label' => $label,
                'sold_count' => $count,
                'prev_sold_count' => $prevCount,
                'change_rate' => $prevCount > 0 ? round(($count - $prevCount) / $prevCount * 100, 1) : 0,
                'avg_price' => $avg,
            ];
        }

        return $results;
    }

    /**
     * メーカー別販売台数TOP5
     */
    private function collectMakerData(Carbon $start, Carbon $end): array
    {
        return Listing::where('listings.is_sold_out', true)
            ->whereBetween('listings.updated_at', [$start, $end])
            ->whereNotNull('listings.manufacturer_id')
            ->join('manufacturers', 'listings.manufacturer_id', '=', 'manufacturers.id')
            ->select('manufacturers.name', DB::raw('COUNT(*) as sold_count'))
            ->groupBy('manufacturers.name')
            ->orderByDesc('sold_count')
            ->limit(5)
            ->get()
            ->toArray();
    }

    /**
     * 売れ筋車種TOP10
     */
    private function collectTopModels(Carbon $start, Carbon $end)
    {
        return Listing::where('listings.is_sold_out', true)
            ->whereBetween('listings.updated_at', [$start, $end])
            ->whereNotNull('listings.bike_model_id')
            ->select(
                'listings.bike_model_id',
                DB::raw('COUNT(*) as sold_count'),
                DB::raw('ROUND(AVG(listings.total_price)) as avg_price'),
            )
            ->groupBy('listings.bike_model_id')
            ->orderByDesc('sold_count')
            ->limit(10)
            ->get();
    }

    /**
     * 価格帯別販売分布
     */
    private function collectPriceBands(Carbon $start, Carbon $end): array
    {
        $results = [];

        foreach (self::PRICE_BANDS as $label => [$min, $max]) {
            $count = Listing::where('listings.is_sold_out', true)
                ->whereBetween('listings.updated_at', [$start, $end])
                ->whereNotNull('listings.total_price')
                ->whereBetween('listings.total_price', [$min, $max])
                ->count();

            $results[] = [
                'label' => $label,
                'count' => $count,
            ];
        }

        return $results;
    }

    private function callClaudeApi(
        string $apiKey,
        string $monthLabel,
        array $summary,
        array $displacementData,
        array $makerData,
        $topModels,
        array $priceBands,
    ): ?array {
        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $topModels->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        // データテキスト構築
        $summaryText = "販売台数: {$summary['sold_count']}台（前月比 {$summary['sold_change_rate']}%）\n"
            . "平均販売価格: " . number_format($summary['avg_price']) . "円（前月比 {$summary['price_change_rate']}%）\n"
            . "新規掲載台数: {$summary['new_count']}台（前月比 {$summary['new_change_rate']}%）";

        $dispText = collect($displacementData)->map(fn ($d) =>
            "{$d['label']}: {$d['sold_count']}台（前月比 {$d['change_rate']}%）/ 平均" . number_format($d['avg_price']) . '円'
        )->implode("\n");

        $makerText = collect($makerData)->map(fn ($m, $i) =>
            ($i + 1) . "位 {$m['name']}: {$m['sold_count']}台"
        )->implode("\n");

        $modelText = $topModels->values()->map(function ($row, $i) use ($models) {
            $m = $models->get($row->bike_model_id);
            $name = $m ? $m->name : '不明';
            $maker = $m?->manufacturer?->name ?? '不明';
            return ($i + 1) . "位 {$name}（{$maker}）: {$row->sold_count}台 / 平均" . number_format((int) $row->avg_price) . '円';
        })->implode("\n");

        $priceText = collect($priceBands)->map(fn ($p) =>
            "{$p['label']}: {$p['count']}台"
        )->implode("\n");

        $month = (int) now()->subMonth()->month;

        $seasonalContext = match ($month) {
            1  => '冬場で需要減、相場は底値圏',
            2  => '春に向けて需要回復の兆し',
            3  => '新生活需要で相場上昇',
            4  => 'バイクシーズン本番、相場ピーク',
            5  => 'GW需要、乗り換え売却増',
            6  => '梅雨入りで需要減',
            7  => '梅雨明け後ツーリング需要',
            8  => '夏休みで需要堅調',
            9  => '決算期セール',
            10 => '秋のツーリングシーズン',
            11 => 'シーズン終盤、相場軟化',
            12 => 'ボーナスセール、年末在庫処分',
        };

        $systemPrompt = <<<'PROMPT'
あなたはMotoHubの中古バイク市場アナリストです。
月次データを元に市場レポートを書いてください。

ルール：
- 文体: ですます調、バイク初心者にもわかりやすく
- HTMLタグで出力（h3, p, ul, li, strong, a を使用）
- 本文は1000〜1500文字程度
- JSONで以下の形式のみ返してください。他のテキストは不要です:
{"title": "記事タイトル", "body": "記事本文HTML", "meta_description": "120文字以内の要約"}
PROMPT;

        $userPrompt = <<<PROMPT
以下の{$monthLabel}のデータを元に、月次市場レポートを書いてください。

## 全体サマリー
{$summaryText}

## 排気量別データ
{$dispText}

## メーカー別販売台数
{$makerText}

## 売れ筋車種TOP10
{$modelText}

## 価格帯別販売分布
{$priceText}

## 季節コンテキスト
{$monthLabel}の傾向: {$seasonalContext}

## 記事の要件
- タイトル: 【MotoHub調べ】{$monthLabel} 中古バイク市場レポート｜{最も特徴的なトレンドを一言で}
- 本文構成:
  1. 今月のサマリー（3行で市場全体の動きを要約）
  2. 排気量別トレンド（各排気量の動きを分析。季節要因も考慮）
  3. 注目車種ピックアップ（TOP10から3車種を選んで深掘り）
  4. 価格帯分析（どの価格帯が売れているか）
  5. 来月の見通し（季節要因から予測）
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(90)->post(self::API_ENDPOINT, [
            'model' => self::MODEL_ID,
            'max_tokens' => self::MAX_TOKENS,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (!$response->successful()) {
            throw new \RuntimeException("API error: {$response->status()} - {$response->body()}");
        }

        $body = $response->json();
        $text = $body['content'][0]['text'] ?? null;

        if (!$text) {
            Log::error('GenerateMonthlyMarketReport: API応答にtextなし', ['body' => $body]);
            return null;
        }

        return $this->parseJsonResponse($text);
    }

    private function parseJsonResponse(string $text): ?array
    {
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/i', '', $text);

        $decoded = json_decode(trim($text), true);

        if (!is_array($decoded) || empty($decoded['title']) || empty($decoded['body'])) {
            Log::error('GenerateMonthlyMarketReport: JSONパース失敗', ['raw' => $text]);
            return null;
        }

        return $decoded;
    }

    /**
     * データテーブルHTML（AI生成テキストの後に自動付与）
     */
    private function buildDataTables(
        array $summary,
        array $displacementData,
        array $makerData,
        $topModels,
        array $priceBands,
        string $monthLabel,
    ): string {
        $html = '';

        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $topModels->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        // 排気量別テーブル
        $html .= '<h3 class="text-lg font-bold text-gray-900 mt-8 mb-3 flex items-center gap-2">';
        $html .= '<span>🏍</span> 排気量別販売データ';
        $html .= '</h3>';
        $html .= '<div class="overflow-x-auto"><table class="w-full text-sm border-collapse">';
        $html .= '<thead><tr class="bg-gray-50">';
        $html .= '<th class="text-left p-2 border-b font-bold">排気量</th>';
        $html .= '<th class="text-right p-2 border-b font-bold">販売台数</th>';
        $html .= '<th class="text-right p-2 border-b font-bold">前月比</th>';
        $html .= '<th class="text-right p-2 border-b font-bold">平均価格</th>';
        $html .= '</tr></thead><tbody>';
        foreach ($displacementData as $d) {
            $changeColor = $d['change_rate'] >= 0 ? 'text-green-600' : 'text-red-500';
            $changeSign = $d['change_rate'] >= 0 ? '+' : '';
            $html .= '<tr class="border-b border-gray-100">';
            $html .= '<td class="p-2 font-bold">' . e($d['label']) . '</td>';
            $html .= '<td class="p-2 text-right">' . number_format($d['sold_count']) . '台</td>';
            $html .= '<td class="p-2 text-right ' . $changeColor . ' font-bold">' . $changeSign . $d['change_rate'] . '%</td>';
            $html .= '<td class="p-2 text-right">' . number_format($d['avg_price']) . '円</td>';
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';

        // メーカー別テーブル
        $html .= '<h3 class="text-lg font-bold text-gray-900 mt-8 mb-3 flex items-center gap-2">';
        $html .= '<span>🏭</span> メーカー別販売台数TOP5';
        $html .= '</h3>';
        $html .= '<div class="space-y-2">';
        $totalMaker = collect($makerData)->sum('sold_count');
        foreach ($makerData as $i => $m) {
            $rank = $i + 1;
            $pct = $totalMaker > 0 ? round($m['sold_count'] / $totalMaker * 100, 1) : 0;
            $medal = match ($rank) {
                1 => '🥇',
                2 => '🥈',
                3 => '🥉',
                default => $rank . '位',
            };
            $html .= '<div class="flex items-center gap-3 py-2 border-b border-gray-100">';
            $html .= '<span class="w-8 text-center font-bold">' . $medal . '</span>';
            $html .= '<span class="font-bold flex-1">' . e($m['name']) . '</span>';
            $html .= '<span class="font-bold text-blue-600">' . number_format($m['sold_count']) . '台</span>';
            $html .= '<span class="text-xs text-gray-400 w-12 text-right">' . $pct . '%</span>';
            $html .= '</div>';
        }
        $html .= '</div>';

        // 売れ筋TOP10
        $html .= '<h3 class="text-lg font-bold text-gray-900 mt-8 mb-3 flex items-center gap-2">';
        $html .= '<span>🏆</span> 売れ筋車種TOP10';
        $html .= '</h3>';
        $html .= '<div class="space-y-2">';
        foreach ($topModels->values() as $i => $row) {
            $rank = $i + 1;
            $m = $models->get($row->bike_model_id);
            if (!$m) continue;

            $name = e($m->name);
            $maker = e($m->manufacturer->name ?? '不明');
            $imgUrl = e($this->resolveImageUrl($m) ?? '');
            $modelUrl = e(route('bikes.model_detail.fallback', $row->bike_model_id));

            $medal = match ($rank) {
                1 => '🥇',
                2 => '🥈',
                3 => '🥉',
                default => null,
            };

            if ($rank <= 3) {
                $imgTag = $imgUrl
                    ? '<img src="' . $imgUrl . '" alt="' . $name . '" class="w-20 h-14 object-cover rounded" loading="lazy" onerror="this.style.display=\'none\'">'
                    : '';

                $html .= '<div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">';
                $html .= '<div class="text-xl font-bold">' . $medal . '</div>';
                if ($imgTag) {
                    $html .= '<a href="' . $modelUrl . '" class="block flex-shrink-0">' . $imgTag . '</a>';
                }
                $html .= '<div class="flex-1 min-w-0">';
                $html .= '<div><a href="' . $modelUrl . '" class="font-bold text-blue-700 hover:underline">' . $name . '</a></div>';
                $html .= '<div class="text-xs text-gray-500">' . $maker . '</div>';
                $html .= '</div>';
                $html .= '<div class="text-right flex-shrink-0">';
                $html .= '<div class="font-bold text-blue-600">' . number_format($row->sold_count) . '台</div>';
                $html .= '<div class="text-xs text-gray-500">平均' . number_format((int) $row->avg_price) . '円</div>';
                $html .= '</div>';
                $html .= '</div>';
            } else {
                $html .= '<div class="flex items-center gap-3 py-2 border-b border-gray-100">';
                $html .= '<span class="w-6 text-center font-bold text-gray-400 text-sm">' . $rank . '</span>';
                $html .= '<div class="flex-1 min-w-0">';
                $html .= '<a href="' . $modelUrl . '" class="font-bold text-blue-700 hover:underline text-sm">' . $name . '</a>';
                $html .= '<span class="text-gray-400 text-xs ml-1">' . $maker . '</span>';
                $html .= '</div>';
                $html .= '<div class="text-right flex-shrink-0">';
                $html .= '<span class="font-bold text-sm">' . number_format($row->sold_count) . '台</span>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }
        $html .= '</div>';

        // 価格帯別分布
        $html .= '<h3 class="text-lg font-bold text-gray-900 mt-8 mb-3 flex items-center gap-2">';
        $html .= '<span>💰</span> 価格帯別販売分布';
        $html .= '</h3>';
        $totalPrice = collect($priceBands)->sum('count');
        $html .= '<div class="space-y-2">';
        foreach ($priceBands as $p) {
            $pct = $totalPrice > 0 ? round($p['count'] / $totalPrice * 100, 1) : 0;
            $barWidth = min($pct * 2, 100);
            $html .= '<div class="flex items-center gap-3">';
            $html .= '<span class="w-20 text-sm font-bold text-gray-600 flex-shrink-0">' . e($p['label']) . '</span>';
            $html .= '<div class="flex-1 bg-gray-100 rounded-full h-5 overflow-hidden">';
            $html .= '<div class="bg-blue-500 h-full rounded-full" style="width: ' . $barWidth . '%"></div>';
            $html .= '</div>';
            $html .= '<span class="w-20 text-right text-sm"><strong>' . number_format($p['count']) . '</strong>台</span>';
            $html .= '<span class="w-12 text-right text-xs text-gray-400">' . $pct . '%</span>';
            $html .= '</div>';
        }
        $html .= '</div>';

        // フッター
        $html .= '<p class="text-xs text-gray-400 mt-6">※ MotoHubに掲載された中古バイクの販売データに基づく' . e($monthLabel) . 'の集計です。</p>';
        $html .= '<a href="' . route('bikes.trends') . '" class="inline-block mt-3 text-sm font-bold text-blue-600 hover:underline">相場変動ランキングの詳細はこちら →</a>';

        return $html;
    }

    private function resolveImageUrl(?BikeModel $model): ?string
    {
        if (!$model) return null;

        if (is_array($model->local_image_path) && !empty($model->local_image_path)) {
            return asset('storage/' . ltrim($model->local_image_path[0], '/'));
        }

        $imageUrl = $model->image_url;
        if ($imageUrl) return $imageUrl;

        if ($model->manufacturer) {
            if ($model->manufacturer->local_logo_path) {
                return asset('storage/' . ltrim($model->manufacturer->local_logo_path, '/'));
            }
            if ($model->manufacturer->logo_url) {
                return $model->manufacturer->logo_url;
            }
        }

        return null;
    }

    private function printDryRun(
        array $summary,
        array $displacementData,
        array $makerData,
        $topModels,
        array $priceBands,
        string $monthLabel,
    ): void {
        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $topModels->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        $this->info("=== {$monthLabel} データサマリー ===");
        $this->line("販売台数: {$summary['sold_count']}台（前月比 {$summary['sold_change_rate']}%）");
        $this->line("平均価格: " . number_format($summary['avg_price']) . "円（前月比 {$summary['price_change_rate']}%）");
        $this->line("新規掲載: {$summary['new_count']}台（前月比 {$summary['new_change_rate']}%）");

        $this->newLine();
        $this->info('--- 排気量別 ---');
        foreach ($displacementData as $d) {
            $this->line("  {$d['label']}: {$d['sold_count']}台（前月比 {$d['change_rate']}%）/ 平均" . number_format($d['avg_price']) . '円');
        }

        $this->newLine();
        $this->info('--- メーカーTOP5 ---');
        foreach ($makerData as $i => $m) {
            $this->line('  ' . ($i + 1) . "位 {$m['name']}: {$m['sold_count']}台");
        }

        $this->newLine();
        $this->info('--- 売れ筋TOP10 ---');
        foreach ($topModels->values() as $i => $row) {
            $m = $models->get($row->bike_model_id);
            $name = $m ? $m->name : '不明';
            $maker = $m?->manufacturer?->name ?? '不明';
            $this->line('  ' . ($i + 1) . "位 {$name}（{$maker}）: {$row->sold_count}台 / 平均" . number_format((int) $row->avg_price) . '円');
        }

        $this->newLine();
        $this->info('--- 価格帯別 ---');
        foreach ($priceBands as $p) {
            $this->line("  {$p['label']}: {$p['count']}台");
        }
    }
}
