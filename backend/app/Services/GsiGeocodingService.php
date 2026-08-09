<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Parking\AddressParser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * 国土地理院（GSI）住所検索APIによるジオコーディング（品質ガード付き）。
 * 承認時にのみ・住所がある場合にのみ呼ぶ（市区町村中心・県庁への誤ピンを避ける）。
 *
 * 過去の事故: city「都筑区」(横浜市欠け)で連結 → GSIが市区レベルまで解決できず
 * 県代表点(県庁)にフォールバック一致し、NULLより悪い「もっともらしく間違った座標」を保存。
 * → 結果の properties.title に市区名が含まれることを採用条件にする（A-2）。
 */
final class GsiGeocodingService
{
    private const ENDPOINT = 'https://msearch.gsi.go.jp/address-search/AddressSearch';

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
     */
    private const CITY_CORRECTIONS = [
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
     */
    private const STREET_WARD_REPLACEMENTS = [
        '岩手県|奥州市' => [
            '水沢区' => '水沢',
            '胆沢区' => '胆沢',
            '衣川区' => '衣川',
        ],
    ];

    public function __construct(
        private readonly AddressParser $addressParser,
    ) {}

    /**
     * prefecture + city + address からジオコーディング。見つからない/検証NG/失敗時は null。
     *
     * @return array{lat: float, lng: float}|null
     */
    public function geocode(string $prefecture, string $city, string $address): ?array
    {
        $prefecture = $this->normalize($prefecture);
        $city = $this->normalize($city);
        $address = $this->normalize($address);

        if ($address === '') {
            return null;
        }

        // city の先頭に prefecture が二重に入っている行の対策（本番ログに
        // 「福岡県福岡県古賀市…」「東京都東京都北区…」等）。city 列が「福岡県古賀市」のように
        // 都道府県名を含むのが原因。以降のクエリ組み立て・A-1・needle すべてで、
        // prefecture を剥がした city（例「古賀市」）を使う。
        if ($prefecture !== '' && str_starts_with($city, $prefecture)) {
            $city = mb_substr($city, mb_strlen($prefecture));
        }

        // A-1: address が city（または政令市の区名末尾要素）で始まる場合は重複を除去。
        // 例: city「横浜市都筑区」に対し address「都筑区荏田南…」→ 「荏田南…」
        $cityTail = null;
        if (preg_match('/市(.+?区)$/u', $city, $m)) {
            $cityTail = $m[1];
        }
        if ($city !== '' && str_starts_with($address, $city)) {
            $address = ltrim(mb_substr($address, mb_strlen($city)));
        } elseif ($cityTail !== null && str_starts_with($address, $cityTail)) {
            $address = ltrim(mb_substr($address, mb_strlen($cityTail)));
        }

        // 廃止・改称された市区町村を現行名へ補正する。クエリ組み立て前に適用し、
        // 置換後の city は下の needle 算出にもそのまま使われる。表に無い組み合わせは素通り。
        [$city, $address] = $this->applyMunicipalityCorrections($prefecture, $city, $address);

        $query = $prefecture.$city.$address;

        // A-3: 合併等で「愛知県長久手町愛知県長久手市菖蒲池1015」のように、先頭以外の位置に
        // prefecture が再出現し、その位置から現在の正しい住所（合併後の住所）が丸ごと始まって
        // いる行が本番ログにある。再出現位置から後ろだけをクエリとして使う。
        // このケースは city による needle 判定を行わず、代わりに「title が prefecture だけで
        // ないこと（市区町村以下が含まれること）」を採用条件にする（下の A-2 分岐参照）。
        $prefectureRestart = false;
        if ($prefecture !== '') {
            $pos = mb_strpos($query, $prefecture, 1);
            if ($pos !== false) {
                $query = mb_substr($query, $pos);
                $prefectureRestart = true;
            }
        }

        try {
            $response = Http::timeout(5)->get(self::ENDPOINT, ['q' => $query]);

            if (! $response->successful()) {
                Log::warning('GSI geocoding non-2xx', ['status' => $response->status(), 'query' => $query]);

                return null;
            }

            $features = $response->json();
            if (! is_array($features) || count($features) === 0) {
                return null; // 見つからず
            }

            $title = (string) ($features[0]['properties']['title'] ?? '');

            // A-2: title に市区町村レベルの照合語（needle）が含まれること。県名のみ＝上位レベルへの
            // フォールバック結果は不採用（誤って県庁所在地の座標を掴むのを防ぐ）。
            if ($prefectureRestart) {
                // A-3 でクエリを prefecture 再出現位置から切り出したケース。渡された city は元の
                // （合併前等の）値でクエリと食い違うため使わない。切り出し後のクエリから
                // AddressParser で市区町村を取り直し、その needle が title に含まれることを要求する。
                //   例: 「埼玉県鳩ヶ谷市南1-1-1」→ city「鳩ヶ谷市」。title「埼玉県鳩山」（鳩山町）は
                //       含まないので棄却（約40km 離れた誤自治体の座標を掴むのを防ぐ）。
                $parsedCity = $this->addressParser->parse($query)['city'];
                if ($parsedCity !== '') {
                    $needle = $this->cityNeedle($parsedCity);
                    if ($needle !== '' && ! str_contains(self::matchNormalize($title), self::matchNormalize($needle))) {
                        Log::warning('GSI geocoding rejected: title lacks city (A-3切り出し後・誤自治体の疑い)', [
                            'query' => $query,
                            'title' => $title,
                            'needle' => $needle,
                        ]);

                        return null;
                    }
                } else {
                    // 市区町村を取り直せないときは保険として「title が prefecture だけ」を棄却する。
                    $normTitle = self::matchNormalize($title);
                    $normPref = self::matchNormalize($prefecture);
                    $rest = str_starts_with($normTitle, $normPref)
                        ? mb_substr($normTitle, mb_strlen($normPref))
                        : $normTitle;
                    if ($rest === '') {
                        Log::warning('GSI geocoding rejected: title is prefecture only (県レベルfallbackの疑い)', [
                            'query' => $query,
                            'title' => $title,
                            'prefecture' => $prefecture,
                        ]);

                        return null;
                    }
                }
            } else {
                // 比較は matchNormalize を通す（title は「龍ケ崎市」、needle は「龍ヶ崎市」のように
                // 「ヶ／ケ」等の表記ゆれがあるため）。クエリ文字列そのものは変えない。
                $needle = $this->cityNeedle($city);
                if ($needle !== '' && ! str_contains(self::matchNormalize($title), self::matchNormalize($needle))) {
                    Log::warning('GSI geocoding rejected: title lacks city (県レベルfallbackの疑い)', [
                        'query' => $query,
                        'title' => $title,
                        'needle' => $needle,
                    ]);

                    return null;
                }
            }

            // GeoJSON: coordinates は [経度, 緯度] の順（lng, lat）
            $coords = $features[0]['geometry']['coordinates'] ?? null;
            if (! is_array($coords) || count($coords) < 2) {
                return null;
            }

            return [
                'lat' => (float) $coords[1],
                'lng' => (float) $coords[0],
            ];
        } catch (\Throwable $e) {
            Log::warning('GSI geocoding failed: '.$e->getMessage(), ['query' => $query]);

            return null;
        }
    }

    /**
     * 廃止・改称された市区町村を現行名へ補正する。都道府県と city の組み合わせが
     * 表にあるときだけ適用し、無ければ引数をそのまま返す（他の組み合わせの挙動は変えない）。
     * 補正したときは後から効果を追えるよう Log::info で記録する。
     *
     * @return array{0: string, 1: string} [補正後 city, 補正後 address]
     */
    private function applyMunicipalityCorrections(string $prefecture, string $city, string $address): array
    {
        // 改称・市制施行・編入（city を置換し、必要なら旧地名を street 先頭へ残す）
        $key = $prefecture.'|'.$city;
        if (isset(self::CITY_CORRECTIONS[$key])) {
            $rule = self::CITY_CORRECTIONS[$key];
            $newCity = $rule['city'];
            $prefix = $rule['street_prefix'] ?? '';

            // 旧区が複数の現行区へ分割されたケース: street 先頭が例外表の町名に一致すれば
            // その区を採用。一致しなければ既定（$rule['city']）を使い、後から「実は別区
            // だった町名」を洗い出せるよう Log::info に街区を記録する。
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
                    Log::info('GSI geocoding: 分割区を street 先頭一致で振り分け', [
                        'prefecture' => $prefecture,
                        'from_city' => $city,
                        'to_city' => $newCity,
                        'matched_town' => $matchedTown,
                        'address' => $address,
                    ]);
                } else {
                    Log::info('GSI geocoding: 分割区を既定へ振り分け（例外町名の追加候補）', [
                        'prefecture' => $prefecture,
                        'from_city' => $city,
                        'to_city' => $newCity,
                        'address' => $address,
                    ]);
                }
            }

            $newAddress = $address;
            if ($prefix !== '' && ! str_starts_with($newAddress, $prefix)) {
                $newAddress = $prefix.$newAddress;
            }

            Log::info('GSI geocoding: 廃止・改称された市区町村を補正', [
                'prefecture' => $prefecture,
                'from_city' => $city,
                'to_city' => $newCity,
                'street_prefix' => $prefix,
                'address_before' => $address,
                'address_after' => $newAddress,
            ]);

            $city = $newCity;
            $address = $newAddress;
        }

        // 廃止された行政区を street 内で置換（city はそのまま）
        $wardKey = $prefecture.'|'.$city;
        if (isset(self::STREET_WARD_REPLACEMENTS[$wardKey])) {
            $replaced = strtr($address, self::STREET_WARD_REPLACEMENTS[$wardKey]);
            if ($replaced !== $address) {
                Log::info('GSI geocoding: 廃止された行政区を street で置換', [
                    'prefecture' => $prefecture,
                    'city' => $city,
                    'address_before' => $address,
                    'address_after' => $replaced,
                ]);

                $address = $replaced;
            }
        }

        return [$city, $address];
    }

    /**
     * A-2 の照合語（needle）を city から決める。
     *  - 政令市の区（「〜市〜区」）: 区は再編・廃止で title に現れないことがある
     *    （実測: city「浜松市東区」→ GSIの title は「静岡県浜松市」/「静岡県浜松市浜名区」）。
     *    市の部分だけ（「浜松市」）を照合語にし、title が市を含めば採用する。
     *  - 郡下の町村（「〜郡〜町」「〜郡〜村」）: 地理院の title は郡を省略する
     *    （実測: city「高市郡明日香村」→ title「奈良県明日香村…」）。郡より後ろの町村名
     *    （「明日香村」）を照合語にする。
     *  - 東京23区など「〜区」のみ（例「大田区」）／市・町・村（例「三木市」）: city 全体で照合。
     */
    private function cityNeedle(string $city): string
    {
        if (preg_match('/^(.+?市).+区$/u', $city, $mm)) {
            return $mm[1];
        }
        if (preg_match('/郡(.+?[町村])$/u', $city, $mg)) {
            return $mg[1];
        }

        return $city;
    }

    /**
     * クエリ前の正規化: 全角英数記号→半角（全角ハイフン含む）、各種ダッシュ→半角ハイフン、
     * スペース（半/全角）除去、trim。
     */
    private function normalize(string $s): string
    {
        $s = mb_convert_kana($s, 'a');           // 全角英数記号→半角（全角ハイフンU+FF0Dも半角化）
        $s = str_replace(['−', '—', '―', 'ｰ'], '-', $s); // マイナス/ダッシュ/半角長音→ハイフン
        $s = preg_replace('/[\s\x{3000}]+/u', '', $s) ?? $s; // スペース除去（半角・全角）

        return trim($s);
    }

    /**
     * title と needle の「比較専用」正規化。地理院へ送るクエリ文字列には使わない。
     * ・「ヶ」→「ケ」、「ヵ」→「カ」（例: 龍ヶ崎市 vs 龍ケ崎市 / 保土ヶ谷区 vs 保土ケ谷区 の表記ゆれ吸収）
     * ・半角/全角スペース除去、trim
     */
    private static function matchNormalize(string $s): string
    {
        $s = str_replace(['ヶ', 'ヵ'], ['ケ', 'カ'], $s);
        $s = preg_replace('/[\s\x{3000}]+/u', '', $s) ?? $s;

        return trim($s);
    }
}
