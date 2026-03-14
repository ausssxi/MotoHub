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

        $apiKey = config('services.openai.api_key');
        if (empty($apiKey)) {
            return response()->json(['error' => 'APIキーが設定されていません。'], 500);
        }

        // 画像をリサイズ・圧縮（API送信サイズ削減）
        $imageData = file_get_contents($request->file('image')->getRealPath());
        $originalSize = strlen($imageData);
        Log::info('画像圧縮: 元サイズ ' . number_format($originalSize / 1024) . ' KB');

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

        $compressedSize = strlen($compressed);
        $base64 = base64_encode($compressed);
        $mediaType = 'image/jpeg';

        Log::info('画像圧縮: 圧縮後 ' . number_format($compressedSize / 1024) . ' KB, ' . $newWidth . 'x' . $newHeight . 'px, 削減率 ' . round((1 - $compressedSize / $originalSize) * 100) . '%');

        $systemPrompt = <<<'PROMPT'
あなたは日本のバイク専門家AIです。画像を見てバイクの車種を特定してください。

重要なルール：
- 日本市場で販売されたモデル名を優先すること
- 似ているバイクではなく、視認できる特徴（タンク形状・フレーム・エンジン・ホイール・ライト形状）から正確に判定すること
- 角度が悪い・背景が写っている場合でも、見える部品から最善の推定をすること
- 必ず候補を最大3つ挙げ、candidatesに確度付きで記載すること
- 自信がない場合はconfidenceを「低」にすること
- 絶対に似ているだけで断定しないこと

名前の表記ルール：
- modelフィールドは必ず日本語カタカナで返すこと（例：Grom→グロム、Gyro Canopy→ジャイロキャノピー、CB400SF→CB400SF）
- maker_jpフィールドは日本語で返すこと（例：Honda→ホンダ、Yamaha→ヤマハ）
- candidatesのmodelも同様にカタカナで返すこと
- アルファベット+数字の型番（CB400SF、YZF-R25等）はそのままでOK

バイクが写っていない場合は {"error": "バイクが見つかりませんでした"} を返してください。

JSON形式のみで返してください。形式：
{"maker":"メーカー英語名","maker_jp":"メーカー日本語名","model":"車種名（カタカナ）","confidence":"高 or 中 or 低","candidates":[{"maker":"メーカー英語名","model":"車種名（カタカナ）","probability":"高"},{"maker":"メーカー英語名","model":"車種名（カタカナ）","probability":"中"},{"maker":"メーカー英語名","model":"車種名（カタカナ）","probability":"低"}],"year":"推定年式","category":"カテゴリ","displacement":"排気量","features":["特徴1","特徴2","特徴3"],"comment":"一言コメント"}
PROMPT;

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
            ])->connectTimeout(10)->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o',
                'max_tokens' => 1000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => 'data:' . $mediaType . ';base64,' . $base64,
                                ],
                            ],
                            [
                                'type' => 'text',
                                'text' => $systemPrompt . "\n\nこの画像に写っているバイクの車種を判定してください。",
                            ],
                        ],
                    ],
                ],
            ]);

            if ($response->failed()) {
                Log::error('OpenAI API error', ['status' => $response->status(), 'body' => $response->body()]);
                return response()->json(['error' => 'AI判定に失敗しました。しばらくしてから再度お試しください。'], 502);
            }

            $body = $response->json();
            $text = $body['choices'][0]['message']['content'] ?? '';

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
