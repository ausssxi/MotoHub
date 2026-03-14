<?php

declare(strict_types=1);

namespace App\Http\Controllers\Bike;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BikeIdentifierController extends Controller
{
    /**
     * 車種判定ページ表示
     */
    public function index(): View
    {
        return view('bikes.identify');
    }

    /**
     * 画像からバイク車種を判定
     */
    public function identify(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:10240', // 10MB
        ]);

        $apiKey = config('services.anthropic.api_key');
        if (empty($apiKey)) {
            return response()->json(['error' => 'APIキーが設定されていません。'], 500);
        }

        $image = $request->file('image');
        $base64 = base64_encode(file_get_contents($image->getRealPath()));
        $mediaType = $image->getMimeType();

        $systemPrompt = 'あなたはバイク専門家AIです。画像を見てバイクの情報をJSON形式のみで返してください。'
            . 'バイクが写っていない場合は {"error": "バイクが見つかりませんでした"} を返してください。'
            . '形式：{"maker":"メーカー英語名","maker_jp":"メーカー日本語名","model":"車種名","year":"推定年式","category":"カテゴリ","displacement":"排気量","confidence":"高 or 中 or 低","features":["特徴1","特徴2","特徴3"],"comment":"一言コメント"}'
            . "\n注意事項：\n・似たバイクではなく、正確な車種名を答えること\n・日本市場で販売されたモデル名を優先すること\n・自信がない場合はconfidenceを「低」にして正直に答えること";

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->timeout(30)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-sonnet-4-20250514',
                'max_tokens' => 1024,
                'system' => $systemPrompt,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image',
                                'source' => [
                                    'type' => 'base64',
                                    'media_type' => $mediaType,
                                    'data' => $base64,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => 'この画像に写っているバイクの車種を判定してください。',
                            ],
                        ],
                    ],
                ],
            ]);

            if ($response->failed()) {
                Log::error('Anthropic API error', ['status' => $response->status(), 'body' => $response->body()]);
                return response()->json(['error' => 'AI判定に失敗しました。しばらくしてから再度お試しください。'], 502);
            }

            $body = $response->json();
            $text = $body['content'][0]['text'] ?? '';

            // JSONブロックを抽出（```json...``` やプレーンJSON対応）
            if (preg_match('/\{[\s\S]*\}/', $text, $matches)) {
                $result = json_decode($matches[0], true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return response()->json($result);
                }
            }

            return response()->json(['error' => '判定結果の解析に失敗しました。別の画像でお試しください。'], 422);
        } catch (\Exception $e) {
            Log::error('Bike identifier exception', ['message' => $e->getMessage()]);
            return response()->json(['error' => 'AI判定中にエラーが発生しました。'], 500);
        }
    }
}
