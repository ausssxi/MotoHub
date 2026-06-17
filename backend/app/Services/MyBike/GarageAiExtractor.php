<?php

declare(strict_types=1);

namespace App\Services\MyBike;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ガレージの AI 抽出 共通下回り（給油 OCR/voice と 整備・カスタム OCR/voice が共有）。
 *  - 画像前処理（EXIF/GPS 除去 + リサイズ + 再エンコード → base64）
 *  - Anthropic /v1/messages 呼び出し（vision / text）→ JSON デコード
 *  - free-text の PII セーフティネット（店名・場所・項目名から カード/電話/住所等を破棄）
 *
 * type 固有のスキーマ/プロンプト/field mapping は各サービス（FuelOcrService / RecordOcrService）が持つ。
 */
final class GarageAiExtractor
{
    private const API_ENDPOINT = 'https://api.anthropic.com/v1/messages';

    public function __construct(private readonly ImageReader $reader) {}

    /**
     * EXIF回転を焼き込み、長辺リサイズし、JPEG再エンコード（EXIF/GPS除去）して base64 化。
     * HEIC（ライブラリ選択のレシート等）は ImageReader が Imagick でデコードして合流。
     */
    public function preprocessImage(UploadedFile $file): string
    {
        $image = $this->reader->read($file->getRealPath());
        $image->orient(); // 斜め/回転撮影をピクセルへ反映（OCR精度）

        $maxEdge = (int) config('garage.ocr_max_edge', 1600);
        $image->scaleDown($maxEdge, $maxEdge);

        // 再エンコードで EXIF/GPS は除去される
        $encoded = $image->toJpeg((int) config('garage.jpeg_quality', 82));

        return base64_encode((string) $encoded);
    }

    /**
     * 画像 + 指示文 → デコード済み JSON（raw）。
     *
     * @return array<string, mixed>
     */
    public function vision(string $system, string $base64, string $userText): array
    {
        return $this->dispatch($system, [
            ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => 'image/jpeg', 'data' => $base64]],
            ['type' => 'text', 'text' => $userText],
        ]);
    }

    /**
     * テキストのみ → デコード済み JSON（raw）。
     *
     * @return array<string, mixed>
     */
    public function text(string $system, string $userText): array
    {
        return $this->dispatch($system, [['type' => 'text', 'text' => $userText]]);
    }

    /**
     * free-text フィールド（店名・場所・項目名等）の PII セーフティネット。
     * 数字合計6桁以上（カード16/電話10/登録13桁等）や長すぎ（住所・法人正式名）は破棄して null。
     * privacy 優先で「疑わしきは破棄」＝空欄→手入力にフォールバック。
     */
    public function sanitizeFreeText(mixed $value, int $maxLen = 60): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $s = trim($value);
        if ($s === '') {
            return null;
        }

        $digitCount = preg_match_all('/\d/', $s);
        if ($digitCount >= 6 || mb_strlen($s) > $maxLen) {
            return null;
        }

        return mb_substr($s, 0, $maxLen);
    }

    /**
     * @param  array<int, array<string, mixed>>  $content
     * @return array<string, mixed>
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
            throw new RuntimeException("AI API error: {$response->status()} - {$response->body()}");
        }

        $text = $response->json('content.0.text');
        if (! is_string($text) || $text === '') {
            throw new RuntimeException('AI API returned empty content.');
        }

        return $this->parseJson($text);
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

        throw new RuntimeException('AI result JSON could not be parsed.');
    }
}
