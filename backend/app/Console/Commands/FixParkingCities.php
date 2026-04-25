<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeParking;
use App\Services\Parking\AddressParser;
use Illuminate\Console\Command;

class FixParkingCities extends Command
{
    protected $signature = 'parking:fix-cities
        {--dry-run : 保存せず確認のみ}
        {--prefecture= : 特定の都道府県のみ対象}';

    protected $description = 'bike_parkingsのcity/prefectureをaddressから再パースして修正します';

    private const PREFECTURES = [
        '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
        '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
        '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
        '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
        '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
        '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
        '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
    ];

    private const DESIGNATED_CITIES = [
        'さいたま市', '横浜市', '川崎市', '相模原市', '名古屋市', '京都市',
        '大阪市', '神戸市', '福岡市', '北九州市', '札幌市', '仙台市',
        '千葉市', '新潟市', '静岡市', '浜松市', '岡山市', '広島市', '熊本市', '堺市',
    ];

    private const CITY_TO_PREF = [
        '札幌市' => '北海道', '仙台市' => '宮城県', 'さいたま市' => '埼玉県',
        '千葉市' => '千葉県', '横浜市' => '神奈川県', '川崎市' => '神奈川県',
        '相模原市' => '神奈川県', '新潟市' => '新潟県', '静岡市' => '静岡県',
        '浜松市' => '静岡県', '名古屋市' => '愛知県', '京都市' => '京都府',
        '大阪市' => '大阪府', '堺市' => '大阪府', '神戸市' => '兵庫県',
        '岡山市' => '岡山県', '広島市' => '広島県', '北九州市' => '福岡県',
        '福岡市' => '福岡県', '熊本市' => '熊本県',
    ];

    public function handle(AddressParser $parser): void
    {
        $dryRun = (bool) $this->option('dry-run');
        $prefFilter = $this->option('prefecture');

        if ($dryRun) {
            $this->info('[DRY RUN] データは保存されません。');
        }

        $query = BikeParking::whereNotNull('address')->where('address', '!=', '');

        if ($prefFilter) {
            $query->where('prefecture', $prefFilter);
        }

        $total = (clone $query)->count();
        $this->info("対象レコード: {$total}件");

        $fixed = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar($total);
        $bar->start();

        $query->chunk(500, function ($rows) use ($parser, $dryRun, &$fixed, &$skipped, $bar) {
            foreach ($rows as $row) {
                $bar->advance();

                // Step 1: AddressParser でパース
                $parsed = $parser->parse($row->address);
                $newPref = $parsed['prefecture'] ?: $row->prefecture;
                $newCity = $parsed['city'];

                // Step 2: パーサー出力バリデーション
                if ($newCity !== '' && !self::isCleanCity($newCity)) {
                    // 政令指定都市はStep4で区補完するため保持
                    if (!in_array($newCity, self::DESIGNATED_CITIES)) {
                        $newCity = '';
                    }
                }

                // Step 3: 空cityの場合のフォールバック
                if ($newCity === '' && $row->city !== '') {
                    if (self::isCleanCity($row->city)) {
                        // 既存cityが正常なら維持
                        $newCity = $row->city;
                    } elseif (in_array($row->city, self::DESIGNATED_CITIES)) {
                        // 政令指定都市はStep4で区補完するため保持
                        $newCity = $row->city;
                    } else {
                        // 不正な既存city → 修復を試みる
                        $newCity = self::repairBadCity($row->city);
                    }
                }

                // Step 4: 政令指定都市の区補完（addressから直接抽出）
                if (in_array($newCity, self::DESIGNATED_CITIES)) {
                    $enhanced = self::extractWardFromAddress($newCity, $row->address);
                    if ($enhanced !== null) {
                        $newCity = $enhanced;
                    }
                }

                // Step 5: CITY_TO_PREF矯正（政令指定都市の都道府県を正しく設定）
                foreach (self::CITY_TO_PREF as $cityName => $correctPref) {
                    if ($newCity !== '' && str_starts_with($newCity, $cityName)) {
                        $newPref = $correctPref;
                        break;
                    }
                }

                // Step 6: 変更チェック
                if ($row->prefecture === $newPref && $row->city === $newCity) {
                    $skipped++;
                    continue;
                }

                if ($dryRun) {
                    $this->newLine();
                    $this->line("ID:{$row->id} [{$row->prefecture}|{$row->city}] → [{$newPref}|{$newCity}]");
                } else {
                    $row->update(['prefecture' => $newPref, 'city' => $newCity]);
                }
                $fixed++;
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("完了: 修正={$fixed}, スキップ={$skipped}");
    }

    /**
     * cityが正常な市区町村かどうか
     */
    private static function isCleanCity(string $city): bool
    {
        if ($city === '') {
            return false;
        }

        // 1. 都道府県名が含まれている（「神奈川県横浜市」「岩手県一関市」等）
        foreach (self::PREFECTURES as $pref) {
            if (str_starts_with($city, $pref)) {
                return false;
            }
        }

        // 2. 市区町村郡で終わらない
        if (!preg_match('/[市区町村郡]$/u', $city)) {
            return false;
        }

        // 3. 2文字以下で市/区で終わらない → 不完全（東村, 蒲郡, 中郡 等）
        if (mb_strlen($city) <= 2 && !preg_match('/[市区]$/u', $city)) {
            return false;
        }

        // 4. 政令指定都市に完全一致（区がない → 不完全）
        if (in_array($city, self::DESIGNATED_CITIES)) {
            return false;
        }

        // 5. 重複サフィックス（区市, 町市, 市市）
        if (preg_match('/(区市|町市)$/u', $city)) {
            return false;
        }
        if (preg_match('/市市$/u', $city) && !in_array($city, ['四日市市', '廿日市市'])) {
            return false;
        }

        // 6. 重複市名（神戸市神戸市兵庫区 等）
        if (preg_match('/^(.+市)\1/u', $city)) {
            return false;
        }

        return true;
    }

    /**
     * 不正cityの修復を試みる
     */
    private static function repairBadCity(string $city): string
    {
        // 1. 都道府県プレフィックス除去
        $stripped = self::stripPrefectureFromCity($city);
        if ($stripped !== '' && self::isCleanCity($stripped)) {
            return $stripped;
        }

        // 2. 重複市名除去: 神戸市神戸市兵庫区 → 神戸市兵庫区
        if (preg_match('/^(.+市)\1/u', $city)) {
            $deduped = preg_replace('/^(.+市)\1/u', '$1', $city);
            if ($deduped !== '' && self::isCleanCity($deduped)) {
                return $deduped;
            }
        }

        // 3. 重複隣接文字除去: 川崎市高高津区 → 川崎市高津区
        if (preg_match('/([\p{Han}])\1/u', $city)) {
            $deduped = preg_replace('/([\p{Han}])\1/u', '$1', $city);
            if ($deduped !== '' && $deduped !== $city && self::isCleanCity($deduped)) {
                return $deduped;
            }
        }

        // 4. 政令指定都市の市欠落: 名古屋千種区 → 名古屋市千種区
        foreach (self::DESIGNATED_CITIES as $dc) {
            $base = preg_replace('/市$/u', '', $dc);
            if ($base === $dc) {
                continue; // 堺市等、baseが変わらないケースはスキップ
            }
            if (str_starts_with($city, $base) && !str_starts_with($city, $dc)
                && mb_strlen($city) > mb_strlen($base)) {
                $remainder = mb_substr($city, mb_strlen($base));
                $withShi = $dc . $remainder;
                if (self::isCleanCity($withShi)) {
                    return $withShi;
                }
            }
        }

        // 5. 重複サフィックス除去: 新宿区市 → 新宿区
        if (preg_match('/^(.+区)市$/u', $city, $m)) {
            if (self::isCleanCity($m[1])) {
                return $m[1];
            }
        }
        if (preg_match('/^(.+町)市$/u', $city, $m)) {
            if (self::isCleanCity($m[1])) {
                return $m[1];
            }
        }

        return '';
    }

    /**
     * 不正cityからprefecture prefixを除去して正常なcityを抽出
     */
    private static function stripPrefectureFromCity(string $city): string
    {
        foreach (self::PREFECTURES as $pref) {
            if (str_starts_with($city, $pref)) {
                $stripped = mb_substr($city, mb_strlen($pref));
                if ($stripped !== '' && mb_strlen($stripped) >= 2 && preg_match('/[市区町村]$/u', $stripped)) {
                    return $stripped;
                }

                return '';
            }
        }

        return '';
    }

    /**
     * 政令指定都市のaddressから「○○市○○区」を直接抽出
     */
    private static function extractWardFromAddress(string $designatedCity, string $address): ?string
    {
        $addr = trim($address);
        if (class_exists(\Normalizer::class)) {
            $addr = \Normalizer::normalize($addr, \Normalizer::FORM_KC);
        }
        $addr = preg_replace('/[（(][^）)]*[）)]/u', '', $addr);

        $cityPattern = preg_quote($designatedCity, '/');

        // 市名の直後に1～5文字（漢字/ひらがな）+ 区 を抽出
        if (preg_match('/' . $cityPattern . '\s*([\p{Han}\p{Hiragana}]{1,5}区)/u', $addr, $m)) {
            return $designatedCity . $m[1];
        }

        return null;
    }
}
