<?php

declare(strict_types=1);

namespace App\Services\Parking;

use App\Support\MunicipalityCorrections;
use Illuminate\Support\Facades\DB;

final class AddressParser
{
    /**
     * 正規化済み municipalities.full_name の集合（key=正規化名, value=元の名称）。
     * プロセス内で1回だけ読み込み、以降は再利用する。
     * null=未ロード、[]=ロード済み（DB接続不可・空を含む）。
     *
     * @var array<string,string>|null
     */
    private static ?array $municipalitySet = null;

    private const PREFECTURES = [
        '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
        '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
        '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
        '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
        '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
        '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
        '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
    ];

    /** 正規表現パターンで誤マッチする市名のホワイトリスト（先頭一致で優先判定） */
    private const SPECIAL_CITIES = [
        // 市名に「市」を含む（一般市パターンで誤マッチ）
        '四日市市', '廿日市市', '市川市', '市原市', '野々市市',
        // 市名に「郡」を含む（郡パターンで誤マッチ）
        '蒲郡市',
    ];

    /** 政令指定都市 + 既知の問題ケースの市→県マッピング */
    private const CITY_TO_PREFECTURE = [
        '札幌市' => '北海道',
        '仙台市' => '宮城県',
        'さいたま市' => '埼玉県',
        '千葉市' => '千葉県',
        '横浜市' => '神奈川県',
        '川崎市' => '神奈川県',
        '相模原市' => '神奈川県',
        '新潟市' => '新潟県',
        '静岡市' => '静岡県',
        '浜松市' => '静岡県',
        '名古屋市' => '愛知県',
        '京都市' => '京都府',
        '大阪市' => '大阪府',
        '堺市' => '大阪府',
        '神戸市' => '兵庫県',
        '岡山市' => '岡山県',
        '広島市' => '広島県',
        '北九州市' => '福岡県',
        '福岡市' => '福岡県',
        '熊本市' => '熊本県',
        '伊丹市' => '兵庫県',
    ];

    /**
     * @return array{prefecture: string, city: string}
     */
    public function parse(string $address): array
    {
        $address = self::preprocess($address);

        $pref = '';
        $rest = $address;

        // 都道府県を抽出・除去
        foreach (self::PREFECTURES as $p) {
            if (str_starts_with($address, $p)) {
                $pref = $p;
                $rest = mb_substr($address, mb_strlen($p));
                break;
            }
        }

        // 都道府県除去後のtrim（「東京都 町田市…」のようなスペース混入対策）
        // 注: ltrimは全角スペースでバイト破壊するためpreg_replaceを使用
        $rest = preg_replace('/^[\s　]+/u', '', $rest);

        // 市区町村抽出用: 番地部分（数字以降）を除去して誤マッチ防止
        $cityRest = preg_replace('/[0-9０-９].*/u', '', $rest);
        if ($cityRest === '' || $cityRest === null) {
            $cityRest = $rest;
        }

        // 市区町村を抽出（優先順位付き、バリデーション失敗時は次パターンへフォールスルー）
        // 注: standalone 町/村 は町名（本町, 三宮町等）と区別できないため対象外
        //     郡+町/村 は郡パターンで処理
        $city = '';

        // 0. 「市」を含む市名のホワイトリスト（四日市市, 市川市等）
        //    一般市パターンで誤マッチするため先に処理
        foreach (self::SPECIAL_CITIES as $sc) {
            if (str_starts_with($rest, $sc)) {
                $city = $sc;
                break;
            }
        }

        if ($city === '') {
            // 候補を4パターンで全て収集する（優先順位で決め打ちせず、全候補を集める）。
            //   (a) 郡+町村 / (b) 政令市の市区 / (c) 一般市 / (c) 特別区
            // 配列順は従来のフォールバック優先順位（郡町村 → 政令市市区 → 市 → 区）を保つ。
            $candidates = [];

            // (a) 郡+町村: ○○郡○○町/村（「神崎郡市川町」「高市郡明日香村」等を正しく処理）
            //     ただし「大和郡山市」のように "郡" を含む市名を郡町村と誤認しないようガードする。
            if (preg_match('/^(.+?郡.+?[町村])/u', $cityRest, $m)
                && ! self::gunMatchLooksLikeCity($m[1])
            ) {
                $candidates[] = $m[1];
            }
            // (b) 政令指定都市: ○○市○○区
            if (preg_match('/^(.+?市.+?区)/u', $cityRest, $m)) {
                $candidates[] = $m[1];
            }
            // (c) 一般市
            if (preg_match('/^(.+?市)/u', $cityRest, $m)) {
                $candidates[] = $m[1];
            }
            // (c) 東京特別区
            if (preg_match('/^(.+?区)/u', $cityRest, $m)) {
                $candidates[] = $m[1];
            }

            // (1) 権威データ（municipalities.full_name）に実在する候補を採用。
            //     複数該当する場合は文字数が最長のものを採る（例: 浜松市中央区 > 浜松市）。
            $city = self::matchAgainstMunicipalities($candidates);

            // (2) 実在一致が1つも無ければ、従来の優先順位（配列順で最初にバリデーション
            //     を通過した候補）でフォールバックする。挙動を後退させない。
            if ($city === '') {
                foreach ($candidates as $candidate) {
                    $validated = self::validateCity($candidate);
                    if ($validated !== '') {
                        $city = $validated;
                        break;
                    }
                }
            }
        }

        // 市名から都道府県を推定・矯正（政令指定都市マップ）
        foreach (self::CITY_TO_PREFECTURE as $cityName => $correctPref) {
            if ($city !== '' && str_starts_with($city, $cityName)) {
                $pref = $correctPref;
                break;
            }
        }

        return ['prefecture' => $pref, 'city' => $city];
    }

    /**
     * parse() に加えて、廃止・改称・再編された市区町村の補正
     * （App\Support\MunicipalityCorrections）を適用した結果を返す。
     *
     * parse() 自体は変更していない（既存の呼び出し元＝ジオコーディングの A-3 判定・
     * 各バックフィルの挙動を動かさないため）。補正が要る呼び出し元だけがこちらを使う。
     *
     * @return array{prefecture: string, city: string}
     */
    public function parseWithCorrections(string $address): array
    {
        $parsed = $this->parse($address);
        if ($parsed['city'] === '') {
            return $parsed;
        }

        // street_prefix / street_exceptions は「市区町村より後ろ」を見るので、
        // parse() と同じ前処理をかけた住所から都道府県・市区町村を取り除いて渡す。
        $street = self::streetAfterCity(self::preprocess($address), $parsed['prefecture'], $parsed['city']);

        [$city] = MunicipalityCorrections::apply($parsed['prefecture'], $parsed['city'], $street);

        // 補正表のキーは郡を含まない町村名（「愛知県|長久手町」「愛知県|七宝町」など）。
        // parse() は住所に郡があれば「愛知郡長久手町」と郡付きで返すため直接は当たらない。
        // 直接一致しなかったときだけ郡を落として同じ表を引き直す（表そのものは増やさない）。
        if ($city === $parsed['city'] && preg_match('/^(.+?郡)(.+[町村])$/u', $parsed['city'], $m)) {
            [$withoutCounty] = MunicipalityCorrections::apply($parsed['prefecture'], $m[2], $street);
            if ($withoutCounty !== $m[2]) {
                $city = $withoutCounty;
            }
        }

        return ['prefecture' => $parsed['prefecture'], 'city' => $city];
    }

    /**
     * parse() の前処理（trim / NFKC / 郵便番号除去 / 括弧内除去）。
     *
     * 正規化に失敗した場合（不正なUTF-8等で Normalizer が false、preg_replace が null を
     * 返す場合）は空文字になる。切り出し前の parse() も同じ入力で最終的に
     * prefecture/city ともに空を返していたため、結果は変わらない。
     */
    private static function preprocess(string $address): string
    {
        $address = trim($address);
        // NFKC正規化（全角英数→半角、不可視文字除去等）
        if (class_exists(\Normalizer::class)) {
            $address = \Normalizer::normalize($address, \Normalizer::FORM_KC);
        }
        // 郵便番号を除去（〒XXX-XXXX）
        $address = preg_replace('/^〒[\d０-９]{3}[-ー][\d０-９]{4}[\s　]*/u', '', (string) $address);
        // 括弧内テキストを除去（「中央市場通り」等の誤マッチ防止）
        $address = preg_replace('/[（(][^）)]*[）)]/u', '', (string) $address);

        return (string) $address;
    }

    /**
     * 前処理済み住所から、先頭の都道府県と市区町村を取り除いた残り（街区以下）を返す。
     * parse() は政令市マップで prefecture を矯正することがあり、住所の先頭と一致しない
     * 場合があるため、いずれも先頭一致したときだけ取り除く。
     */
    private static function streetAfterCity(string $address, string $prefecture, string $city): string
    {
        $rest = $address;
        if ($prefecture !== '' && str_starts_with($rest, $prefecture)) {
            $rest = mb_substr($rest, mb_strlen($prefecture));
        }
        $rest = preg_replace('/^[\s　]+/u', '', $rest) ?? $rest;

        if ($city !== '' && str_starts_with($rest, $city)) {
            $rest = mb_substr($rest, mb_strlen($city));
        }

        return preg_replace('/^[\s　]+/u', '', $rest) ?? $rest;
    }

    /**
     * 郡パターン「○○郡○○町/村」でマッチした文字列が、実際には "郡" を含む市名
     * （大和郡山市 等）を誤って捕捉していないか判定する。
     *
     * 郡より後ろ（本来は町村名部分）に「市」が現れ、かつその部分が「市」で始まらず、
     * 末尾も「市町」「市村」でない場合は、市名の一部を巻き込んでいるとみなして真を返す。
     *   大和郡山市本町 → 郡以降「山市本町」→ 市が中間      → true（市の誤マッチ）
     *   吉野郡下市町   → 郡以降「下市町」  → 末尾が「市町」  → false（正しい町村）
     *   神崎郡市川町   → 郡以降「市川町」  → 「市」で始まる  → false（正しい町村）
     *   高市郡明日香村 → 郡以降「明日香村」→ 「市」を含まない → false（正しい町村）
     */
    private static function gunMatchLooksLikeCity(string $match): bool
    {
        $gunPos = mb_strpos($match, '郡');
        if ($gunPos === false) {
            return false;
        }
        $afterGun = mb_substr($match, $gunPos + 1);

        if (! str_contains($afterGun, '市')) {
            return false;
        }
        // 町村名そのものが「市」で始まる（市川町 等）／末尾が「市町」「市村」（下市町, 余市町 等）
        // なら「市」は町村名の一部。市名の巻き込みではない。
        if (str_starts_with($afterGun, '市') || preg_match('/市[町村]$/u', $afterGun)) {
            return false;
        }

        return true;
    }

    /**
     * パース結果のcityをバリデーションし、不正値は空文字に落とす
     */
    private static function validateCity(string $city): string
    {
        if ($city === '') {
            return '';
        }

        // 15文字以上 → 住所がそのまま混入している可能性大
        if (mb_strlen($city) >= 15) {
            return '';
        }

        // 数字・丁目・番地・号・先が含まれる → 住所の一部が混入
        if (preg_match('/[\d０-９]|丁目|番地|号|先/u', $city)) {
            return '';
        }

        // 括弧が含まれる → 補足情報の混入
        if (preg_match('/[（）()]/u', $city)) {
            return '';
        }

        // 市区町村郡で終わらない → 有効な市区町村名ではない
        if (!preg_match('/[市区町村郡]$/u', $city)) {
            return '';
        }

        return $city;
    }

    /**
     * 候補群のうち municipalities.full_name に実在するものを返す。
     * 複数該当時は文字数が最長のもの（同長は配列順で先勝ち＝優先順位を尊重）。
     * 実在一致が無い、またはデータ未ロード時は空文字を返す。
     *
     * @param  list<string>  $candidates
     */
    private static function matchAgainstMunicipalities(array $candidates): string
    {
        $set = self::municipalitySet();
        if ($set === []) {
            return '';
        }

        $best = '';
        foreach ($candidates as $candidate) {
            $key = self::normalizeForMatch($candidate);
            if ($key === '' || ! isset($set[$key])) {
                continue;
            }
            if (mb_strlen($candidate) > mb_strlen($best)) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * municipalities.full_name の集合をプロセス内で1回だけ読み込み、static に保持する。
     * DBに接続できない場合（テスト等）は例外を握りつぶし空配列を返す（＝従来ロジックへ）。
     *
     * @return array<string,string> key=正規化済みfull_name, value=元のfull_name
     */
    private static function municipalitySet(): array
    {
        if (self::$municipalitySet !== null) {
            return self::$municipalitySet;
        }

        // 読込を試みたら結果（空を含む）を保持し、以降クエリを発行しない。
        self::$municipalitySet = [];

        try {
            $set = [];
            foreach (DB::table('municipalities')->pluck('full_name') as $name) {
                $key = self::normalizeForMatch((string) $name);
                if ($key !== '') {
                    $set[$key] = (string) $name;
                }
            }
            self::$municipalitySet = $set;
        } catch (\Throwable $e) {
            // DB未接続・テーブル未作成等はフォールバックへ委ねる。
            self::$municipalitySet = [];
        }

        return self::$municipalitySet;
    }

    /**
     * 照合用の正規化: 「ヶ」→「ケ」の表記ゆれ吸収 + 空白除去。
     * （N03は「龍ケ崎市」、住所は「龍ヶ崎市」のような差があるため）
     */
    private static function normalizeForMatch(string $s): string
    {
        $s = str_replace('ヶ', 'ケ', $s);
        $s = preg_replace('/[\s　]+/u', '', $s);

        return $s ?? '';
    }

    /**
     * テスト用: municipalities の読み込みをモックする。
     * full_name の配列を渡すと、以降の照合はこの集合に対して行われる。
     *
     * @param  list<string>  $fullNames
     */
    public static function setMunicipalitiesForTesting(array $fullNames): void
    {
        $set = [];
        foreach ($fullNames as $name) {
            $key = self::normalizeForMatch($name);
            if ($key !== '') {
                $set[$key] = $name;
            }
        }
        self::$municipalitySet = $set;
    }

    /**
     * テスト用: キャッシュ済みの municipalities 集合を破棄し、次回再読み込みさせる。
     */
    public static function flushMunicipalityCache(): void
    {
        self::$municipalitySet = null;
    }
}
