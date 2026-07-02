<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\BikeNews;
use App\Models\Listing;
use App\Services\Bike\TrendService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GenerateWeeklyMarketReport extends Command
{
    protected $signature = 'news:generate-weekly-report
                            {--publish : 即時公開（デフォルトはdraft）}
                            {--force : 重複チェックをスキップ}
                            {--dry-run : APIを呼ばずデータ確認のみ}';

    protected $description = '先週の相場変動データを集計し、週間相場速報を自動生成';

    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const MAX_TOKENS = 2000;

    public function handle(TrendService $trendService): int
    {
        $apiKey = config('services.anthropic.api_key');
        $isDryRun = $this->option('dry-run');

        if (! $isDryRun && ! $apiKey) {
            $this->error('ANTHROPIC_API_KEY が .env に設定されていません。');

            return self::FAILURE;
        }

        // 先週の月〜日
        $weekStart = now()->subWeek()->startOfWeek();
        $weekEnd = now()->subWeek()->endOfWeek()->endOfDay();
        // 前々週
        $prevWeekStart = now()->subWeeks(2)->startOfWeek();
        $prevWeekEnd = now()->subWeeks(2)->endOfWeek()->endOfDay();

        $month = (int) $weekStart->format('n');
        $weekOfMonth = (int) ceil($weekStart->day / 7);
        $weekLabel = "{$month}月第{$weekOfMonth}週";
        $periodLabel = $weekStart->format('n/j').'〜'.now()->subWeek()->endOfWeek()->format('n/j');

        // 重複チェック
        if (! $this->option('force') && BikeNews::where('title', 'like', "%週間相場速報%{$weekLabel}%")->exists()) {
            $this->warn("既に{$weekLabel}の週間レポートが存在します。");

            return self::SUCCESS;
        }

        $this->info("=== {$weekLabel}（{$periodLabel}）週間相場速報生成 ===");

        // データ集計
        $this->info('データ集計中...');
        $summary = $this->collectSummary($weekStart, $weekEnd, $prevWeekStart, $prevWeekEnd);
        $trends = $trendService->getRanking(7);
        $topDrops = array_slice($trends['drop'] ?? [], 0, 3);
        $topRises = array_slice($trends['rise'] ?? [], 0, 3);
        $topModels = $this->collectTopModels($weekStart, $weekEnd);

        if ($isDryRun) {
            $this->printDryRun($summary, $topDrops, $topRises, $topModels, $weekLabel, $periodLabel);

            return self::SUCCESS;
        }

        if (empty($topDrops) && empty($topRises) && $topModels->isEmpty()) {
            $this->warn('データ不足のためスキップします。');

            return self::SUCCESS;
        }

        // Claude APIでレポート生成
        $this->info('Claude APIでレポート生成中...');

        try {
            $result = $this->callClaudeApi(
                $apiKey, $weekLabel, $periodLabel, $summary, $topDrops, $topRises, $topModels
            );
        } catch (\Throwable $e) {
            $this->error("API呼び出しエラー: {$e->getMessage()}");
            Log::error('GenerateWeeklyMarketReport: API呼び出し失敗', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        if ($result === null) {
            $this->error('Claude APIのレスポンスパースに失敗しました。');

            return self::FAILURE;
        }

        // HTML本文 = AI生成テキスト + データテーブル
        $content = $result['body'];
        $content .= $this->buildDataTables($topDrops, $topRises, $topModels, $periodLabel);

        // サムネイル
        $thumbnailUrl = $topDrops[0]['image_url'] ?? ($topRises[0]['image_url'] ?? null);
        $topModelId = $topDrops[0]['model_id'] ?? ($topRises[0]['model_id'] ?? null);

        $publishedAt = $this->option('publish') ? now() : null;

        $news = BikeNews::create([
            'title' => $result['title'],
            'url' => '',
            'source' => 'MotoHub',
            'content' => $content,
            'thumbnail_url' => $thumbnailUrl,
            'published_at' => $publishedAt,
            'bike_model_id' => $topModelId,
            'manufacturer_id' => null,
            'is_featured' => true,
        ]);

        $news->update(['url' => route('news.show', $news->id)]);

        $status = $publishedAt ? '公開' : '下書き';
        $this->info("記事を生成しました（{$status}）: {$result['title']}");

        return self::SUCCESS;
    }

    private function collectSummary(Carbon $start, Carbon $end, Carbon $prevStart, Carbon $prevEnd): array
    {
        $soldQuery = fn (Carbon $s, Carbon $e) => Listing::cappedSold($s, $e);

        $soldCount = $soldQuery($start, $end)->count();
        $prevSoldCount = $soldQuery($prevStart, $prevEnd)->count();

        $avgPrice = (int) $soldQuery($start, $end)->whereNotNull('total_price')->avg('total_price');
        $prevAvgPrice = (int) $soldQuery($prevStart, $prevEnd)->whereNotNull('total_price')->avg('total_price');

        return [
            'sold_count' => $soldCount,
            'prev_sold_count' => $prevSoldCount,
            'sold_change_rate' => $prevSoldCount > 0 ? round(($soldCount - $prevSoldCount) / $prevSoldCount * 100, 1) : 0,
            'avg_price' => $avgPrice,
            'prev_avg_price' => $prevAvgPrice,
            'price_change_rate' => $prevAvgPrice > 0 ? round(($avgPrice - $prevAvgPrice) / $prevAvgPrice * 100, 1) : 0,
        ];
    }

    private function collectTopModels(Carbon $start, Carbon $end)
    {
        return Listing::cappedSold($start, $end)
            ->whereNotNull('bike_model_id')
            ->select(
                'bike_model_id',
                DB::raw('COUNT(*) as sold_count'),
                DB::raw('ROUND(AVG(total_price)) as avg_price'),
            )
            ->groupBy('bike_model_id')
            ->orderByDesc('sold_count')
            ->limit(5)
            ->get();
    }

    private function callClaudeApi(
        string $apiKey,
        string $weekLabel,
        string $periodLabel,
        array $summary,
        array $topDrops,
        array $topRises,
        $topModels,
    ): ?array {
        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $topModels->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        $summaryText = "販売台数: {$summary['sold_count']}台（前週比 {$summary['sold_change_rate']}%）\n"
            .'平均販売価格: '.number_format($summary['avg_price'])."円（前週比 {$summary['price_change_rate']}%）";

        $dropText = collect($topDrops)->map(fn ($d) => "{$d['model_name']}（{$d['maker_name']}）: {$d['current_price']}万円（{$d['diff']}万円 / {$d['rate']}%）掲載{$d['count']}台"
        )->implode("\n");

        $riseText = collect($topRises)->map(fn ($r) => "{$r['model_name']}（{$r['maker_name']}）: {$r['current_price']}万円（+{$r['diff']}万円 / +{$r['rate']}%）掲載{$r['count']}台"
        )->implode("\n");

        $modelText = $topModels->values()->map(function ($row, $i) use ($models) {
            $m = $models->get($row->bike_model_id);
            $name = $m ? $m->name : '不明';
            $maker = $m?->manufacturer?->name ?? '不明';

            return ($i + 1)."位 {$name}（{$maker}）: {$row->sold_count}台 / 平均".number_format((int) $row->avg_price).'円';
        })->implode("\n");

        $systemPrompt = <<<'PROMPT'
あなたはMotoHubの中古バイク市場アナリストです。
週間データを元に相場速報を書いてください。

ルール：
- 文体: ですます調、簡潔に
- HTMLタグで出力（h3, p, strong, a を使用）
- 本文は300〜500文字程度（速報感を出す）
- 各車種名にはリンクを含めない（後からHTML側で付与する）
- JSONで以下の形式のみ返してください。他のテキストは不要です:
{"title": "記事タイトル", "body": "記事本文HTML", "meta_description": "120文字以内の要約"}
PROMPT;

        $userPrompt = <<<PROMPT
以下の先週（{$periodLabel}）のデータを元に、週間相場速報を書いてください。

## 全体サマリー
{$summaryText}

## 値下がり注目TOP3
{$dropText}

## 値上がり注目TOP3
{$riseText}

## 売れ筋TOP5
{$modelText}

## 記事の要件
- タイトル: 【週間相場速報】{$weekLabel}｜{最もインパクトのある変動を一言で}
- 本文構成:
  1. 今週のハイライト（2〜3行で要約）
  2. 値下がり注目（TOP3を簡潔に分析）
  3. 値上がり注目（TOP3を簡潔に分析）
  4. 今週の狙い目（値下がり車種から1つ推薦）
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

        $result = $response->json();
        $text = $result['content'][0]['text'] ?? null;

        if (! $text) {
            $this->error('Claude APIからのレスポンスが空です');
            $this->error('Response: '.json_encode($result));
            Log::error('GenerateWeeklyMarketReport: API応答にtextなし', ['body' => $result]);

            return null;
        }

        // JSONパース
        $clean = preg_replace('/```json|```/', '', $text);
        $data = json_decode(trim($clean), true);

        if (! $data || ! isset($data['title'])) {
            $this->error('レスポンスのJSONパースに失敗');
            $this->error('Text: '.$text);
            Log::error('GenerateWeeklyMarketReport: JSONパース失敗', ['raw' => $text]);

            return null;
        }

        return $data;
    }

    private function buildDataTables(array $drops, array $rises, $topModels, string $periodLabel): string
    {
        $html = '';

        // 値下がりTOP3
        if (! empty($drops)) {
            $html .= '<h3 class="text-lg font-bold text-gray-900 mt-6 mb-3 flex items-center gap-2">';
            $html .= '<span class="text-blue-500">📉</span> 値下がり注目TOP3';
            $html .= '</h3>';
            $html .= $this->buildTrendTable($drops, 'drop');
        }

        // 値上がりTOP3
        if (! empty($rises)) {
            $html .= '<h3 class="text-lg font-bold text-gray-900 mt-6 mb-3 flex items-center gap-2">';
            $html .= '<span class="text-red-500">📈</span> 値上がり注目TOP3';
            $html .= '</h3>';
            $html .= $this->buildTrendTable($rises, 'rise');
        }

        // 売れ筋TOP5
        if ($topModels->isNotEmpty()) {
            $models = BikeModel::with('manufacturer')
                ->whereIn('id', $topModels->pluck('bike_model_id'))
                ->get()
                ->keyBy('id');

            $html .= '<h3 class="text-lg font-bold text-gray-900 mt-6 mb-3 flex items-center gap-2">';
            $html .= '<span>🏆</span> 売れ筋TOP5';
            $html .= '</h3>';
            $html .= '<div class="space-y-2">';

            foreach ($topModels->values() as $i => $row) {
                $rank = $i + 1;
                $m = $models->get($row->bike_model_id);
                if (! $m) {
                    continue;
                }

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
                        ? '<img src="'.$imgUrl.'" alt="'.$name.'" class="w-20 h-14 object-cover rounded" loading="lazy" onerror="this.style.display=\'none\'">'
                        : '';

                    $html .= '<div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">';
                    $html .= '<div class="text-xl font-bold">'.$medal.'</div>';
                    if ($imgTag) {
                        $html .= '<a href="'.$modelUrl.'" class="block flex-shrink-0">'.$imgTag.'</a>';
                    }
                    $html .= '<div class="flex-1 min-w-0">';
                    $html .= '<div><a href="'.$modelUrl.'" class="font-bold text-blue-700 hover:underline">'.$name.'</a></div>';
                    $html .= '<div class="text-xs text-gray-500">'.$maker.'</div>';
                    $html .= '</div>';
                    $html .= '<div class="text-right flex-shrink-0">';
                    $html .= '<div class="font-bold text-blue-600">'.number_format($row->sold_count).'台</div>';
                    $html .= '<div class="text-xs text-gray-500">平均'.number_format((int) $row->avg_price).'円</div>';
                    $html .= '</div>';
                    $html .= '</div>';
                } else {
                    $html .= '<div class="flex items-center gap-3 py-2 border-b border-gray-100">';
                    $html .= '<span class="w-6 text-center font-bold text-gray-400 text-sm">'.$rank.'</span>';
                    $html .= '<div class="flex-1 min-w-0">';
                    $html .= '<a href="'.$modelUrl.'" class="font-bold text-blue-700 hover:underline text-sm">'.$name.'</a>';
                    $html .= '<span class="text-gray-400 text-xs ml-1">'.$maker.'</span>';
                    $html .= '</div>';
                    $html .= '<div class="text-right flex-shrink-0">';
                    $html .= '<span class="font-bold text-sm">'.number_format($row->sold_count).'台</span>';
                    $html .= '</div>';
                    $html .= '</div>';
                }
            }
            $html .= '</div>';
        }

        $html .= '<p class="text-xs text-gray-400 mt-6">※ MotoHubに掲載された中古バイクのデータに基づく'.e($periodLabel).'の集計です。</p>';
        $html .= '<a href="'.route('bikes.trends').'" class="inline-block mt-3 text-sm font-bold text-blue-600 hover:underline">詳細な相場変動ランキングはこちら →</a>';

        return $html;
    }

    private function buildTrendTable(array $items, string $type): string
    {
        $diffColor = $type === 'drop' ? 'text-blue-600' : 'text-red-600';
        $diffPrefix = $type === 'rise' ? '+' : '';

        $html = '<div class="overflow-x-auto"><table class="w-full text-sm border-collapse">';
        $html .= '<thead><tr class="bg-gray-50">';
        $html .= '<th class="text-left p-2 border-b font-bold">車種</th>';
        $html .= '<th class="text-right p-2 border-b font-bold">現在価格</th>';
        $html .= '<th class="text-right p-2 border-b font-bold">変動額</th>';
        $html .= '<th class="text-right p-2 border-b font-bold">変動率</th>';
        $html .= '</tr></thead><tbody>';

        foreach ($items as $item) {
            $name = e($item['model_name']);
            $maker = e($item['maker_name']);
            $modelUrl = e(route('bikes.model_detail.fallback', $item['model_id']));

            $html .= '<tr class="border-b border-gray-100">';
            $html .= '<td class="p-2"><a href="'.$modelUrl.'" class="font-bold text-blue-700 hover:underline">'.$name.'</a>';
            $html .= '<span class="text-gray-400 text-xs ml-1">'.$maker.'</span></td>';
            $html .= '<td class="p-2 text-right font-bold">'.$item['current_price'].'万円</td>';
            $html .= '<td class="p-2 text-right '.$diffColor.' font-bold">'.$diffPrefix.$item['diff'].'万円</td>';
            $html .= '<td class="p-2 text-right '.$diffColor.' font-bold">'.$diffPrefix.$item['rate'].'%</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';

        return $html;
    }

    private function resolveImageUrl(?BikeModel $model): ?string
    {
        if (! $model) {
            return null;
        }

        if (is_array($model->local_image_path) && ! empty($model->local_image_path)) {
            return asset('storage/'.ltrim($model->local_image_path[0], '/'));
        }

        $imageUrl = $model->image_url;
        if ($imageUrl) {
            return $imageUrl;
        }

        if ($model->manufacturer) {
            if ($model->manufacturer->local_logo_path) {
                return asset('storage/'.ltrim($model->manufacturer->local_logo_path, '/'));
            }
            if ($model->manufacturer->logo_url) {
                return $model->manufacturer->logo_url;
            }
        }

        return null;
    }

    private function printDryRun(
        array $summary,
        array $topDrops,
        array $topRises,
        $topModels,
        string $weekLabel,
        string $periodLabel,
    ): void {
        $models = BikeModel::with('manufacturer')
            ->whereIn('id', $topModels->pluck('bike_model_id'))
            ->get()
            ->keyBy('id');

        $this->info("=== {$weekLabel}（{$periodLabel}）データサマリー ===");
        $this->line("販売台数: {$summary['sold_count']}台（前週比 {$summary['sold_change_rate']}%）");
        $this->line('平均価格: '.number_format($summary['avg_price'])."円（前週比 {$summary['price_change_rate']}%）");

        $this->newLine();
        $this->info('--- 値下がりTOP3 ---');
        foreach ($topDrops as $d) {
            $this->line("  {$d['model_name']}（{$d['maker_name']}）: {$d['diff']}万円（{$d['rate']}%）現在{$d['current_price']}万円");
        }

        $this->newLine();
        $this->info('--- 値上がりTOP3 ---');
        foreach ($topRises as $r) {
            $this->line("  {$r['model_name']}（{$r['maker_name']}）: +{$r['diff']}万円（+{$r['rate']}%）現在{$r['current_price']}万円");
        }

        $this->newLine();
        $this->info('--- 売れ筋TOP5 ---');
        foreach ($topModels->values() as $i => $row) {
            $m = $models->get($row->bike_model_id);
            $name = $m ? $m->name : '不明';
            $maker = $m?->manufacturer?->name ?? '不明';
            $this->line('  '.($i + 1)."位 {$name}（{$maker}）: {$row->sold_count}台 / 平均".number_format((int) $row->avg_price).'円');
        }
    }
}
