<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Models\BikeNews;
use App\Services\Bike\TrendService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GenerateMarketReport extends Command
{
    protected $signature = 'news:generate-market-report
                            {--publish : 即時公開（デフォルトはdraft）}
                            {--force : 重複チェックをスキップ}
                            {--dry-run : APIを呼ばずデータ確認のみ}';

    protected $description = '相場変動データからマーケットレポートニュースを自動生成';

    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const MODEL_ID = 'claude-sonnet-4-20250514';
    private const MAX_TOKENS = 3000;

    public function handle(TrendService $trendService): int
    {
        $apiKey = config('services.anthropic.api_key');
        $isDryRun = $this->option('dry-run');

        if (!$isDryRun && !$apiKey) {
            $this->error('ANTHROPIC_API_KEY が .env に設定されていません。');
            return self::FAILURE;
        }

        $this->info('相場データを取得中...');
        $trends = $trendService->getRanking(30);

        $drops = $trends['drop'] ?? [];
        $rises = $trends['rise'] ?? [];
        $period = $trends['period'] ?? [];

        if (empty($drops) && empty($rises)) {
            $this->warn('相場変動データがありません。スキップします。');
            return self::SUCCESS;
        }

        $periodFrom = $period['from'] ?? now()->subDays(30)->format('Y年m月d日');
        $periodTo = $period['to'] ?? now()->format('Y年m月d日');

        $title = '【相場速報】' . Carbon::now()->format('Y年n月') . ' バイク中古相場レポート｜値下がり・高騰車種まとめ';

        // 重複チェック
        if (!$this->option('force') && BikeNews::where('title', $title)->exists()) {
            $this->warn("既に同じタイトルの記事が存在します: {$title}");
            return self::SUCCESS;
        }

        $topDrops = array_slice($drops, 0, 10);
        $topRises = array_slice($rises, 0, 10);

        $this->info('値下がりTOP: ' . count($topDrops) . '車種');
        $this->info('高騰TOP: ' . count($topRises) . '車種');

        if ($isDryRun) {
            $this->info('--- 値下がりTOP ---');
            foreach ($topDrops as $d) {
                $this->line("  {$d['model_name']} ({$d['maker_name']}): {$d['diff']}万円 ({$d['rate']}%)");
            }
            $this->info('--- 高騰TOP ---');
            foreach ($topRises as $r) {
                $this->line("  {$r['model_name']} ({$r['maker_name']}): +{$r['diff']}万円 (+{$r['rate']}%)");
            }
            return self::SUCCESS;
        }

        // Claude APIで考察テキスト生成
        $this->info('Claude APIで分析テキストを生成中...');

        try {
            $analysis = $this->callClaudeApi($apiKey, $topDrops, $topRises, $periodFrom, $periodTo);
        } catch (\Throwable $e) {
            $this->error("API呼び出しエラー: {$e->getMessage()}");
            Log::error('GenerateMarketReport: API呼び出し失敗', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }

        if ($analysis === null) {
            $this->error('Claude APIのレスポンスパースに失敗しました。');
            return self::FAILURE;
        }

        // HTML本文を構築
        $content = $this->buildContent($topDrops, $topRises, $analysis, $periodFrom, $periodTo);

        // サムネイル: 値下がり1位の車種画像
        $thumbnailUrl = $topDrops[0]['image_url'] ?? ($topRises[0]['image_url'] ?? null);

        // 関連車種: 値下がり1位
        $topModelId = $topDrops[0]['model_id'] ?? ($topRises[0]['model_id'] ?? null);

        $publishedAt = $this->option('publish') ? now() : null;

        BikeNews::create([
            'title'           => $title,
            'url'             => route('bikes.trends'),
            'source'          => 'MotoHub',
            'content'         => $content,
            'thumbnail_url'   => $thumbnailUrl,
            'published_at'    => $publishedAt,
            'bike_model_id'   => $topModelId,
            'manufacturer_id' => null,
            'is_featured'     => true,
        ]);

        $status = $publishedAt ? '公開' : '下書き';
        $this->info("記事を生成しました（{$status}）: {$title}");

        return self::SUCCESS;
    }

    private function callClaudeApi(string $apiKey, array $drops, array $rises, string $periodFrom, string $periodTo): ?string
    {
        $systemPrompt = <<<'PROMPT'
あなたはバイク中古市場の専門アナリストです。
相場変動データを元に、簡潔で読みやすい市場レポートの考察テキストを日本語で書いてください。

ルール：
- 300〜500文字程度で簡潔に
- 値下がり・高騰の要因を推察する（季節要因、新型発売、人気トレンドなど）
- バイク初心者にもわかりやすい表現を使う
- 「買い時」「売り時」などの具体的なアドバイスを1つ含める
- HTMLタグは使わない。プレーンテキストで出力する
PROMPT;

        $dropsSummary = collect($drops)->map(fn ($d) =>
            "{$d['model_name']}（{$d['maker_name']}）: {$d['current_price']}万円（{$d['diff']}万円 / {$d['rate']}%）掲載{$d['count']}台"
        )->implode("\n");

        $risesSummary = collect($rises)->map(fn ($r) =>
            "{$r['model_name']}（{$r['maker_name']}）: {$r['current_price']}万円（+{$r['diff']}万円 / +{$r['rate']}%）掲載{$r['count']}台"
        )->implode("\n");

        $userPrompt = <<<PROMPT
以下のバイク中古相場データ（{$periodFrom}〜{$periodTo}の変動）を元に、市場レポートの考察テキストを書いてください。
テキストのみ出力してください。

【値下がりTOP】
{$dropsSummary}

【高騰TOP】
{$risesSummary}
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post(self::API_ENDPOINT, [
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
            Log::error('GenerateMarketReport: API応答にtextなし', ['body' => $body]);
            return null;
        }

        return trim($text);
    }

    private function buildContent(array $drops, array $rises, string $analysis, string $periodFrom, string $periodTo): string
    {
        $html = '<div class="market-report">';

        // 期間・導入
        $html .= '<p class="text-sm text-gray-600 mb-4">';
        $html .= e($periodFrom) . '〜' . e($periodTo) . 'の中古バイク相場変動をまとめました。';
        $html .= '</p>';

        // AI考察
        $html .= '<div class="bg-blue-50 rounded-lg p-4 mb-6">';
        $html .= '<div class="flex items-center gap-2 mb-2">';
        $html .= '<span class="text-lg">📊</span>';
        $html .= '<span class="font-bold text-gray-800">今月の相場トレンド</span>';
        $html .= '</div>';
        $html .= '<p class="text-sm text-gray-700 leading-relaxed">' . nl2br(e($analysis)) . '</p>';
        $html .= '</div>';

        // 値下がりランキング
        if (!empty($drops)) {
            $html .= '<h3 class="text-lg font-bold text-gray-900 mb-3 flex items-center gap-2">';
            $html .= '<span class="text-blue-500">📉</span> 値下がりランキングTOP' . count($drops);
            $html .= '</h3>';
            $html .= $this->buildRankingTable($drops, 'drop');
        }

        // 高騰ランキング
        if (!empty($rises)) {
            $html .= '<h3 class="text-lg font-bold text-gray-900 mb-3 mt-6 flex items-center gap-2">';
            $html .= '<span class="text-red-500">📈</span> 高騰ランキングTOP' . count($rises);
            $html .= '</h3>';
            $html .= $this->buildRankingTable($rises, 'rise');
        }

        $html .= '<p class="text-xs text-gray-400 mt-4">※ MotoHubに掲載された中古バイクの平均相場データに基づく集計です。</p>';
        $html .= '<a href="' . route('bikes.trends') . '" class="inline-block mt-3 text-sm font-bold text-blue-600 hover:underline">相場変動ランキングの詳細はこちら →</a>';
        $html .= '</div>';

        return $html;
    }

    private function buildRankingTable(array $items, string $type): string
    {
        $html = '<div class="space-y-2 mb-4">';

        foreach ($items as $i => $item) {
            $rank = $i + 1;
            $name = e($item['model_name']);
            $maker = e($item['maker_name']);
            $price = $item['current_price'];
            $diff = $item['diff'];
            $rate = $item['rate'];
            $count = $item['count'];
            $imgUrl = e($item['image_url'] ?? '');

            $modelUrl = e(route('bikes.model_detail.fallback', $item['model_id']));

            $diffColor = $type === 'drop' ? 'text-blue-600' : 'text-red-600';
            $diffPrefix = $type === 'rise' ? '+' : '';
            $ratePrefix = $type === 'rise' ? '+' : '';

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
                $html .= '<div class="text-xs text-gray-500">' . $maker . ' / 掲載' . $count . '台</div>';
                $html .= '</div>';
                $html .= '<div class="text-right flex-shrink-0">';
                $html .= '<div class="font-bold">' . $price . '万円</div>';
                $html .= '<div class="text-sm ' . $diffColor . ' font-bold">' . $diffPrefix . $diff . '万円（' . $ratePrefix . $rate . '%）</div>';
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
                $html .= '<span class="font-bold text-sm">' . $price . '万円</span>';
                $html .= '<span class="text-xs ' . $diffColor . ' font-bold ml-1">' . $diffPrefix . $diff . '万</span>';
                $html .= '</div>';
                $html .= '</div>';
            }
        }

        $html .= '</div>';
        return $html;
    }
}
