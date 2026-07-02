<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeModel;
use App\Services\Bike\ModelContentDataService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GenerateModelContent extends Command
{
    protected $signature = 'models:generate-content
        {--chunk=10 : 処理する車種数}
        {--min-listings= : 最低在庫数（デフォルト5）}
        {--dry-run : APIを呼ばずデータ収集のみ表示}';

    protected $description = '車種モデルページのAI生成コンテンツを作成';

    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const MAX_TOKENS = 2500;

    private const SLEEP_SECONDS = 2;

    private const MIN_ACTIVE_COUNT = 5;

    public function handle(ModelContentDataService $dataService): int
    {
        $apiKey = config('services.anthropic.api_key');
        $isDryRun = $this->option('dry-run');
        $chunk = (int) $this->option('chunk');
        $minListings = (int) ($this->option('min-listings') ?? self::MIN_ACTIVE_COUNT);

        if (! $isDryRun && ! $apiKey) {
            $this->error('ANTHROPIC_API_KEY が .env に設定されていません。');

            return self::FAILURE;
        }

        // enriched_contentがNULLの車種を、販売中台数が多い順に取得
        $models = BikeModel::whereNull('enriched_content')
            ->withCount(['listings' => fn ($q) => $q->where('is_sold_out', false)])
            ->having('listings_count', '>=', $minListings)
            ->where('name', '!=', '他車種')
            ->orderByDesc('listings_count')
            ->limit($chunk)
            ->get();

        if ($models->isEmpty()) {
            $this->info('処理対象の車種がありません。');

            return self::SUCCESS;
        }

        $total = $models->count();
        $completed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($models as $index => $model) {
            $num = $index + 1;
            $prefix = "[{$num}/{$total}] {$model->name}";

            // データ収集
            try {
                $data = $dataService->collect($model->id);
            } catch (\Throwable $e) {
                $this->error("{$prefix}: データ収集エラー - {$e->getMessage()}");
                Log::error('GenerateModelContent: データ収集失敗', [
                    'model_id' => $model->id,
                    'error' => $e->getMessage(),
                ]);
                $errors++;

                continue;
            }

            // 販売中台数が少ない場合はスキップ
            if ($data['active_count'] < $minListings) {
                $this->warn("{$prefix}: 販売中 {$data['active_count']}台 → スキップ");
                $skipped++;

                continue;
            }

            $this->info("{$prefix}: データ収集OK（販売中 {$data['active_count']}台）");

            if ($isDryRun) {
                $this->line(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                $completed++;

                continue;
            }

            // Claude API呼び出し
            try {
                $content = $this->callClaudeApi($apiKey, $data);
            } catch (\Throwable $e) {
                $this->error("{$prefix}: API呼び出しエラー - {$e->getMessage()}");
                Log::error('GenerateModelContent: API呼び出し失敗', [
                    'model_id' => $model->id,
                    'error' => $e->getMessage(),
                ]);
                $errors++;

                continue;
            }

            if ($content === null) {
                $this->error("{$prefix}: レスポンスのパースに失敗");
                $errors++;

                continue;
            }

            // 保存
            $model->update([
                'enriched_content' => $content,
                'content_generated_at' => now(),
            ]);

            $this->info("{$prefix}: 保存完了");
            $completed++;

            // レート制限対策
            if ($index < $total - 1) {
                sleep(self::SLEEP_SECONDS);
            }
        }

        $this->newLine();
        $this->info("完了: {$completed}車種のコンテンツを生成しました（スキップ: {$skipped}件、エラー: {$errors}件）");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function callClaudeApi(string $apiKey, array $data): ?array
    {
        $systemPrompt = <<<'PROMPT'
あなたはバイク雑誌のベテラン編集者です。
中古バイクの購入を検討しているユーザー向けに、
実用的で具体的な車種紹介コンテンツを書いてください。

ルール：
- 提供された市場データに基づく事実のみを書く
- データには必ず「つまり〜」「これは〜を意味する」等の解釈を添える
- 「圧倒的」「抜群」「魅力的」等の曖昧な形容詞は使わない
- 具体的な数字を使い、その数字が意味することを説明する
- 「です・ます」調で統一
- 推測や不確かな情報は絶対に書かない
PROMPT;

        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE);

        $userPrompt = <<<PROMPT
以下のデータに基づいて、{$data['model_name']}の紹介コンテンツを生成してください。

【車種データ】
{$dataJson}

以下のJSON形式で出力してください。各valueは日本語テキスト（HTMLタグなし）です。
他の文字は一切含めず、JSONのみを出力してください。

{
  "introduction": "（150〜200字）この車種が中古市場でどういう存在かを、データで説明する。流通台数・平均価格・年式幅を織り込みつつ、『つまり〇〇ということ』という解釈を必ず入れる。メーカー名・排気量・カテゴリは冒頭で自然に触れる。",
  "market_insight": "（120〜180字）市場データの読み解き。売却日数が同クラス平均と比べて速いか遅いか、価格が上がってるか下がってるか、どの地域に在庫が集中してるか。数字を出した上で『これは〇〇を意味します』と解釈する。",
  "target_rider": "（100〜150字）データから逆算したおすすめライダー像。平均価格帯・走行距離・年式から『初めてのバイクに向いている』『ベテランのセカンドバイクに最適』等の具体的な提案。抽象的な『バイク好きな方に』は禁止。",
  "buying_tips": "（120〜180字）このデータから読み取れる、この車種特有の中古購入アドバイス。平均走行距離・最頻年式・価格帯を根拠に、『〇〇km以下の個体を狙うなら予算〇〇万円は見ておきたい』のような具体的なアドバイス。一般的なバイク購入の注意点は不要。",
  "rivals": "（80〜120字）同クラスモデルとの比較。same_class_modelsのデータを使い、『〇〇は△△万円で選択肢に入るが、本車種は□□の点で異なる』のように書く。ライバルがいない場合はカテゴリ内でのポジションを説明。"
}
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
            Log::error('GenerateModelContent: API応答にtextなし', ['body' => $body]);

            return null;
        }

        return $this->parseJsonResponse($text);
    }

    private function parseJsonResponse(string $text): ?array
    {
        // ```json ... ``` フェンスを除去
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/i', '', $text);

        $decoded = json_decode(trim($text), true);

        if (! is_array($decoded)) {
            Log::error('GenerateModelContent: JSONパース失敗', ['raw' => $text]);

            return null;
        }

        // 必須キーの検証
        $required = ['introduction', 'market_insight', 'target_rider', 'buying_tips'];
        foreach ($required as $key) {
            if (empty($decoded[$key])) {
                Log::error("GenerateModelContent: 必須キー '{$key}' が空", ['decoded' => $decoded]);

                return null;
            }
        }

        return $decoded;
    }
}
