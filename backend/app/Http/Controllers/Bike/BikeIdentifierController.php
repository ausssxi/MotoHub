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

        // 画像をリサイズ・圧縮（API送信サイズ削減）
        $imageData = file_get_contents($request->file('image')->getRealPath());
        $srcImage = @imagecreatefromstring($imageData);
        if (!$srcImage) {
            return response()->json(['error' => '画像の読み込みに失敗しました。別の画像でお試しください。'], 422);
        }

        $width = imagesx($srcImage);
        $height = imagesy($srcImage);
        $maxSize = 1600;

        if ($width > $height && $width > $maxSize) {
            $newWidth = $maxSize;
            $newHeight = (int) ($height * $maxSize / $width);
        } elseif ($height > $maxSize) {
            $newHeight = $maxSize;
            $newWidth = (int) ($width * $maxSize / $height);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($resized, $srcImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($srcImage);

        ob_start();
        imagejpeg($resized, null, 75);
        $compressed = ob_get_clean();
        imagedestroy($resized);

        $base64 = base64_encode($compressed);
        $mediaType = 'image/jpeg';

        $systemPrompt = <<<'PROMPT'
あなたは日本のバイク専門家AIです。画像を見てバイクの車種を特定してください。

重要なルール：
- 日本市場で販売されたモデル名を優先すること
- 似ているバイクではなく、視認できる特徴（タンク形状・フレーム・エンジン・ホイール・ライト形状）から正確に判定すること
- 角度が悪い・背景が写っている場合でも、見える部品から最善の推定をすること
- 必ず候補を最大3つ挙げ、candidatesに確度付きで記載すること
- 自信がない場合はconfidenceを「低」にすること
- 絶対に似ているだけで断定しないこと

バイクが写っていない場合は {"error": "バイクが見つかりませんでした"} を返してください。

JSON形式のみで返してください。形式：
{"maker":"最有力メーカー英語名","maker_jp":"最有力メーカー日本語名","model":"最有力車種名","confidence":"高 or 中 or 低","candidates":[{"maker":"メーカー1","model":"車種名1","probability":"高"},{"maker":"メーカー2","model":"車種名2","probability":"中"},{"maker":"メーカー3","model":"車種名3","probability":"低"}],"year":"推定年式","category":"カテゴリ","displacement":"排気量","features":["特徴1","特徴2","特徴3"],"comment":"一言コメント"}
PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ])->connectTimeout(10)->timeout(60)->post('https://api.anthropic.com/v1/messages', [
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
