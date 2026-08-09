<?php

declare(strict_types=1);

namespace App\Services\Parking;

final class AddressParser
{
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
        // === 前処理 ===
        $address = trim($address);
        // NFKC正規化（全角英数→半角、不可視文字除去等）
        if (class_exists(\Normalizer::class)) {
            $address = \Normalizer::normalize($address, \Normalizer::FORM_KC);
        }
        // 郵便番号を除去（〒XXX-XXXX）
        $address = preg_replace('/^〒[\d０-９]{3}[-ー][\d０-９]{4}[\s　]*/u', '', $address);
        // 括弧内テキストを除去（「中央市場通り」等の誤マッチ防止）
        $address = preg_replace('/[（(][^）)]*[）)]/u', '', $address);

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
            // 候補を優先順位順に収集（先にマッチしたものが高優先）
            // 優先: (a) 郡+町村 → (b) 政令市の市区 → (c) 市/区/町/村
            // 郡+町村 を最優先にすることで、町村名に「市」を含む自治体
            // （高市郡明日香村, 吉野郡下市町 等）を一般市パターンで途中まで切らずに取れる。
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

            // 最初にバリデーションを通過する候補を採用
            foreach ($candidates as $candidate) {
                $validated = self::validateCity($candidate);
                if ($validated !== '') {
                    $city = $validated;
                    break;
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
}
