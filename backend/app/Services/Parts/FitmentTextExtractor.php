<?php

declare(strict_types=1);

namespace App\Services\Parts;

/**
 * 商品説明文から「適合車種 / 適合型式 / 純正品番」を抽出する。
 *
 * パターンは 2026-08-12 に fitment:probe --dump で取得した実データから決めたもので、
 * 推測で書いた分岐は含めない。確認できた書式:
 *
 *   【適合車種】Vストローム250（V-Strom250）【適合型式】2BK-DS11A【適合年式】17年
 *   【適合車種】Vストローム250/ABS（V-Strom250） 【適合型式】DS11A/DS12E 【適合年式】2017〜2023年
 *
 * ■ 設計方針（誤ったデータを入れないことを最優先にする）
 *  - 型式は【適合型式】ラベルがある商品からしか取らない。年式・車台番号からの推測はしない。
 *    実測でラベルを持つ5件は5件とも正しい適合を書いており、逆にラベルを持たない商品は
 *    いずれも「多数の車種に使える汎用品」だった。つまりラベルの要求が、そのまま汎用品の
 *    除外フィルタとして働く。
 *  - 車種名は前方一致で通さない。「Vストローム250」と「Vストローム250SX」は別物で、
 *    実測で SX が混入していた。分割したうえでの完全一致だけを採用する。
 *  - 判断がつかないものは採用しない（呼び出し側で「判定不能」として計上する）。
 *
 * PartsCodeExtractor（JAN・メーカー品番）と役割を分ける。あちらは品番、こちらは適合。
 */
final class FitmentTextExtractor
{
    /**
     * ラベルを囲む括弧。全角【】・全角［］・半角[] の揺れを吸収する。
     * 値はラベルの終わりから「次のラベルの始まり」までとする。
     */
    private const LABEL_OPEN = '【\[［';

    private const LABEL_CLOSE = '】\]］';

    /**
     * 値の区切り。スラッシュ（全角/半角）・中黒・読点・カンマ（全角/半角）。
     * 「DS11A/DS12E」「AA07/AA08/AA09」「Vストローム250/ABS」などを分ける。
     */
    private const SEPARATORS = '/[\/／・、，,]+/u';

    /**
     * 車体型式（規制記号なし）。DS11A / JA42 / AA07 / RN52J など。
     * 英字2 + 数字2 + 英字0〜1。実データで確認できた形はすべてこれに収まる。
     */
    private const FRAME_CODE = '[A-Z]{2}\d{2}[A-Z]?';

    /**
     * 規制記号。2BK / 8BL / EBL / 2BL など（英数2〜3文字）。
     */
    private const REGULATION = '[0-9A-Z]{2,3}';

    /**
     * 「適合表の埋め込み」と判断する、説明文中の distinct な型式トークン数のしきい値。
     *
     * 1商品が正当に持つ型式は、年式違い・グレード違いを含めても数個までという想定に基づく
     * （実データの最大は「DS11A/DS12E」の2個、下見した例でも「AA07/AA08/AA09」の3個）。
     * これに対し、メーカーの適合表（「NGK プラグ 適合表 スズキ」など）を説明文に丸ごと
     * 貼っている商品は、無関係な車種の型式が何十個も並ぶ。
     *
     * 実害: 2026-08-12 の測定で「DS12E → MR7E-9」が2商品一致で採用された。MR7E-9 は
     * アドレス125／アヴェニス125（8BJ-EA12J）用で Vストローム250 には適合しない。
     * 適合表の中の1行「Vストローム250 DS12E」を書式Bが拾い、商品自体の品番（別車種向け）と
     * 結びつけたのが原因。同じ表を貼る店舗が複数あるため、一致数による多数決も効かない。
     * よって「型式が多すぎる説明文は、そもそも1車種向けの説明ではない」として書式Bから除外する。
     *
     * ※ 2026-08-12 の本番測定では発火0件だった。上記 MR7E-9 の原因は適合表の埋め込みではなく、
     *   単品商品のタイトル・説明文自体が誤っていたことだと後に判明している
     *   （「Vストローム250 DS12E」と書かれた商品の実際の品番が別車種用だった）。
     *   つまりこの判定は当該事例には効いていない。それでも残すのは、擬似データでは意図どおり
     *   動作しており、除去コストが無く、適合表を貼る商品が今後現れたときの保険になるため。
     */
    private const FITMENT_TABLE_FRAME_CODE_THRESHOLD = 5;

    /**
     * 【適合型式】ラベルが存在するか。
     * これが false の商品からは、型式も純正品番も採らない（汎用品の可能性が高いため）。
     */
    public static function hasFrameCodeLabel(string $text): bool
    {
        return self::labelledValue($text, '適合型式') !== null;
    }

    /**
     * 【適合型式】の値から車体型式を取り出す。
     * ラベルが無い、または1件も形が合わなければ空配列。
     *
     * 「2BK-DS11A」は規制記号（2BK）と車体型式（DS11A）に分けて保持する。
     * 規制記号は年式・排ガス規制で変わるため、型式そのものとは別に持つ必要がある。
     *
     * @return array<int, array{raw: string, regulation: string|null, code: string}>
     */
    public static function frameCodes(string $text): array
    {
        $value = self::labelledValue($text, '適合型式');
        if ($value === null) {
            return [];
        }

        $out = [];
        foreach (self::splitValues($value) as $token) {
            // 「2BK-DS11A（17年〜）」のような補足は落としてから判定する
            $token = self::stripParentheses($token);
            $token = strtoupper(self::normalize($token));

            if (preg_match('/^('.self::REGULATION.')-('.self::FRAME_CODE.')$/u', $token, $m) === 1) {
                $out[] = ['raw' => $token, 'regulation' => $m[1], 'code' => $m[2]];

                continue;
            }

            if (preg_match('/^('.self::FRAME_CODE.')$/u', $token, $m) === 1) {
                $out[] = ['raw' => $token, 'regulation' => null, 'code' => $m[1]];
            }
            // どちらにも当てはまらない語は採らない（誤った型式を作らないため）
        }

        return self::uniqueByRaw($out);
    }

    /**
     * 【適合車種】の値を車種名の配列にして返す。ラベルが無ければ null（＝判定不能）。
     *
     * 「（V-Strom250）」のような英語表記の併記は落とす。
     * 「Vストローム250/ABS」はスラッシュで分割して両方を候補にする。
     *
     * @return array<int, string>|null
     */
    public static function fitmentModelNames(string $text): ?array
    {
        $value = self::labelledValue($text, '適合車種');
        if ($value === null) {
            return null;
        }

        $value = self::stripParentheses($value);

        $names = [];
        foreach (self::splitValues($value) as $token) {
            $token = trim($token);
            if ($token !== '') {
                $names[] = $token;
            }
        }

        return $names;
    }

    /**
     * 適合車種の候補群に、対象車種名と完全一致するものがあるか。
     *
     * 前方一致では判定しない。「Vストローム250SX」を「Vストローム250」として
     * 採用してしまうため（実測で混入を確認）。
     *
     * @param  array<int, string>  $names
     */
    public static function matchesModel(array $names, string $target): bool
    {
        $needle = strtoupper(self::normalize($target));
        if ($needle === '') {
            return false;
        }

        foreach ($names as $name) {
            if (strtoupper(self::normalize($name)) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * 説明文が「1車種向けの商品説明」ではなく「メーカーの適合表の埋め込み」に見えるか。
     *
     * 判定は単純に、テキスト中に現れる distinct な型式トークン数がしきい値以上かどうか。
     * しきい値の根拠は FITMENT_TABLE_FRAME_CODE_THRESHOLD のコメントを参照。
     *
     * ※ 書式B（車種名の直後の語を型式とみなす）にだけ使う。書式Aの【適合型式】ラベルは
     *   値が単数〜数個に限られるため、この判定は適用しない。
     */
    public static function looksLikeFitmentTable(string $text): bool
    {
        return count(self::distinctFrameCodeTokens($text)) >= self::FITMENT_TABLE_FRAME_CODE_THRESHOLD;
    }

    /**
     * テキスト中に語として現れる型式トークンを重複なく返す（適合表の判定に使う）。
     *
     * 前後が英数字・ハイフンでないことを要求する。そうしないと車台番号
     * 「LC6DS12EZ01100001」の中の「DS12E」まで数えてしまう。
     *
     * @return array<int, string>
     */
    public static function distinctFrameCodeTokens(string $text): array
    {
        $normalized = strtoupper(self::widthNormalize($text));

        $re = '/(?<![A-Z0-9\-])('.self::FRAME_CODE.')(?![A-Z0-9\-])/u';
        if (preg_match_all($re, $normalized, $m) !== false && ! empty($m[1])) {
            return array_values(array_unique($m[1]));
        }

        return [];
    }

    /**
     * 【診断専用】数字を2〜3桁まで許した拡張パターンで型式トークンを重複なく返す。
     *
     * ★採用判定には一切使わないこと。distinctFrameCodeTokens() の数字2桁固定（FRAME_CODE）を
     *   2〜3桁に広げただけの、効果測定用の別実装。カワサキの EX250K / ZR900C のような数字3桁の
     *   型式を現行パターンがどれだけ取りこぼしているかを、fitment:probe --verify --frame-widen で
     *   数えるために使う。境界規則（前後が英数字・ハイフンでない）は本体と揃える。
     *
     * @return array<int, string>
     */
    public static function distinctFrameCodeTokensWidened(string $text): array
    {
        $normalized = strtoupper(self::widthNormalize($text));

        // 数字2桁→2〜3桁。3桁を許すと CB250R / GB350S のような車種名の形も拾ってしまうため、
        // これはあくまで「増分と実例を見る」ための診断であり、採用には落とさない。
        $re = '/(?<![A-Z0-9\-])([A-Z]{2}\d{2,3}[A-Z]?)(?![A-Z0-9\-])/u';
        if (preg_match_all($re, $normalized, $m) !== false && ! empty($m[1])) {
            return array_values(array_unique($m[1]));
        }

        return [];
    }

    /**
     * テキストに対象車種名が「語として」含まれるか（前方一致では通さない）。
     *
     * 「Vストローム250SX」の中の「Vストローム250」は含まれているとみなさない。
     * frameCodesAfterModelName の条件1と同じ境界規則を使う。
     */
    public static function containsModelName(string $text, string $target): bool
    {
        $haystack = self::widthNormalize($text);
        $needle = self::widthNormalize($target);

        if ($needle === '' || $haystack === '') {
            return false;
        }

        $offset = 0;
        while (($pos = mb_stripos($haystack, $needle, $offset)) !== false) {
            $offset = $pos + mb_strlen($needle);
            $rest = mb_substr($haystack, $offset);

            if ($rest === '' || preg_match('/^[\s\x{3000}]/u', $rest) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * 【照合専用】型式文字列を突き合わせ可能な形に正規化する。
     *
     * model_fitments.frame_code は「2BK-DN11A/8BK-DN12B」のように
     * 規制記号つき・スラッシュ連結で入っている。一方、商品テキストから抽出されるのは
     * 裸の型式（DN11A）なので、そのまま比較すると一致しているのに食い違いとして数えられる
     * （2026-08-12 の実測: 既存 2BK-DN11A/8BK-DN12B と抽出 DN11A が「取りこぼし＋新規」になった）。
     *
     * 手順:
     *   a) スラッシュ・読点・中黒で分割
     *   b) 規制記号のプレフィックス（英数2〜3文字＋ハイフン）を除去。
     *      ただしハイフンより後ろが FRAME_CODE の形のときだけ。合致しなければ元の値を保つ
     *      （型式そのものにハイフンが含まれる場合を壊さないため）
     *   c) 空白除去・大文字化
     *
     * 実データにある規制記号: 2BK- 8BK- 8BJ- JBK- JBH- 2BH- 2BL- EBJ-
     *
     * ★ 突き合わせにのみ使う。表示は元の値のままにすること（どちらの表記だったかを失わないため）。
     *
     * @return array<int, string>
     */
    public static function normalizeFrameCodesForMatching(string $raw): array
    {
        $out = [];

        foreach (preg_split(self::SEPARATORS, $raw) ?: [] as $token) {
            $token = strtoupper(self::normalize($token));
            if ($token === '') {
                continue;
            }

            // 規制記号を落とせるのは、残りが型式の形をしているときだけ
            if (preg_match('/^'.self::REGULATION.'-('.self::FRAME_CODE.')$/u', $token, $m) === 1) {
                $out[] = $m[1];

                continue;
            }

            $out[] = $token;
        }

        return array_values(array_unique($out));
    }

    /**
     * 【診断専用】空白・記号・大小文字・全半角を無視した部分一致で車種名を含むか。
     *
     * ★ 採用判定には絶対に使わないこと。
     *   この判定は「Vストローム250」と「Vストローム250SX」を区別できない（部分一致のため）。
     *   採用に使うのは containsModelName() / matchesModel()（語境界つき・完全一致）の方。
     *
     * 用途は取りこぼしの原因切り分けのみ。「商品は取れているのに車種名を認識できていない」のか
     * 「そもそも商品が無い」のかを、bike_models.name の表記（例「ninja 250」）と
     * 商品側の表記（例「Ninja250」）の差として可視化する。
     */
    public static function containsModelNameLoose(string $text, string $target): bool
    {
        $needle = self::looseNormalize($target);

        return $needle !== '' && str_contains(self::looseNormalize($text), $needle);
    }

    /**
     * 【診断専用】緩い一致でヒットした箇所を、元テキストの表記のまま返す。
     *
     * 「DB上は ninja 250 だが、商品タイトルでは Ninja250 と書かれている」を目で見るためのもの。
     * 最も早い位置の、最も短い一致部分を返す。見つからなければ null。
     */
    public static function findLooseOccurrence(string $text, string $target): ?string
    {
        $needle = self::looseNormalize($target);
        if ($needle === '' || $text === '') {
            return null;
        }

        $length = mb_strlen($text);
        for ($start = 0; $start < $length; $start++) {
            $buffer = '';
            for ($end = $start; $end < $length; $end++) {
                $buffer .= mb_substr($text, $end, 1);
                $normalized = self::looseNormalize($buffer);

                if ($normalized === $needle) {
                    // 記号を無視して照合するため、前後に空白を巻き込むことがある。表示用に落とす。
                    return trim($buffer);
                }
                // 途中まで一致しなくなったら、この開始位置は見込みなし
                if ($normalized !== '' && ! str_starts_with($needle, $normalized)) {
                    break;
                }
            }
        }

        return null;
    }

    /**
     * 【診断専用】緩い比較用の正規化: 全角→半角、英字は大文字、英数字とかな・漢字以外を除去。
     * 「ninja 250」「Ninja250」「NINJA-250」がすべて "NINJA250" になる。
     */
    public static function looseNormalize(string $text): string
    {
        $text = strtoupper(self::widthNormalize($text));

        // 記号・空白を落とす（英数字・ひらがな・カタカナ・漢字・長音記号は残す）
        return preg_replace('/[^0-9A-Z\p{Hiragana}\p{Katakana}\p{Han}ー]+/u', '', $text) ?? '';
    }

    /**
     * 書式B（NGK形式）: 「メーカー名 車種名 型式 排気量cc 年式」の並びから型式を取り出す。
     *
     * 実データ（2026-08-12）:
     *   商品名: NGK スパークプラグ スズキ Vストローム250 DS11A 250cc 2017年07月〜2023年03月 入数：1本
     *   説明文: ■適合車種スズキVストローム250 DS11A 2017年07月〜2023年03月
     *   別車種: NGK MotoDX スパークプラグ スズキ Vストローム250SX EL11L 250cc 2023年08月〜
     *
     * 書式Aと違ってラベルで値が囲まれていないため、構造を推測して切り出すのではなく
     * 「対象車種名を見つけ、その直後の語が型式の形をしているか」だけを見る。
     * 車種名が起点なので、SX のような別車種を取り違えない。
     *
     * 採用条件（どれか1つでも欠けたら採らない）:
     *   1. 対象車種名が完全な語として現れること
     *      （直後が空白か文末。「Vストローム250SX」の中の「Vストローム250」は語として認めない）
     *   2. その直後の語が FRAME_CODE（DS11A / EL11L / AA07 等）の形をしていること
     *
     * @return array<int, array{raw: string, regulation: string|null, code: string}>
     */
    public static function frameCodesAfterModelName(string $text, string $target): array
    {
        // 全角英数を半角へ寄せる。空白は語の境界として使うので、ここでは消さない。
        $haystack = self::widthNormalize($text);
        $needle = self::widthNormalize($target);

        if ($needle === '' || $haystack === '') {
            return [];
        }

        $out = [];
        $offset = 0;

        while (($pos = mb_stripos($haystack, $needle, $offset)) !== false) {
            $offset = $pos + mb_strlen($needle);
            $rest = mb_substr($haystack, $offset);

            // 条件1: 車種名の直後は空白か文末でなければならない。
            //        「Vストローム250SX」で「Vストローム250」に一致しても、直後が 'S' なので弾かれる。
            if ($rest !== '' && preg_match('/^[\s\x{3000}]/u', $rest) !== 1) {
                continue;
            }

            // 条件2: 直後の語が型式の形をしているか（1語だけ見る。離れた位置の語は見ない）
            // ※ trim() は全角スペース(U+3000)を落とさない。先頭の空白は正規表現で落とすこと
            //   （そうしないと split の先頭要素が空文字になり、全角区切りの商品を取りこぼす）
            $rest = preg_replace('/^[\s\x{3000}]+/u', '', $rest) ?? $rest;
            $nextToken = preg_split('/[\s\x{3000}]+/u', $rest, 2)[0] ?? '';
            $nextToken = strtoupper(self::normalize(self::stripParentheses($nextToken)));

            if (preg_match('/^('.self::REGULATION.')-('.self::FRAME_CODE.')$/u', $nextToken, $m) === 1) {
                $out[] = ['raw' => $nextToken, 'regulation' => $m[1], 'code' => $m[2]];
            } elseif (preg_match('/^('.self::FRAME_CODE.')$/u', $nextToken, $m) === 1) {
                $out[] = ['raw' => $nextToken, 'regulation' => null, 'code' => $m[1]];
            }
            // 型式の形でなければ何も採らない（排気量や年式を型式と誤認しないため）
        }

        return self::uniqueByRaw($out);
    }

    /**
     * 「純正品番」「該当純正品番」ラベルの直後にある品番群。
     * 呼び出し側は、型式ラベルを持つ商品に限って使うこと（汎用品の品番を拾わないため）。
     *
     * 実データ: 「該当純正品番:16510-03G00、16510-06B00」
     *
     * @return array<int, string>
     */
    public static function oemPartNumbers(string $text): array
    {
        // ラベルは括弧つき（【純正品番】）でもコロン区切り（該当純正品番:）でも来る
        $value = self::labelledValue($text, '(?:該当)?純正品番');

        if ($value === null && preg_match('/(?:該当)?純正品番\s*[:：]\s*(.+)$/u', $text, $m) === 1) {
            $value = $m[1];
        }

        if ($value === null) {
            return [];
        }

        // 値の終わりが次のラベルで区切られているとは限らない（「【純正品番】A、B 品番 C」のように
        // 別の語が続くことがある）。そこで区切り文字と空白の両方で分割し、
        // 品番らしい形が続く間だけ採り、最初にそうでない語が出たら打ち切る。
        // こうしないと2つ目以降の品番を取りこぼす（「A、B」の B が「B 品番 C」として弾かれる）。
        $tokens = preg_split('/[\s\x{3000}\/／・、，,]+/u', $value) ?: [];

        $out = [];
        foreach ($tokens as $token) {
            $token = strtoupper(self::normalize(self::stripParentheses($token)));
            if ($token === '') {
                continue;
            }

            // 品番らしい形（英数字とハイフンで6文字以上）だけを採る
            if (preg_match('/^[0-9A-Z][0-9A-Z\-]{5,}$/u', $token) !== 1) {
                break; // 品番の並びが途切れた＝ここから先は別の記述
            }

            $out[] = $token;
        }

        return array_values(array_unique($out));
    }

    /**
     * ラベルの値を取り出す。ラベルが無ければ null。
     *
     * 値は「ラベルの閉じ括弧の直後」から「次のラベルの開き括弧の直前」まで。
     * 括弧なしでコロン区切りのラベルにも対応する（該当純正品番: など）。
     */
    private static function labelledValue(string $text, string $labelPattern): ?string
    {
        $open = self::LABEL_OPEN;
        $close = self::LABEL_CLOSE;

        // 【適合型式】値 …（次の【まで）
        $re = '/['.$open.']\s*'.$labelPattern.'\s*['.$close.']\s*([^'.$open.']*)/u';
        if (preg_match($re, $text, $m) === 1) {
            $value = trim($m[1]);

            return $value !== '' ? $value : null;
        }

        return null;
    }

    /**
     * 区切り文字で分割する。
     *
     * @return array<int, string>
     */
    private static function splitValues(string $value): array
    {
        $parts = preg_split(self::SEPARATORS, $value) ?: [];

        return array_values(array_filter(array_map('trim', $parts), fn (string $v): bool => $v !== ''));
    }

    /**
     * 全角・半角どちらの括弧も、その中身ごと落とす。
     * 「Vストローム250（V-Strom250）」→「Vストローム250」
     */
    private static function stripParentheses(string $text): string
    {
        return trim(preg_replace('/[（(\[［][^）)\]］]*[）)\]］]/u', '', $text) ?? $text);
    }

    /**
     * 比較用の正規化: 全角英数記号→半角、空白（半角・全角）除去、trim。
     * 車種名にも型式にも同じものを通し、比較の左右で必ず揃える。
     */
    public static function normalize(string $text): string
    {
        $text = self::widthNormalize($text);
        $text = preg_replace('/[\s\x{3000}]+/u', '', $text) ?? $text;

        return trim($text);
    }

    /**
     * 全角英数記号→半角のみを行い、空白は残す。
     * 書式B は空白を語の境界として使うため、空白を消してはいけない。
     */
    private static function widthNormalize(string $text): string
    {
        return mb_convert_kana($text, 'a');
    }

    /**
     * raw で重複排除する。
     *
     * @param  array<int, array{raw: string, regulation: string|null, code: string}>  $codes
     * @return array<int, array{raw: string, regulation: string|null, code: string}>
     */
    private static function uniqueByRaw(array $codes): array
    {
        $seen = [];
        foreach ($codes as $c) {
            $seen[$c['raw']] = $c;
        }

        return array_values($seen);
    }
}
