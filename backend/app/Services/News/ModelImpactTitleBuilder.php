<?php

declare(strict_types=1);

namespace App\Services\News;

use App\Models\BikeModel;

/**
 * 「新車→中古相場影響」記事のタイトルを、事実ベースの部品から組み立てる。
 *
 * 目的:
 *   旧実装は 318 本が「{車種名}の新型発表で旧型中古相場はどう動く？｜データで予測」という
 *   同一構文で、しかも起点はパーツ発売等がほとんどなのに「新型発表」と偽っていた。
 *   本ビルダーは検索語＋数字を先頭に置き、後半（トリガー/年月）で個体を区別する。
 *
 * 形（フル）:
 *   {正式車種名}の中古相場、平均{X}万円・在庫{N}台｜{トリガー要約}（{YYYY年M月}）
 *
 * 部品欠落時の落とし方:
 *   数字なし   → {車種名}の中古相場｜{トリガー要約}（{年月}）
 *   トリガーなし → {車種名}の中古相場、平均{X}万円・在庫{N}台（{年月}）
 *   車種名なし → null（タイトルが作れない記事は生成しない）
 *   空欄や「〜」等の穴埋め文字は一切出さない。
 */
final class ModelImpactTitleBuilder
{
    /** タイトル要約から取り除く評価語（事実ベースにするため）。 */
    private const EVALUATIVE_WORDS = [
        'さらに進化', 'さらなる進化', '注目の', '注目', '話題の', '話題', '人気の',
        '魅力的な', '魅力の', '待望の', '衝撃の', '驚きの', '究極の', '最強の', '期待の',
    ];

    /**
     * 通常タイトルを組み立てる。車種名が無ければ null。
     *
     * @param  string|null  $avgManStr  平均価格（万円）の表示用文字列。例 "147.5"。本文と必ず同一にすること。
     * @param  string|null  $countStr  在庫台数の表示用文字列。例 "886"。
     */
    public static function build(
        ?string $officialName,
        ?string $avgManStr,
        ?string $countStr,
        ?string $trigger,
        string $yearMonth,
    ): ?string {
        $name = self::normalizeName($officialName);
        if ($name === null) {
            return null;
        }

        $title = "{$name}の中古相場";

        if (self::hasNumbers($avgManStr, $countStr)) {
            $title .= "、平均{$avgManStr}万円・在庫{$countStr}台";
        }

        $trigger = self::sanitizeTrigger($trigger);
        if ($trigger !== null) {
            $title .= "｜{$trigger}";
        }

        $title .= "（{$yearMonth}）";

        return $title;
    }

    /**
     * 本当に新型・モデルチェンジが発表されたときだけ使う特別形。
     *   {車種名}に新型が登場｜現行型の中古相場は平均{X}万円・在庫{N}台（{年月}）
     * 数字が無ければ相場句を省く。
     */
    public static function buildNewModel(
        ?string $officialName,
        ?string $avgManStr,
        ?string $countStr,
        string $yearMonth,
    ): ?string {
        $name = self::normalizeName($officialName);
        if ($name === null) {
            return null;
        }

        if (self::hasNumbers($avgManStr, $countStr)) {
            return "{$name}に新型が登場｜現行型の中古相場は平均{$avgManStr}万円・在庫{$countStr}台（{$yearMonth}）";
        }

        return "{$name}に新型が登場（{$yearMonth}）";
    }

    /**
     * 既存記事書き換え用: 数字が取れなかったときの日付形式。
     *   {車種名}の中古相場レポート（{YYYY年M月D日}時点）
     */
    public static function buildDateFallback(?string $officialName, string $yearMonthDay): ?string
    {
        $name = self::normalizeName($officialName);
        if ($name === null) {
            return null;
        }

        return "{$name}の中古相場レポート（{$yearMonthDay}時点）";
    }

    /**
     * bike_models から正式車種名を引く。
     * title の小文字（z900rs 等）をそのまま使わないため display_name を優先。
     * display_name が無い場合、name は「既に大文字を含む」または「日本語を含む」ときのみ採用
     * （小細工で先頭を大文字化したりはしない）。取れなければ null。
     */
    public static function officialName(?BikeModel $model): ?string
    {
        if (! $model) {
            return null;
        }

        $display = trim((string) ($model->display_name ?? ''));
        if ($display !== '') {
            return $display;
        }

        $name = trim((string) ($model->name ?? ''));
        if ($name === '') {
            return null;
        }

        // 既に正式表記になっているもの（大文字を含む / 日本語を含む）はそのまま使える。
        // "z900rs" "pcx" のような全小文字ラテンは正式表記ではないので採用しない。
        if (preg_match('/[A-Z]/u', $name) === 1 || preg_match('/[^\x00-\x7F]/u', $name) === 1) {
            return $name;
        }

        return null;
    }

    /**
     * トリガー要約を事実ベースに整える。空・評価語のみ・長すぎる場合は null（＝タイトルから省く）。
     */
    public static function sanitizeTrigger(?string $trigger): ?string
    {
        if ($trigger === null) {
            return null;
        }

        $trigger = self::mbTrim($trigger);
        if ($trigger === '') {
            return null;
        }

        foreach (self::EVALUATIVE_WORDS as $word) {
            $trigger = str_replace($word, '', $trigger);
        }
        $trigger = self::mbTrim($trigger);

        if ($trigger === '' || $trigger === '〜' || $trigger === '～') {
            return null;
        }

        // 20文字程度の要約を想定。極端に長いものは要約失敗とみなし省く。
        if (mb_strlen($trigger) > 32) {
            return null;
        }

        return $trigger;
    }

    /**
     * 既存記事の本文 HTML から、在庫台数と平均価格（万円）を抽出する。
     * 表現ゆれに複数パターンで対応。両方取れなければ null。
     *
     * @return array{count: string, avg: string}|null
     */
    public static function extractNumbers(string $html): ?array
    {
        $count = self::extractCount($html);
        $avg = self::extractAvgMan($html);

        if ($count === null || $avg === null) {
            return null;
        }

        return ['count' => $count, 'avg' => $avg];
    }

    private static function extractCount(string $html): ?string
    {
        // 「掲載/在庫/中古車」文脈に紐づく台数を優先し、min/max とは無関係な台数だけ拾う。
        $anchored = [
            '/掲載台数は?\s*<strong>\s*([\d,]+)\s*台/u',
            '/中古車は\s*<strong>\s*([\d,]+)\s*台/u',
            '/在庫(?:台数|数)?は?\s*<strong>\s*([\d,]+)\s*台/u',
            '/<strong>\s*([\d,]+)\s*台\s*<\/strong>\s*(?:が|も)?\s*掲載/u',
            '/<strong>\s*([\d,]+)\s*台\s*<\/strong>\s*と豊富/u',
        ];
        foreach ($anchored as $pattern) {
            if (preg_match($pattern, $html, $m) === 1) {
                return str_replace(',', '', $m[1]);
            }
        }

        // フォールバック: 最初に出てくる <strong>N台</strong>。
        if (preg_match('/<strong>\s*([\d,]+)\s*台/u', $html, $m) === 1) {
            return str_replace(',', '', $m[1]);
        }

        return null;
    }

    private static function extractAvgMan(string $html): ?string
    {
        // 「平均」文脈の万円のみ拾う（最安値/最高値の万円を誤取得しないため anchor 必須）。
        $anchored = [
            '/平均価格は?[約]?\s*<strong>\s*([\d,]+(?:\.\d+)?)\s*万円/u',
            '/平均価格は?[約]?\s*<strong>\s*([\d,]+(?:\.\d+)?)\s*<\/strong>\s*万円/u',
            '/平均(?:相場|価格)?は?[約]?\s*<strong>\s*([\d,]+(?:\.\d+)?)\s*万円/u',
            '/平均[^<]{0,12}<strong>\s*([\d,]+(?:\.\d+)?)\s*万円/u',
        ];
        foreach ($anchored as $pattern) {
            if (preg_match($pattern, $html, $m) === 1) {
                return self::normalizeMan(str_replace(',', '', $m[1]));
            }
        }

        return null;
    }

    /**
     * 平均価格文字列の末尾ゼロ揺れを均す（"147.0" → "147"）。本文が既に "147.5" ならそのまま。
     */
    private static function normalizeMan(string $value): string
    {
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }

        return $value === '' ? '0' : $value;
    }

    private static function hasNumbers(?string $avgManStr, ?string $countStr): bool
    {
        return $avgManStr !== null && $avgManStr !== '' && $avgManStr !== '0'
            && $countStr !== null && $countStr !== '' && $countStr !== '0';
    }

    private static function normalizeName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $name = self::mbTrim($name);

        return $name === '' ? null : $name;
    }

    /**
     * 全角スペース（U+3000）を含めてマルチバイト安全に前後の空白を除去する。
     * 通常の trim() はバイト単位のため、マスクに全角スペースを渡すと日本語が壊れる。
     */
    private static function mbTrim(string $value): string
    {
        return (string) preg_replace('/^[\s\x{3000}]+|[\s\x{3000}]+$/u', '', $value);
    }

    /**
     * 新車発表→中古影響の分析記事を組み立てるコマンド由来のタイトル文字列。
     * 万円の表示は本文と一致させるため round(,1) と同じ規則で整える。
     */
    public static function formatMan(float $manYen): string
    {
        return self::normalizeMan((string) round($manYen, 1));
    }
}
