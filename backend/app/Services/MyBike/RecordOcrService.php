<?php

declare(strict_types=1);

namespace App\Services\MyBike;

use Illuminate\Http\UploadedFile;

/**
 * 整備・カスタム記録フォームの OCR/voice 入力補完。
 * 下回り（画像前処理・API呼び出し・PII除去）は GarageAiExtractor に委譲し、ここは
 * 整備/カスタムのスキーマ・プロンプト・field mapping のみ持つ（給油の FuelOcrService と同設計）。
 *
 * 安全側の抽出方針:
 *  - 確実に取る: 日付(maintained_at)・金額(cost)・場所/店名(vendor)・走行距離(odometer・あれば)。
 *  - 欲張らない: title / part_name / brand は「取れたら候補・外しても害なし」（誤爆で履歴を汚さない）。
 *  - PII（氏名・カード番号・電話・住所・登録番号）は出力に含めない（プロンプト＋sanitizeFreeText で二重防御）。
 *  - 抽出値は自動保存しない（フォーム充填→ユーザー確認）。
 */
final class RecordOcrService
{
    public const TYPE_MAINTENANCE = 'maintenance';

    public const TYPE_CUSTOM = 'custom';

    public function __construct(private readonly GarageAiExtractor $ai) {}

    /**
     * @param  string  $type  self::TYPE_MAINTENANCE | self::TYPE_CUSTOM
     * @return array{values: array<string, mixed>, confidence: string}
     */
    public function extract(UploadedFile $file, string $type): array
    {
        $raw = $this->ai->vision(
            $this->systemPrompt($type),
            $this->ai->preprocessImage($file),
            $this->imageInstruction($type),
        );

        return $this->normalize($raw, $type);
    }

    /**
     * @return array{values: array<string, mixed>, confidence: string}
     */
    public function parseText(string $transcript, string $type): array
    {
        $raw = $this->ai->text(
            $this->voiceSystemPrompt($type),
            "次の音声書き起こしから記録項目を抽出してください:\n".$transcript,
        );

        return $this->normalize($raw, $type);
    }

    private function systemPrompt(string $type): string
    {
        $schema = $type === self::TYPE_CUSTOM
            ? <<<'PROMPT'
出力スキーマ（このキーだけ・順不同可）:
{
  "date": 日付("YYYY-MM-DD") or null,
  "cost": 金額(円, 整数) or null,
  "vendor": 購入店/作業場所の短い表記 or null,
  "odometer": 走行距離(km, 数値) or null,
  "part_name": パーツ名の短い表記 or null,
  "brand": ブランド名 or null,
  "confidence": "高" or "中" or "低"
}
PROMPT
            : <<<'PROMPT'
出力スキーマ（このキーだけ・順不同可）:
{
  "date": 日付("YYYY-MM-DD") or null,
  "cost": 金額(円, 整数) or null,
  "vendor": 店名/作業場所の短い表記 or null,
  "odometer": 走行距離(km, 数値) or null,
  "title": 整備内容の短い表記(例: オイル交換, タイヤ交換) or null,
  "confidence": "高" or "中" or "低"
}
PROMPT;

        $pii = <<<'PROMPT'
★個人情報(PII)は絶対に出力に含めてはならない: 氏名・クレジットカード番号・登録番号(インボイス番号 Txxxx)・
電話番号・承認番号・端末識別番号・住所。どのキーにも一切含めないこと。判別できない項目は null（推測禁止）。
vendor は店舗名・ブランドの短い表記のみ（運営会社の法人正式名・住所は入れない）。
PROMPT;

        $role = $type === self::TYPE_CUSTOM
            ? 'あなたは日本のバイク「カスタム(パーツ装着)記録」の入力補助AIです。パーツのレシート/箱/明細から指定項目だけを読み取り、JSONのみを返します。'
            : 'あなたは日本のバイク「整備記録」の入力補助AIです。整備伝票/レシートから指定項目だけを読み取り、JSONのみを返します。';

        return $role."\n説明文やマークダウンは付けないこと。数値は単位なしの数字。日付は西暦 \"YYYY-MM-DD\"（和暦は西暦へ変換）。\n\n".$schema."\n\n".$pii;
    }

    private function imageInstruction(string $type): string
    {
        return $type === self::TYPE_CUSTOM
            ? 'この画像（パーツのレシート/箱）から 日付・金額・購入店・パーツ名・ブランドを抽出してください。走行距離は通常無いので null。'
            : 'この画像（整備伝票/レシート）から 日付・金額・店名・整備内容を抽出してください。走行距離が記載されていれば odometer に。';
    }

    private function voiceSystemPrompt(string $type): string
    {
        $base = $type === self::TYPE_CUSTOM
            ? "あなたは日本のバイク カスタム記録の音声入力補助AIです。自由文の書き起こしから part_name・brand・金額(cost)・場所(vendor)・走行距離(odometer)を抽出。\n例:「ヨシムラのマフラー 8万円 2りんかんで」「フェンダーレス 1万2千円」。"
            : "あなたは日本のバイク 整備記録の音声入力補助AIです。自由文の書き起こしから title(整備内容)・金額(cost)・場所(vendor)・走行距離(odometer)を抽出。\n例:「オイル交換 3000円 ナップス 19000キロ」「チェーン給油 DIY」。";

        $schema = $type === self::TYPE_CUSTOM
            ? '{"date":null,"cost":金額 or null,"vendor":場所 or null,"odometer":走行距離 or null,"part_name":パーツ名 or null,"brand":ブランド or null,"confidence":"高"|"中"|"低"}'
            : '{"date":null,"cost":金額 or null,"vendor":場所 or null,"odometer":走行距離 or null,"title":整備内容 or null,"confidence":"高"|"中"|"低"}';

        return $base."\n漢数字・ひらがな読み・全角は算用数字へ。明示されない項目は null（推測禁止）。日付は扱わない(date は常に null)。JSONのみ:\n".$schema;
    }

    /**
     * モデル出力をフォームのフィールド名へマップ（非nullのみ・PIIは破棄）。
     *
     * @param  array<string, mixed>  $raw
     * @return array{values: array<string, mixed>, confidence: string}
     */
    private function normalize(array $raw, string $type): array
    {
        $values = [];

        if (isset($raw['odometer']) && is_numeric($raw['odometer'])) {
            $values['odometer'] = (float) $raw['odometer'];
        }
        if (isset($raw['cost']) && is_numeric($raw['cost'])) {
            $values['cost'] = (int) round((float) $raw['cost']);
        }
        if (isset($raw['date']) && is_string($raw['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw['date'])) {
            if ($raw['date'] <= date('Y-m-d')) { // 未来日は誤読として落とす
                $values['maintained_at'] = $raw['date'];
            }
        }

        // free-text は全て PII セーフティネットを通す（店名・項目名にカード/電話/住所等が混ざらない）
        $vendor = $this->ai->sanitizeFreeText($raw['vendor'] ?? null);
        if ($vendor !== null) {
            $values['vendor'] = $vendor;
        }

        if ($type === self::TYPE_CUSTOM) {
            $part = $this->ai->sanitizeFreeText($raw['part_name'] ?? null, 100);
            if ($part !== null) {
                $values['part_name'] = $part;
            }
            $brand = $this->ai->sanitizeFreeText($raw['brand'] ?? null, 100);
            if ($brand !== null) {
                $values['brand'] = $brand;
            }
        } else {
            $title = $this->ai->sanitizeFreeText($raw['title'] ?? null, 50);
            if ($title !== null) {
                $values['title'] = $title;
            }
        }

        $confidence = in_array($raw['confidence'] ?? null, ['高', '中', '低'], true) ? $raw['confidence'] : '低';

        return ['values' => $values, 'confidence' => $confidence];
    }
}
