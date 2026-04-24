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

        $rows = $query->get();
        $fixed = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $parsed = $parser->parse($row->address);

            $newPref = $parsed['prefecture'] ?: $row->prefecture;
            $newCity = $parsed['city'];

            // パーサー出力もバリデーション（旧パーサーの不正出力対策）
            if ($newCity !== '' && !self::isCleanCity($newCity)) {
                $newCity = '';
            }

            // パーサーが空cityの場合のフォールバック
            if ($newCity === '' && $row->city !== '') {
                if (self::isCleanCity($row->city)) {
                    // 既存cityが正常なら維持
                    $newCity = $row->city;
                } else {
                    // 不正な既存city → prefecture prefix除去を試みる
                    $newCity = self::stripPrefectureFromCity($row->city);
                }
            }

            if ($row->prefecture === $newPref && $row->city === $newCity) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $this->line("ID:{$row->id} [{$row->prefecture}|{$row->city}] → [{$newPref}|{$newCity}]");
            } else {
                $row->update(['prefecture' => $newPref, 'city' => $newCity]);
            }
            $fixed++;
        }

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
        //    堺市(2文字), 柏市(2文字), 港区(2文字) は正常
        if (mb_strlen($city) <= 2 && !preg_match('/[市区]$/u', $city)) {
            return false;
        }

        return true;
    }

    /**
     * 不正cityからprefecture prefixを除去して正常なcityを抽出
     */
    private static function stripPrefectureFromCity(string $city): string
    {
        foreach (self::PREFECTURES as $pref) {
            if (str_starts_with($city, $pref)) {
                $stripped = mb_substr($city, mb_strlen($pref));
                // 除去後が有効な市区町村なら採用（2文字以上 + 正当な末尾）
                if ($stripped !== '' && mb_strlen($stripped) >= 2 && preg_match('/[市区町村]$/u', $stripped)) {
                    return $stripped;
                }

                return '';
            }
        }

        return '';
    }
}
