<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RoadsideStation;
use App\Models\TouringSpot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class TouringPlannerController extends Controller
{
    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const MAX_TOKENS = 3000;

    public function suggest(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:-90,90',
            'lng' => 'required|numeric|between:-180,180',
            'distance_km' => 'required|integer|in:100,200,300',
            'preference' => 'required|string|in:sea,mountain,onsen,any',
            'origin_name' => 'nullable|string|max:100',
        ]);

        $apiKey = config('services.anthropic.api_key');
        if (! $apiKey) {
            return response()->json(['error' => 'API設定がありません'], 500);
        }

        $lat = (float) $validated['lat'];
        $lng = (float) $validated['lng'];
        $distanceKm = (int) $validated['distance_km'];
        $preference = $validated['preference'];
        $originName = $validated['origin_name'] ?? "{$lat},{$lng}付近";

        // 検索半径: 総距離の1/4 を度数に変換
        $radiusKm = $distanceKm / 4;
        $degree = $radiusKm / 111.0;

        $spots = TouringSpot::whereBetween('lat', [$lat - $degree, $lat + $degree])
            ->whereBetween('lng', [$lng - $degree, $lng + $degree])
            ->select('name', 'lat', 'lng', 'description', 'difficulty', 'distance_km')
            ->limit(15)
            ->get();

        // 座標NULLの駅を明示除外（whereBetween の NULL 落ちに依存させない）。:99 の座標文字列連結の安全も担保。
        $stations = RoadsideStation::whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$lat - $degree, $lat + $degree])
            ->whereBetween('longitude', [$lng - $degree, $lng + $degree])
            ->select('name', 'latitude', 'longitude', 'address', 'has_restaurant', 'has_onsen', 'has_gas_station')
            ->limit(15)
            ->get();

        try {
            $result = $this->callClaudeApi($apiKey, $lat, $lng, $originName, $distanceKm, $preference, $spots, $stations);
        } catch (\Throwable $e) {
            Log::error('TouringPlanner: API呼び出し失敗', ['error' => $e->getMessage()]);

            return response()->json(['error' => 'ルート提案の生成に失敗しました。しばらくしてから再度お試しください。'], 500);
        }

        if ($result === null) {
            return response()->json(['error' => 'ルート提案の解析に失敗しました。'], 500);
        }

        return response()->json($result);
    }

    private function callClaudeApi(
        string $apiKey,
        float $lat,
        float $lng,
        string $originName,
        int $distanceKm,
        string $preference,
        $spots,
        $stations,
    ): ?array {
        $preferenceMap = [
            'sea' => '海沿い・海岸線ルート重視',
            'mountain' => '山岳・峠道ルート重視',
            'onsen' => '温泉立ち寄り重視',
            'any' => '特にこだわりなし（バランス重視）',
        ];
        $preferenceText = $preferenceMap[$preference] ?? $preferenceMap['any'];

        $spotList = $spots->map(fn ($s) => "- {$s->name}（{$s->lat},{$s->lng}）{$s->description}")->implode("\n");
        $stationList = $stations->map(function ($s) {
            $facilities = collect([
                $s->has_restaurant ? 'レストラン' : null,
                $s->has_onsen ? '温泉' : null,
                $s->has_gas_station ? 'GS' : null,
            ])->filter()->implode('/');

            return "- {$s->name}（{$s->latitude},{$s->longitude}）{$s->address}".($facilities ? " [{$facilities}]" : '');
        })->implode("\n");

        $systemPrompt = <<<'PROMPT'
あなたはバイクツーリングのベテランプランナーです。
ライダーが1日で楽しめるツーリングルートを提案してください。
出力はJSON形式のみ。説明文やマークダウンは不要です。
ルートは出発地→各スポット→出発地に戻る周回ルートを基本とします。
道の駅は休憩・食事ポイントとして組み込んでください。
PROMPT;

        $userPrompt = <<<PROMPT
出発地: {$originName}（{$lat},{$lng}）
条件: 1日ツーリング、総距離約{$distanceKm}km、{$preferenceText}

周辺のツーリングスポット:
{$spotList}

周辺の道の駅:
{$stationList}

上記のスポット・道の駅から適切なものを選び、以下のJSON形式でルートを1つ提案してください。
スポットが少ない場合は、あなたの知識で追加の立ち寄りポイントを補完してください。
他の文字は一切含めず、JSONのみを出力してください。

{
  "title": "ルート名（日本語、魅力的な名前）",
  "description": "ルートの概要（100〜150字、見どころを具体的に）",
  "total_distance_km": 走行距離の概算（整数）,
  "estimated_hours": 所要時間の概算（小数、休憩込み）,
  "stops": [
    {
      "name": "出発地",
      "lat": {$lat},
      "lng": {$lng},
      "type": "waypoint",
      "note": "出発",
      "stay_minutes": 0
    },
    {
      "name": "スポット名",
      "lat": 緯度,
      "lng": 経度,
      "type": "touring_spot または roadside_station または waypoint",
      "note": "立ち寄りの楽しみ方やおすすめポイント（30〜50字）",
      "stay_minutes": 滞在目安（分）
    }
  ]
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
            Log::error('TouringPlanner: API応答にtextなし', ['body' => $body]);

            return null;
        }

        return $this->parseJsonResponse($text);
    }

    private function parseJsonResponse(string $text): ?array
    {
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/i', '', $text);

        $decoded = json_decode(trim($text), true);

        if (! is_array($decoded) || empty($decoded['title']) || empty($decoded['stops'])) {
            Log::error('TouringPlanner: JSONパース失敗', ['raw' => $text]);

            return null;
        }

        return $decoded;
    }
}
