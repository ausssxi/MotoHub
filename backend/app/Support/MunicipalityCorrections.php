<?php

declare(strict_types=1);

namespace App\Support;

/**
 * 廃止・改称・再編された市区町村を現行名へ補正する知識と、その適用ロジック。
 *
 * もとは GsiGeocodingService の private const に閉じていたため、座標を取るときだけ
 * 正しく直り、city カラム自体は旧名のまま残っていた。同じ知識を住所パース側
 * （AddressParser）や是正コマンド（addresses:fix-city）からも使えるよう、
 * DB にもHTTPにも依存しない static な値クラスとして App\Support へ切り出す。
 * AddressNormalizer / JapanCityPrefecture と同じ位置づけ。
 *
 * ログは呼び出し側の関心（GSI ジオコーディングのログ文言など）なので、ここでは出さず
 * 「何を適用したか」をイベント列として返し、文言と出力方法は呼び出し側に委ねる。
 */
final class MunicipalityCorrections
{
    /** CITY_CORRECTIONS により city を置換した */
    public const EVENT_CITY_CORRECTION = 'city_correction';

    /** 分割された旧区を street 先頭一致で振り分けた */
    public const EVENT_SPLIT_WARD_MATCHED = 'split_ward_matched';

    /** 分割された旧区を既定の区へ振り分けた（例外町名の追加候補） */
    public const EVENT_SPLIT_WARD_DEFAULT = 'split_ward_default';

    /** STREET_WARD_REPLACEMENTS により street 内の廃止行政区を置換した */
    public const EVENT_STREET_WARD_REPLACEMENT = 'street_ward_replacement';

    /**
     * 廃止・改称された市区町村の補正表。キーは「都道府県|旧city」で、
     * 都道府県と city の組み合わせが一致したときだけ適用する（同名の別自治体を
     * 巻き込まないよう city 単独では判定しない）。
     *
     * 各エントリ:
     *   'city'              置換後の現行 city（必須）
     *   'street_prefix'     street(=address) の先頭に付与する旧地名（任意）。
     *                       合併で旧町名が現行住所に大字として残るケース用。
     *   'street_exceptions' street 先頭一致で city を振り分ける例外表（任意）。
     *                       形式は [町名(先頭一致) => 現行city]。旧区が複数の現行区へ
     *                       分割されたケース用（例: 旧浜松市北区 → 浜名区/中央区）。
     *                       どれにも一致しなければ 'city' を既定として使う。
     *
     * 出典: 総務省「市町村合併の記録」/ 各自治体の合併・市制施行・区再編告示。
     * すべて国土地理院 AddressSearch API で実際に座標が返ることを確認済み。
     *   兵庫県 篠山市     : 2019-05-01 「丹波篠山市」へ改称
     *   愛知県 長久手町   : 2012-01-04 市制施行で「長久手市」
     *   福岡県 那珂川町   : 2018-10-01 市制施行で「那珂川市」
     *   千葉県 大網白里町 : 2013-01-01 市制施行で「大網白里市」
     *   静岡県 新居町     : 2010-03-23 湖西市へ編入。現行住所は「湖西市新居町…」なので
     *                       street 先頭へ「新居町」を残す
     *   愛知県 七宝町     : 2010-03-22 美和町・甚目寺町と合併し「あま市」。現行住所は
     *                       「あま市七宝町…」なので street 先頭へ「七宝町」を残す
     *   兵庫県 北区       : 神戸市の行政区。city 列に「神戸市」が欠けた行の補正
     *   静岡県 浜松市各区 : 2024-01-01 に7区→3区（中央区・浜名区・天竜区）へ再編。旧区名
     *                       だと地理院は区を無視し市の重心しか返さないため現行区へ補正する。
     *                       旧中/東/西/南区→中央区、旧浜北区→浜名区。旧北区は浜名区と中央区へ
     *                       分割されたため street_exceptions で先頭町名により振り分ける。
     *
     * @var array<string, array{city: string, street_prefix?: string, street_exceptions?: array<string, string>}>
     */
    public const CITY_CORRECTIONS = [
        '兵庫県|篠山市' => ['city' => '丹波篠山市'],
        '愛知県|長久手町' => ['city' => '長久手市'],
        '福岡県|那珂川町' => ['city' => '那珂川市'],
        '千葉県|大網白里町' => ['city' => '大網白里市'],
        '静岡県|新居町' => ['city' => '湖西市', 'street_prefix' => '新居町'],
        '愛知県|七宝町' => ['city' => 'あま市', 'street_prefix' => '七宝町'],
        '兵庫県|北区' => ['city' => '神戸市北区'],

        // 浜松市 2024-01-01 区再編（7区→3区）
        '静岡県|浜松市中区' => ['city' => '浜松市中央区'],
        '静岡県|浜松市東区' => ['city' => '浜松市中央区'],
        '静岡県|浜松市西区' => ['city' => '浜松市中央区'],
        '静岡県|浜松市南区' => ['city' => '浜松市中央区'],
        '静岡県|浜松市浜北区' => ['city' => '浜松市浜名区'],
        // 旧北区は浜名区と中央区へ分割。既定は浜名区、street 先頭が例外表の町名なら中央区。
        // 例外表は後から町名を追記できる（実測で中央区と判明した町名をここへ足す）。
        '静岡県|浜松市北区' => [
            'city' => '浜松市浜名区',
            'street_exceptions' => [
                '初生町' => '浜松市中央区',
            ],
        ],
    ];

    /**
     * 廃止された行政区を street(=address) 内で置換する表。キーは「都道府県|city」で、
     * その city のときだけ適用する。city は変えず address 内の表記だけを直す。
     * 値は [旧表記 => 新表記]。
     *
     * 出典: 奥州市は 2006-02-20 に5市町村が合併して発足し、旧市町村単位の地域自治区
     * （水沢区・江刺区・前沢区・胆沢区・衣川区）を設けていたが後に廃止され、現行住所は
     * 区を含まない（例: 岩手県奥州市水沢真城…）。国土地理院 AddressSearch で確認済み。
     *
     * @var array<string, array<string, string>>
     */
    public const STREET_WARD_REPLACEMENTS = [
        '岩手県|奥州市' => [
            '水沢区' => '水沢',
            '胆沢区' => '胆沢',
            '衣川区' => '衣川',
        ],
    ];

    /**
     * 都道府県と city の組み合わせが表にあるときだけ補正を適用する。
     * 表に無ければ引数をそのまま返す（他の組み合わせの挙動は変えない）。
     *
     * 第3要素として「何を適用したか」のイベント列を返す。ログを出したい呼び出し側は
     * type ごとに文言を割り当てて context をそのまま渡せばよい。
     *
     * @return array{0: string, 1: string, 2: list<array{type: string, context: array<string, mixed>}>}
     *         [補正後 city, 補正後 address, イベント列]
     */
    public static function apply(string $prefecture, string $city, string $address): array
    {
        $events = [];

        // 改称・市制施行・編入（city を置換し、必要なら旧地名を street 先頭へ残す）
        $key = $prefecture.'|'.$city;
        if (isset(self::CITY_CORRECTIONS[$key])) {
            $rule = self::CITY_CORRECTIONS[$key];
            $newCity = $rule['city'];
            $prefix = $rule['street_prefix'] ?? '';

            // 旧区が複数の現行区へ分割されたケース: street 先頭が例外表の町名に一致すれば
            // その区を採用。一致しなければ既定（$rule['city']）を使い、後から「実は別区
            // だった町名」を洗い出せるようイベントに街区を記録する。
            if (! empty($rule['street_exceptions'])) {
                $matchedCity = null;
                $matchedTown = null;
                foreach ($rule['street_exceptions'] as $town => $exceptionCity) {
                    if (str_starts_with($address, $town)) {
                        $matchedCity = $exceptionCity;
                        $matchedTown = $town;
                        break;
                    }
                }
                if ($matchedCity !== null) {
                    $newCity = $matchedCity;
                    $events[] = [
                        'type' => self::EVENT_SPLIT_WARD_MATCHED,
                        'context' => [
                            'prefecture' => $prefecture,
                            'from_city' => $city,
                            'to_city' => $newCity,
                            'matched_town' => $matchedTown,
                            'address' => $address,
                        ],
                    ];
                } else {
                    $events[] = [
                        'type' => self::EVENT_SPLIT_WARD_DEFAULT,
                        'context' => [
                            'prefecture' => $prefecture,
                            'from_city' => $city,
                            'to_city' => $newCity,
                            'address' => $address,
                        ],
                    ];
                }
            }

            $newAddress = $address;
            if ($prefix !== '' && ! str_starts_with($newAddress, $prefix)) {
                $newAddress = $prefix.$newAddress;
            }

            $events[] = [
                'type' => self::EVENT_CITY_CORRECTION,
                'context' => [
                    'prefecture' => $prefecture,
                    'from_city' => $city,
                    'to_city' => $newCity,
                    'street_prefix' => $prefix,
                    'address_before' => $address,
                    'address_after' => $newAddress,
                ],
            ];

            $city = $newCity;
            $address = $newAddress;
        }

        // 廃止された行政区を street 内で置換（city はそのまま）
        $wardKey = $prefecture.'|'.$city;
        if (isset(self::STREET_WARD_REPLACEMENTS[$wardKey])) {
            $replaced = strtr($address, self::STREET_WARD_REPLACEMENTS[$wardKey]);
            if ($replaced !== $address) {
                $events[] = [
                    'type' => self::EVENT_STREET_WARD_REPLACEMENT,
                    'context' => [
                        'prefecture' => $prefecture,
                        'city' => $city,
                        'address_before' => $address,
                        'address_after' => $replaced,
                    ],
                ];

                $address = $replaced;
            }
        }

        return [$city, $address, $events];
    }
}
