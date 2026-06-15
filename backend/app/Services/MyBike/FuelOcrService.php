<?php

declare(strict_types=1);

namespace App\Services\MyBike;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use RuntimeException;

/**
 * 給油フォームの OCR 入力補完。
 * レシート/メーター画像を Claude vision に渡し {走行距離, 給油量, 金額, 日付} を抽出する。
 *
 * 重要:
 *  - 抽出値は「自動入力の候補」であり自動保存しない（呼び出し側でフォームに充填→ユーザー確認）。
 *  - 送信前に Intervention で再エンコードし EXIF/GPS メタを除去する（プライバシー）。
 *  - 読めない項目は null（手入力フォールバック）。
 */
final class FuelOcrService
{
    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_ODOMETER = 'odometer';

    /**
     * 画像から給油項目を抽出する。
     *
     * @param  string  $type  self::TYPE_RECEIPT | self::TYPE_ODOMETER
     * @return array{values: array<string, mixed>, confidence: string}
     */
    public function extract(UploadedFile $file, string $type): array
    {
        $base64 = $this->preprocess($file);

        return $this->dispatch($this->systemPrompt($type), [
            ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => $base64]],
            ['type' => 'text', 'text' => $this->userInstruction($type)],
        ]);
    }

    /**
     * 音声の書き起こし（自由文）から給油項目を抽出する（engine A: Web Speech→Haiku のパース層）。
     * 日付は音声に載らない前提（today デフォルト）なので filled_at は埋めない。
     *
     * @return array{values: array<string, mixed>, confidence: string}
     */
    public function parseText(string $transcript): array
    {
        return $this->dispatch($this->voiceSystemPrompt(), [
            ['type' => 'text', 'text' => "次の音声書き起こしから給油項目を抽出してください:\n".$transcript],
        ]);
    }

    /**
     * Anthropic /v1/messages を叩き、content.0.text を normalize して返す（vision/text 共通）。
     *
     * @param  array<int, array<string, mixed>>  $content
     * @return array{values: array<string, mixed>, confidence: string}
     */
    private function dispatch(string $system, array $content): array
    {
        $apiKey = config('services.anthropic.api_key');
        if (! $apiKey) {
            throw new RuntimeException('Anthropic API key is not configured.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(40)->post(self::API_ENDPOINT, [
            'model' => (string) config('garage.ocr_model'),
            'max_tokens' => 300,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $content]],
        ]);

        if (! $response->successful()) {
            throw new RuntimeException("OCR API error: {$response->status()} - {$response->body()}");
        }

        $text = $response->json('content.0.text');
        if (! is_string($text) || $text === '') {
            throw new RuntimeException('OCR API returned empty content.');
        }

        return $this->normalize($this->parseJson($text));
    }

    /**
     * EXIF回転を焼き込み、長辺リサイズし、JPEG再エンコード（EXIF/GPS除去）して base64 化。
     */
    private function preprocess(UploadedFile $file): string
    {
        $manager = new ImageManager(new Driver);
        $image = $manager->read($file->getRealPath());
        $image->orient(); // 斜め/回転撮影をピクセルへ反映（OCR精度）

        $maxEdge = (int) config('garage.ocr_max_edge', 1600);
        $image->scaleDown($maxEdge, $maxEdge);

        // 再エンコードで EXIF/GPS は除去される
        $encoded = $image->toJpeg((int) config('garage.jpeg_quality', 82));

        return base64_encode((string) $encoded);
    }

    private function systemPrompt(string $type): string
    {
        $base = <<<'PROMPT'
あなたは日本のバイク給油記録の入力補助AIです。画像から指定項目だけを正確に読み取り、JSONのみを返します。
説明文やマークダウンは一切付けないこと。読み取れない・確信が持てない項目は必ず null にすること（推測で埋めない）。
数値は単位なしの数字のみ。日付は西暦の "YYYY-MM-DD" 形式。和暦は西暦へ変換。

出力スキーマ（このキーだけ・順不同可）:
{
  "odometer": 走行距離(km, 数値) or null,
  "quantity": 給油量(L, 数値) or null,
  "cost": 金額(円, 整数) or null,
  "date": 給油日("YYYY-MM-DD") or null,
  "confidence": "高" or "中" or "低"
}
PROMPT;

        $hint = $type === self::TYPE_RECEIPT
            ? 'この画像はガソリンスタンドのレシートです。給油量(L)・金額(円)・日付を読み取ってください。走行距離は通常レシートに無いので null にしてください。'
            : 'この画像はバイクのメーター(走行距離計)です。総走行距離(odometer, km)を読み取ってください。トリップ(区間距離)は使わず、総距離を優先。給油量・金額・日付は null にしてください。';

        return $base."\n\n".$hint;
    }

    private function userInstruction(string $type): string
    {
        return $type === self::TYPE_RECEIPT
            ? 'このレシート画像から給油量・金額・日付を抽出してください。'
            : 'このメーター画像から総走行距離を抽出してください。';
    }

    private function voiceSystemPrompt(): string
    {
        return <<<'PROMPT'
あなたは日本のバイク給油記録の音声入力補助AIです。ユーザーが給油時に読み上げた自由文の書き起こしから、
走行距離・給油量・金額を抽出し、JSONのみを返します。説明文やマークダウンは付けないこと。

読み取りルール:
- 自由な言い回しに対応すること（例:「6万キロ、1500円、10リットル」「ろくまんきろ せんごひゃくえん じゅうりっとる」「満タン 12.3リットル 2000円」）。
- 漢数字・ひらがな読み・全角も算用数字へ変換すること（例: 六万→60000、千五百→1500、じゅう→10）。
- 単位語（キロ/km、リットル/L、円/¥）で項目を判別すること。走行距離=km、給油量=L、金額=円。
- 明示されていない・確信が持てない項目は必ず null にすること（推測で埋めない）。
- 日付は扱わない（date は常に null）。

出力スキーマ（このキーだけ）:
{
  "odometer": 走行距離(km, 数値) or null,
  "quantity": 給油量(L, 数値) or null,
  "cost": 金額(円, 整数) or null,
  "date": null,
  "confidence": "高" or "中" or "低"
}
PROMPT;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseJson(string $text): array
    {
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $decoded = json_decode($m[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        throw new RuntimeException('OCR result JSON could not be parsed.');
    }

    /**
     * モデル出力をフォームのフィールド名へマップ（非nullのみ）。
     *
     * @param  array<string, mixed>  $raw
     * @return array{values: array<string, mixed>, confidence: string}
     */
    private function normalize(array $raw): array
    {
        $values = [];

        if (isset($raw['odometer']) && is_numeric($raw['odometer'])) {
            $values['odometer'] = (float) $raw['odometer'];
        }
        if (isset($raw['quantity']) && is_numeric($raw['quantity'])) {
            $values['quantity'] = (float) $raw['quantity'];
        }
        if (isset($raw['cost']) && is_numeric($raw['cost'])) {
            $values['cost'] = (int) round((float) $raw['cost']);
        }
        if (isset($raw['date']) && is_string($raw['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw['date'])) {
            // 未来日は誤読の可能性が高いので落とす（手入力に委ねる）
            if ($raw['date'] <= date('Y-m-d')) {
                $values['filled_at'] = $raw['date'];
            }
        }

        $confidence = in_array($raw['confidence'] ?? null, ['高', '中', '低'], true) ? $raw['confidence'] : '低';

        return ['values' => $values, 'confidence' => $confidence];
    }
}
