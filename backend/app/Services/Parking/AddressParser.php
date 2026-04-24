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

    /** 市名に「市」を含む市（一般市パターンで誤マッチするためホワイトリスト処理） */
    private const SPECIAL_SHI_CITIES = ['四日市市', '廿日市市', '市川市', '市原市', '野々市市'];

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
        $rest = ltrim($rest, " \t　");

        // 市区町村抽出用: 番地部分（数字以降）を除去して誤マッチ防止
        $cityRest = preg_replace('/[0-9０-９].*/u', '', $rest);
        if ($cityRest === '' || $cityRest === null) {
            $cityRest = $rest;
        }

        // 市区町村を抽出（優先順位付き）
        // 注: standalone 町/村 は町名（本町, 三宮町等）と区別できないため対象外
        //     郡+町/村 は郡パターンで処理
        $city = '';

        // 0. 「市」を含む市名のホワイトリスト（四日市市, 市川市等）
        //    一般市パターンで誤マッチするため先に処理
        foreach (self::SPECIAL_SHI_CITIES as $sc) {
            if (str_starts_with($rest, $sc)) {
                $city = $sc;
                break;
            }
        }

        if ($city === '') {
            if (preg_match('/^(.+?市.+?区)/u', $cityRest, $m)) {
                // 1. 政令指定都市: ○○市○○区
                $city = $m[1];
            } elseif (preg_match('/^(.+?郡.+?[町村])/u', $cityRest, $m)
                // 「大和郡山市」のように市名に郡を含むケースを除外:
                // 郡マッチ内に「市」があるが「郡市」(郡直後に市)でない場合は偽マッチ
                && (!str_contains($m[1], '市') || str_contains($m[1], '郡市'))
            ) {
                // 2. 郡+町村: ○○郡○○町/村（市パターンより先に判定し「神崎郡市川町」等を正しく処理）
                $city = $m[1];
            } elseif (preg_match('/^(.+?市)/u', $cityRest, $m)) {
                // 3. 一般市（ホワイトリストで特殊ケース処理済みのため lookahead 不要）
                $city = $m[1];
            } elseif (preg_match('/^(.+?区)/u', $cityRest, $m)) {
                // 4. 東京特別区
                $city = $m[1];
            }
        }

        // バリデーション: 不正なcityを排除
        $city = self::validateCity($city);

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
