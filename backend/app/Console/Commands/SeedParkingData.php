<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BikeParking;
use App\Services\GsiGeocodingService;
use Illuminate\Console\Command;

class SeedParkingData extends Command
{
    protected $signature = 'parking:seed {--dry-run : 実際には保存しない}';
    protected $description = '主要都市のバイク駐車場の初期データを投入します';

    // forward ジオコーディングは国土地理院(GSI)のみを使う（Nominatim/OSM/Google は規約違反のため禁止）。
    public function handle(GsiGeocodingService $geocoder): void
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('[DRY RUN] データは保存されません。');
        }

        $parkings = $this->getParkingData();

        $this->info(count($parkings) . '件の駐車場データを投入します...');
        $bar = $this->output->createProgressBar(count($parkings));
        $bar->start();

        $successCount = 0;
        $failCount = 0;

        foreach ($parkings as $data) {
            // 住所から座標を取得（国土地理院・GsiGeocodingService 経由。prefecture/city/address はデータに同梱）。
            $coords = $geocoder->geocode(
                (string) ($data['prefecture'] ?? ''),
                (string) ($data['city'] ?? ''),
                (string) $data['address']
            );
            if (!$coords) {
                $this->newLine();
                $this->warn("座標取得失敗: {$data['name']} ({$data['address']})");
                $failCount++;
                $bar->advance();
                continue;
            }

            $data['latitude'] = $coords['lat'];
            $data['longitude'] = $coords['lng'];

            if (!$dryRun) {
                BikeParking::updateOrCreate(
                    ['name' => $data['name'], 'address' => $data['address']],
                    $data
                );
            }

            $successCount++;
            usleep(1000000); // GSIは公共API: 1件ごとに1秒待機
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("完了！ (成功: {$successCount}件 / 失敗: {$failCount}件)");
    }

    private function getParkingData(): array
    {
        return [
            // 東京
            ['name' => '東京駅八重洲バイク駐車場', 'address' => '東京都中央区八重洲1丁目', 'prefecture' => '東京都', 'city' => '中央区', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'price_per_day' => 800, 'is_covered' => true, 'is_locked' => true, 'has_security_camera' => true, 'available_24h' => true, 'is_active' => true],
            ['name' => '新宿サブナード バイク駐車場', 'address' => '東京都新宿区歌舞伎町1丁目', 'prefecture' => '東京都', 'city' => '新宿区', 'parking_type' => 'bike_only', 'price_per_hour' => 200, 'price_per_day' => 1000, 'is_covered' => true, 'has_security_camera' => true, 'available_24h' => true, 'is_active' => true],
            ['name' => '渋谷マークシティ バイク駐輪場', 'address' => '東京都渋谷区道玄坂1丁目', 'prefecture' => '東京都', 'city' => '渋谷区', 'parking_type' => 'bicycle_shared', 'price_per_hour' => 100, 'price_per_day' => 500, 'is_covered' => true, 'available_24h' => true, 'is_active' => true],
            ['name' => '池袋駅東口バイク駐車場', 'address' => '東京都豊島区南池袋1丁目', 'prefecture' => '東京都', 'city' => '豊島区', 'parking_type' => 'bike_only', 'price_per_hour' => 150, 'price_per_day' => 800, 'is_covered' => true, 'has_security_camera' => true, 'is_active' => true],
            ['name' => '上野駅前バイク駐車場', 'address' => '東京都台東区上野7丁目', 'prefecture' => '東京都', 'city' => '台東区', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'price_per_day' => 600, 'is_covered' => true, 'available_24h' => true, 'is_active' => true],
            ['name' => '秋葉原UDX バイク駐車場', 'address' => '東京都千代田区外神田4丁目', 'prefecture' => '東京都', 'city' => '千代田区', 'parking_type' => 'car_shared', 'price_per_hour' => 200, 'price_per_day' => 1200, 'is_covered' => true, 'has_security_camera' => true, 'available_24h' => true, 'is_active' => true],
            ['name' => '品川駅港南口バイク駐輪場', 'address' => '東京都港区港南2丁目', 'prefecture' => '東京都', 'city' => '港区', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'price_per_day' => 500, 'has_security_camera' => true, 'available_24h' => true, 'is_active' => true],
            ['name' => '六本木ヒルズ バイク駐車場', 'address' => '東京都港区六本木6丁目', 'prefecture' => '東京都', 'city' => '港区', 'parking_type' => 'car_shared', 'price_per_hour' => 300, 'price_per_day' => 1500, 'is_covered' => true, 'has_security_camera' => true, 'available_24h' => true, 'is_active' => true],
            ['name' => '浅草雷門バイク駐輪場', 'address' => '東京都台東区浅草1丁目', 'prefecture' => '東京都', 'city' => '台東区', 'parking_type' => 'bicycle_shared', 'price_per_hour' => 100, 'price_per_day' => 400, 'is_active' => true],
            ['name' => 'お台場海浜公園バイク駐車場', 'address' => '東京都港区台場1丁目', 'prefecture' => '東京都', 'city' => '港区', 'parking_type' => 'bike_only', 'is_free' => true, 'available_24h' => true, 'is_active' => true],
            ['name' => '立川駅北口バイク駐車場', 'address' => '東京都立川市曙町2丁目', 'prefecture' => '東京都', 'city' => '立川市', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'price_per_day' => 500, 'is_covered' => true, 'is_active' => true],
            ['name' => '吉祥寺駅南口バイク駐輪場', 'address' => '東京都武蔵野市吉祥寺南町1丁目', 'prefecture' => '東京都', 'city' => '武蔵野市', 'parking_type' => 'bicycle_shared', 'price_per_hour' => 100, 'is_active' => true],

            // 神奈川
            ['name' => '横浜駅西口バイク駐車場', 'address' => '神奈川県横浜市西区南幸1丁目', 'prefecture' => '神奈川県', 'city' => '横浜市', 'parking_type' => 'bike_only', 'price_per_hour' => 150, 'price_per_day' => 800, 'is_covered' => true, 'has_security_camera' => true, 'available_24h' => true, 'is_active' => true],
            ['name' => 'みなとみらいバイク駐車場', 'address' => '神奈川県横浜市西区みなとみらい2丁目', 'prefecture' => '神奈川県', 'city' => '横浜市', 'parking_type' => 'car_shared', 'price_per_hour' => 200, 'price_per_day' => 1000, 'is_covered' => true, 'has_security_camera' => true, 'is_active' => true],
            ['name' => '川崎駅東口バイク駐輪場', 'address' => '神奈川県川崎市川崎区駅前本町', 'prefecture' => '神奈川県', 'city' => '川崎市', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'price_per_day' => 600, 'is_active' => true],

            // 大阪
            ['name' => '梅田バイク駐車場', 'address' => '大阪府大阪市北区梅田1丁目', 'prefecture' => '大阪府', 'city' => '大阪市', 'parking_type' => 'bike_only', 'price_per_hour' => 200, 'price_per_day' => 1000, 'is_covered' => true, 'has_security_camera' => true, 'available_24h' => true, 'is_active' => true],
            ['name' => '難波バイク駐車場', 'address' => '大阪府大阪市中央区難波5丁目', 'prefecture' => '大阪府', 'city' => '大阪市', 'parking_type' => 'bike_only', 'price_per_hour' => 150, 'price_per_day' => 800, 'is_covered' => true, 'available_24h' => true, 'is_active' => true],
            ['name' => '天王寺駅前バイク駐輪場', 'address' => '大阪府大阪市天王寺区悲田院町', 'prefecture' => '大阪府', 'city' => '大阪市', 'parking_type' => 'bicycle_shared', 'price_per_hour' => 100, 'price_per_day' => 500, 'is_active' => true],
            ['name' => '心斎橋バイク駐車場', 'address' => '大阪府大阪市中央区心斎橋筋2丁目', 'prefecture' => '大阪府', 'city' => '大阪市', 'parking_type' => 'bike_only', 'price_per_hour' => 200, 'is_covered' => true, 'has_security_camera' => true, 'is_active' => true],
            ['name' => '新大阪駅バイク駐車場', 'address' => '大阪府大阪市淀川区西中島5丁目', 'prefecture' => '大阪府', 'city' => '大阪市', 'parking_type' => 'car_shared', 'price_per_hour' => 150, 'price_per_day' => 800, 'is_covered' => true, 'available_24h' => true, 'is_active' => true],
            ['name' => '大阪城公園バイク駐車場', 'address' => '大阪府大阪市中央区大阪城', 'prefecture' => '大阪府', 'city' => '大阪市', 'parking_type' => 'bike_only', 'is_free' => true, 'is_active' => true],

            // 名古屋
            ['name' => '名古屋駅太閤通口バイク駐車場', 'address' => '愛知県名古屋市中村区名駅1丁目', 'prefecture' => '愛知県', 'city' => '名古屋市', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'price_per_day' => 600, 'is_covered' => true, 'has_security_camera' => true, 'available_24h' => true, 'is_active' => true],
            ['name' => '栄バイク駐車場', 'address' => '愛知県名古屋市中区栄3丁目', 'prefecture' => '愛知県', 'city' => '名古屋市', 'parking_type' => 'bike_only', 'price_per_hour' => 150, 'price_per_day' => 800, 'is_covered' => true, 'is_active' => true],
            ['name' => '金山駅バイク駐輪場', 'address' => '愛知県名古屋市中区金山1丁目', 'prefecture' => '愛知県', 'city' => '名古屋市', 'parking_type' => 'bicycle_shared', 'price_per_hour' => 100, 'is_active' => true],
            ['name' => '名古屋城周辺バイク駐車場', 'address' => '愛知県名古屋市中区本丸1番1号', 'prefecture' => '愛知県', 'city' => '名古屋市', 'parking_type' => 'car_shared', 'price_per_day' => 500, 'is_active' => true],

            // 福岡
            ['name' => '博多駅前バイク駐車場', 'address' => '福岡県福岡市博多区博多駅前2丁目', 'prefecture' => '福岡県', 'city' => '福岡市', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'price_per_day' => 500, 'is_covered' => true, 'has_security_camera' => true, 'available_24h' => true, 'is_active' => true],
            ['name' => '天神バイク駐車場', 'address' => '福岡県福岡市中央区天神2丁目', 'prefecture' => '福岡県', 'city' => '福岡市', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'price_per_day' => 600, 'is_covered' => true, 'is_active' => true],
            ['name' => '中洲バイク駐輪場', 'address' => '福岡県福岡市博多区中洲3丁目', 'prefecture' => '福岡県', 'city' => '福岡市', 'parking_type' => 'bicycle_shared', 'price_per_hour' => 100, 'available_24h' => true, 'is_active' => true],
            ['name' => 'キャナルシティ バイク駐車場', 'address' => '福岡県福岡市博多区住吉1丁目', 'prefecture' => '福岡県', 'city' => '福岡市', 'parking_type' => 'car_shared', 'price_per_hour' => 150, 'price_per_day' => 800, 'is_covered' => true, 'has_security_camera' => true, 'is_active' => true],

            // 札幌
            ['name' => '札幌駅北口バイク駐車場', 'address' => '北海道札幌市北区北7条西4丁目', 'prefecture' => '北海道', 'city' => '札幌市', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'price_per_day' => 500, 'is_covered' => true, 'available_24h' => true, 'is_active' => true],
            ['name' => 'すすきのバイク駐輪場', 'address' => '北海道札幌市中央区南4条西3丁目', 'prefecture' => '北海道', 'city' => '札幌市', 'parking_type' => 'bicycle_shared', 'price_per_hour' => 100, 'is_active' => true],

            // 仙台
            ['name' => '仙台駅西口バイク駐車場', 'address' => '宮城県仙台市青葉区中央1丁目', 'prefecture' => '宮城県', 'city' => '仙台市', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'price_per_day' => 500, 'is_covered' => true, 'available_24h' => true, 'is_active' => true],

            // 京都
            ['name' => '京都駅八条口バイク駐車場', 'address' => '京都府京都市南区東九条西山王町', 'prefecture' => '京都府', 'city' => '京都市', 'parking_type' => 'bike_only', 'price_per_hour' => 150, 'price_per_day' => 800, 'is_covered' => true, 'has_security_camera' => true, 'is_active' => true],
            ['name' => '四条河原町バイク駐輪場', 'address' => '京都府京都市中京区河原町通四条', 'prefecture' => '京都府', 'city' => '京都市', 'parking_type' => 'bicycle_shared', 'price_per_hour' => 100, 'is_active' => true],

            // 神戸
            ['name' => '三宮駅前バイク駐車場', 'address' => '兵庫県神戸市中央区三宮町1丁目', 'prefecture' => '兵庫県', 'city' => '神戸市', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'price_per_day' => 500, 'is_covered' => true, 'is_active' => true],
            ['name' => '神戸ハーバーランド バイク駐車場', 'address' => '兵庫県神戸市中央区東川崎町1丁目', 'prefecture' => '兵庫県', 'city' => '神戸市', 'parking_type' => 'car_shared', 'price_per_hour' => 150, 'is_covered' => true, 'has_security_camera' => true, 'is_active' => true],

            // 広島
            ['name' => '広島駅南口バイク駐車場', 'address' => '広島県広島市南区松原町', 'prefecture' => '広島県', 'city' => '広島市', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'price_per_day' => 500, 'is_covered' => true, 'is_active' => true],

            // さいたま
            ['name' => '大宮駅西口バイク駐車場', 'address' => '埼玉県さいたま市大宮区桜木町1丁目', 'prefecture' => '埼玉県', 'city' => 'さいたま市', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'price_per_day' => 500, 'is_covered' => true, 'available_24h' => true, 'is_active' => true],

            // 千葉
            ['name' => '千葉駅前バイク駐輪場', 'address' => '千葉県千葉市中央区新千葉1丁目', 'prefecture' => '千葉県', 'city' => '千葉市', 'parking_type' => 'bicycle_shared', 'price_per_hour' => 100, 'is_active' => true],
            ['name' => '船橋駅南口バイク駐車場', 'address' => '千葉県船橋市本町1丁目', 'prefecture' => '千葉県', 'city' => '船橋市', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'price_per_day' => 500, 'is_active' => true],

            // 静岡
            ['name' => '静岡駅北口バイク駐車場', 'address' => '静岡県静岡市葵区黒金町', 'prefecture' => '静岡県', 'city' => '静岡市', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'is_covered' => true, 'is_active' => true],
            ['name' => '浜松駅前バイク駐車場', 'address' => '静岡県浜松市中央区砂山町', 'prefecture' => '静岡県', 'city' => '浜松市', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'price_per_day' => 500, 'is_active' => true],

            // 沖縄
            ['name' => '那覇国際通りバイク駐車場', 'address' => '沖縄県那覇市牧志3丁目', 'prefecture' => '沖縄県', 'city' => '那覇市', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'is_active' => true],

            // 新潟
            ['name' => '新潟駅万代口バイク駐輪場', 'address' => '新潟県新潟市中央区花園1丁目', 'prefecture' => '新潟県', 'city' => '新潟市', 'parking_type' => 'bicycle_shared', 'price_per_hour' => 100, 'is_active' => true],

            // 熊本
            ['name' => '熊本駅前バイク駐車場', 'address' => '熊本県熊本市西区春日3丁目', 'prefecture' => '熊本県', 'city' => '熊本市', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'price_per_day' => 500, 'is_active' => true],

            // 長崎
            ['name' => '長崎駅前バイク駐輪場', 'address' => '長崎県長崎市尾上町', 'prefecture' => '長崎県', 'city' => '長崎市', 'parking_type' => 'bicycle_shared', 'is_free' => true, 'is_active' => true],

            // 鹿児島
            ['name' => '鹿児島中央駅バイク駐車場', 'address' => '鹿児島県鹿児島市中央町', 'prefecture' => '鹿児島県', 'city' => '鹿児島市', 'parking_type' => 'bike_only', 'price_per_hour' => 100, 'is_active' => true],
        ];
    }
}
