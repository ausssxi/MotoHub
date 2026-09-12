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
    /** トリガー要約の最大長（全角30文字）。超える場合のみ「、」「。」の切れ目で切る。 */
    private const TRIGGER_MAX = 30;

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
     * bike_models から正式車種名を引く。title の小文字（z900rs 等）をそのまま使わないため
     * display_name を優先し、整形（全角ダッシュ正規化＋小文字のみラテンの大文字化）を施す。
     *
     * - 全角マイナス（−, U+2212）/全角ハイフン（－, U+FF0D）は半角 - に正規化。長音符（ー）は不変。
     * - 大文字を1つも含まないラテン連続だけ大文字化（z900rs→Z900RS, シグナスx→シグナスX）。
     *   Ninja 250 のように大文字を含むものは触らない。
     * - display_name が無い場合の「最後の砦」: 整形後も大文字ラテンも日本語も無い（＝実質使えない）
     *   ものは採用しない（生成しない）。整形を先に通すため、全小文字ラテンの多くは救済される。
     */
    public static function officialName(?BikeModel $model): ?string
    {
        if (! $model) {
            return null;
        }

        $display = self::mbTrim((string) ($model->display_name ?? ''));
        $fromDisplay = $display !== '';

        $raw = $fromDisplay ? $display : self::mbTrim((string) ($model->name ?? ''));
        if ($raw === '') {
            return null;
        }

        $formatted = self::formatModelName($raw);

        if (! $fromDisplay) {
            // display_name が無いときだけ、使えない表記（整形しても大文字ラテンも日本語も無い）を落とす。
            $hasUpperLatin = preg_match('/[A-Z]/', $formatted) === 1;
            $hasJapanese = preg_match('/[^\x00-\x7F]/u', $formatted) === 1;
            if (! $hasUpperLatin && ! $hasJapanese) {
                return null;
            }
        }

        return $formatted;
    }

    /**
     * 車種名の整形: 全角ダッシュ正規化（C）→ 小文字のみラテン連続の大文字化（D）。
     */
    public static function formatModelName(string $name): string
    {
        return self::upperLowercaseOnlyRuns(self::normalizeDashes($name));
    }

    /**
     * 全角マイナス（U+2212）と全角ハイフン（U+FF0D）だけを半角ハイフンにする。
     * 長音符（ー, U+30FC）やその他のダッシュ類には一切触れない。
     */
    public static function normalizeDashes(string $value): string
    {
        return str_replace(["\u{2212}", "\u{FF0D}"], '-', $value);
    }

    /**
     * ラテン英数字の連続トークンのうち、小文字を含み大文字を1つも含まないものだけ大文字化する。
     * 文字列全体の mb_strtoupper はしない（Ninja 250 → NINJA 250 を防ぐ）。
     */
    private static function upperLowercaseOnlyRuns(string $value): string
    {
        return (string) preg_replace_callback('/[A-Za-z0-9]+/', function (array $m): string {
            $token = $m[0];
            if (preg_match('/[a-z]/', $token) === 1 && preg_match('/[A-Z]/', $token) === 0) {
                return strtoupper($token);
            }

            return $token;
        }, $value);
    }

    /**
     * トリガー要約を最小限に整える。空・「〜」のみ・長すぎ（>30）は null（＝タイトルから省く）。
     * 評価語や車種名の除去はしない（部分一致で文が壊れるため）。
     */
    public static function sanitizeTrigger(?string $trigger): ?string
    {
        if ($trigger === null) {
            return null;
        }

        $trigger = self::normalizeDashes(self::mbTrim($trigger));

        if ($trigger === '' || $trigger === '〜' || $trigger === '～') {
            return null;
        }

        if (mb_strlen($trigger) > self::TRIGGER_MAX) {
            return null;
        }

        return $trigger;
    }

    /**
     * 本文の最初の h3 をトリガー要約に使う（既存記事書き換え／新規生成の空フォールバック共通）。
     *
     * 方針: h3 をできるだけそのまま使う。
     *   1) タグ除去し空白を詰める（連続スペースは1つに）
     *   2) 車種名・評価語の除去は一切しない
     *   3) 全角30文字までならそのまま採用
     *   4) 30文字超は「、」「。」の直後でのみ切る。ただし
     *      ・括弧（「」『』（））の内側では切らない（開き括弧だけを含んで終わる結果を作らない）
     *      ・末尾が「／」「/」「＆」「&」「・」や助詞（が・の・に・を・で・と・は・も・へ）になる位置では切らない
     *      ・安全に切れる位置が無ければトリガー句ごと省略（null）
     * h3 が無い・整形後に空なら null。
     */
    public static function triggerFromContent(string $html): ?string
    {
        if (preg_match('/<h3\b[^>]*>(.*?)<\/h3>/isu', $html, $m) !== 1) {
            return null;
        }

        $text = self::normalizeDashes(self::plainText($m[1]));
        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) <= self::TRIGGER_MAX) {
            return $text;
        }

        return self::cutTriggerAtSentence($text);
    }

    /**
     * 30文字を超えるトリガーを「、」「。」の直後（＝記号の手前まで）で安全に切る。
     * 括弧の内側・末尾が区切り/助詞になる位置は避ける。安全に切れなければ null。
     */
    private static function cutTriggerAtSentence(string $text): ?string
    {
        $limit = min(mb_strlen($text), self::TRIGGER_MAX);
        $best = null;

        for ($i = 0; $i < $limit; $i++) {
            $ch = mb_substr($text, $i, 1);
            if ($ch !== '、' && $ch !== '。') {
                continue;
            }

            $kept = self::mbTrim(mb_substr($text, 0, $i)); // 「、」「。」は含めない
            if ($kept === '') {
                continue;
            }
            if (! self::bracketsBalanced($kept)) {
                continue;
            }
            if (self::endsWithSeparatorOrParticle($kept)) {
                continue;
            }

            $best = $kept; // 条件を満たす最後（最長）の位置を採用
        }

        return $best;
    }

    private static function bracketsBalanced(string $text): bool
    {
        $pairs = ['「' => '」', '『' => '』', '（' => '）'];
        foreach ($pairs as $open => $close) {
            if (substr_count($text, $open) !== substr_count($text, $close)) {
                return false;
            }
        }

        return true;
    }

    private static function endsWithSeparatorOrParticle(string $text): bool
    {
        $bad = [
            '／', '/', '＆', '&', '・',            // 区切り
            'が', 'の', 'に', 'を', 'で', 'と', 'は', 'も', 'へ', // 助詞
        ];

        return in_array(mb_substr($text, -1), $bad, true);
    }

    /**
     * 既存記事の本文 HTML から、在庫台数と平均価格（万円）を抽出する。
     * <strong> の有無に依存しないよう strip_tags してから抽出する。両方取れなければ null。
     *
     * @return array{count: string, avg: string}|null
     */
    public static function extractNumbers(string $html): ?array
    {
        $text = self::plainText($html);

        $count = self::extractCount($text);
        $avg = self::extractAvgMan($text);

        if ($count === null || $avg === null) {
            return null;
        }

        return ['count' => $count, 'avg' => $avg];
    }

    private static function extractCount(string $text): ?string
    {
        // 「中古」文脈に紐づく台数だけを拾う。動詞（掲載/流通/在庫…）は限定しない。
        $anchored = [
            // 中古 … N台（同一文内）
            '/中古[^。]{0,24}?([\d,]+)\s*台/u',
            // N台 … 中古/流通/掲載/在庫（同一文内・語順逆）
            '/([\d,]+)\s*台[^。]{0,24}?(?:中古|流通|掲載|在庫)/u',
            // 流通/掲載/在庫 … N台
            '/(?:流通|掲載|在庫)[^。]{0,16}?([\d,]+)\s*台/u',
        ];
        foreach ($anchored as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                return str_replace(',', '', $m[1]);
            }
        }

        // フォールバック: 最初に出てくる N台。
        if (preg_match('/([\d,]+)\s*台/u', $text, $m) === 1) {
            return str_replace(',', '', $m[1]);
        }

        return null;
    }

    private static function extractAvgMan(string $text): ?string
    {
        // 「平均」直後の万円のみ拾う（最安値/最高値/価格帯の万円を誤取得しないため anchor 必須）。
        // 「平均を下回る最安値は98.8万円」のように平均の直後が数字でないものは弾く。
        $anchored = [
            '/平均(?:相場|価格)?は?[約]?\s*([\d,]+(?:\.\d+)?)\s*万円/u',
        ];
        foreach ($anchored as $pattern) {
            if (preg_match($pattern, $text, $m) === 1) {
                return self::normalizeMan(str_replace(',', '', $m[1]));
            }
        }

        return null;
    }

    /**
     * HTML をプレーンテキスト化。&nbsp; 等の実体参照も空白へ均す。
     */
    private static function plainText(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // 全角・半角の空白の連続を1つに畳む（アンカー距離判定を安定させる）。
        $text = (string) preg_replace('/[\s\x{3000}]+/u', ' ', $text);

        return self::mbTrim($text);
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
