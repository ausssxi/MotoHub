<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Bike\ListingResource;
use App\Models\Listing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

final class AiSearchController extends Controller
{
    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const MODEL_ID = 'claude-sonnet-4-20250514';

    public function index(): View
    {
        return view('ai-search.index', [
            'title' => 'AIスマート検索 | MotoHub',
            'metaDescription' => '自然言語でバイクを検索。予算・排気量・エリアなどを自由に入力するだけで、AIがあなたにぴったりの中古バイクを提案します。',
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|max:200',
        ]);

        $apiKey = config('services.anthropic.api_key');
        if (! $apiKey) {
            return response()->json(['error' => 'API設定がありません'], 500);
        }

        // STEP 1: 自然言語から検索条件を抽出
        try {
            $conditions = $this->extractConditions($apiKey, $validated['query']);
        } catch (\Throwable $e) {
            Log::error('AiSearch STEP1: 条件抽出失敗', ['error' => $e->getMessage()]);
            return response()->json(['error' => '検索条件の解析に失敗しました。もう少し具体的に入力してみてください。'], 500);
        }

        if ($conditions === null) {
            return response()->json(['error' => '検索条件を読み取れませんでした。別の表現でお試しください。'], 422);
        }

        // STEP 2: DB検索
        $query = Listing::query()->where('is_sold_out', false);

        if (! empty($conditions['max_price'])) {
            $query->where('total_price', '<=', (int) $conditions['max_price']);
        }
        if (! empty($conditions['min_price'])) {
            $query->where('total_price', '>=', (int) $conditions['min_price']);
        }
        if (! empty($conditions['max_displacement'])) {
            $query->where('displacement', '<=', (int) $conditions['max_displacement']);
        }
        if (! empty($conditions['min_displacement'])) {
            $query->where('displacement', '>=', (int) $conditions['min_displacement']);
        }
        if (! empty($conditions['max_mileage'])) {
            $query->where('mileage', '<=', (int) $conditions['max_mileage']);
        }
        if (! empty($conditions['min_model_year'])) {
            $query->where('model_year', '>=', (int) $conditions['min_model_year']);
        }
        if (! empty($conditions['prefecture'])) {
            $query->whereHas('shop', fn ($q) => $q->where('prefecture', $conditions['prefecture']));
        }
        if (! empty($conditions['manufacturer'])) {
            $query->whereHas('bikeModel.manufacturer', fn ($q) => $q->where('name', 'LIKE', "%{$conditions['manufacturer']}%"));
        }
        if (! empty($conditions['model_name'])) {
            $query->whereHas('bikeModel', fn ($q) => $q->where('name', 'LIKE', "%{$conditions['model_name']}%"));
        }

        $totalCount = (clone $query)->count();

        $listings = $query
            ->with(['bikeModel.manufacturer', 'shop', 'site'])
            ->orderByDesc('view_count_today')
            ->limit(3)
            ->get();

        $results = ListingResource::collection($listings)->resolve();

        // STEP 3: AIアドバイス生成
        $advice = null;
        try {
            $advice = $this->generateAdvice($apiKey, $validated['query'], $conditions, $results, $totalCount);
        } catch (\Throwable $e) {
            Log::error('AiSearch STEP3: アドバイス生成失敗', ['error' => $e->getMessage()]);
        }

        $searchUrl = $this->buildSearchUrl($conditions);

        return response()->json([
            'conditions' => $conditions,
            'results' => $results,
            'total_count' => $totalCount,
            'advice' => $advice ?? 'おすすめのバイクが見つかりました！詳細は各車両ページでご確認ください。',
            'search_url' => $searchUrl,
        ]);
    }

    private function extractConditions(string $apiKey, string $userQuery): ?array
    {
        $systemPrompt = <<<'PROMPT'
あなたはバイク検索アシスタントです。ユーザーの自然言語入力から検索条件を抽出してください。
出力はJSON形式のみ。説明文やマークダウンは不要です。
該当しない項目はnullにしてください。価格は円単位（例: 30万円→300000）、排気量はcc単位で出力してください。
都道府県名は「東京都」「神奈川県」のように正式名称で出力してください。

{
  "max_price": 上限価格（円）or null,
  "min_price": 下限価格（円）or null,
  "max_displacement": 排気量上限（cc）or null,
  "min_displacement": 排気量下限（cc）or null,
  "max_mileage": 走行距離上限（km）or null,
  "min_model_year": 年式下限（西暦）or null,
  "prefecture": 都道府県名 or null,
  "manufacturer": メーカー名 or null,
  "model_name": 車種名 or null,
  "summary": "抽出した条件の日本語要約（1文）"
}
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(30)->post(self::API_ENDPOINT, [
            'model' => self::MODEL_ID,
            'max_tokens' => 300,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userQuery],
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("API error: {$response->status()} - {$response->body()}");
        }

        $text = $response->json('content.0.text');
        if (! $text) {
            return null;
        }

        return $this->parseJsonResponse($text);
    }

    private function generateAdvice(string $apiKey, string $userQuery, array $conditions, array $results, int $totalCount): ?string
    {
        $resultSummary = collect($results)->map(function ($r) {
            return "- {$r['name']}（{$r['total_price']}万円、{$r['mileage']}、{$r['prefecture']}）";
        })->implode("\n");

        $systemPrompt = <<<'PROMPT'
あなたはバイク選びのアドバイザーです。検索結果を踏まえて、ユーザーに簡潔なアドバイスを返してください。
マークダウンは使用せず、プレーンテキストで2〜3文で回答してください。
PROMPT;

        $userPrompt = <<<PROMPT
ユーザーの検索: {$userQuery}
ヒット件数: {$totalCount}件
上位結果:
{$resultSummary}

上記を踏まえて、このユーザーへの簡潔なアドバイスをお願いします。
PROMPT;

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(30)->post(self::API_ENDPOINT, [
            'model' => self::MODEL_ID,
            'max_tokens' => 500,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("API error: {$response->status()} - {$response->body()}");
        }

        return $response->json('content.0.text');
    }

    private function buildSearchUrl(array $conditions): string
    {
        $params = [];

        if (! empty($conditions['max_price'])) {
            $params['price_max'] = (int) ($conditions['max_price'] / 10000);
        }
        if (! empty($conditions['min_price'])) {
            $params['price_min'] = (int) ($conditions['min_price'] / 10000);
        }
        if (! empty($conditions['max_displacement'])) {
            $params['displacement_max'] = $conditions['max_displacement'];
        }
        if (! empty($conditions['min_displacement'])) {
            $params['displacement_min'] = $conditions['min_displacement'];
        }
        if (! empty($conditions['max_mileage'])) {
            $params['mileage_max'] = $conditions['max_mileage'];
        }
        if (! empty($conditions['min_model_year'])) {
            $params['year_min'] = $conditions['min_model_year'];
        }
        if (! empty($conditions['prefecture'])) {
            $params['prefecture'] = $conditions['prefecture'];
        }
        if (! empty($conditions['manufacturer'])) {
            $params['keyword'] = $conditions['manufacturer'];
        }
        if (! empty($conditions['model_name'])) {
            $params['keyword'] = $conditions['model_name'];
        }

        return '/bikes/search?' . http_build_query($params);
    }

    private function parseJsonResponse(string $text): ?array
    {
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/i', '', $text);

        $decoded = json_decode(trim($text), true);

        if (! is_array($decoded)) {
            Log::error('AiSearch: JSONパース失敗', ['raw' => $text]);
            return null;
        }

        return $decoded;
    }
}
