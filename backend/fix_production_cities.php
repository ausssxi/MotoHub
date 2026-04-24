<?php
/**
 * 本番用: 不正city調査＋修正スクリプト
 *
 * 使い方:
 *   # 調査のみ（デフォルト）
 *   docker compose exec app php /var/www/fix_production_cities.php
 *
 *   # 修正を実行
 *   docker compose exec app php /var/www/fix_production_cities.php --fix
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$fix = in_array('--fix', $argv ?? []);
$parser = new \App\Services\Parking\AddressParser();

echo $fix ? "=== 修正モード ===\n\n" : "=== 調査モード（--fix で修正実行）===\n\n";

// 不正cityリスト
$badCities = ['本町', '川崎市', '神奈川県町', '武蔵村', '羽村', '岩手県一関市'];

$totalFixed = 0;

foreach ($badCities as $badCity) {
    $rows = \App\Models\BikeParking::where('city', $badCity)->get();

    if ($rows->isEmpty()) {
        echo "--- {$badCity}: 該当なし ---\n\n";
        continue;
    }

    echo "--- {$badCity}: {$rows->count()}件 ---\n";

    foreach ($rows as $row) {
        // AddressParserで再パース
        $parsed = $parser->parse($row->address);
        $newPref = $parsed['prefecture'] ?: $row->prefecture;
        $newCity = $parsed['city'];

        // パーサーが空cityの場合、addressから手動推定
        if ($newCity === '') {
            $newCity = inferCityManual($row->address, $row->prefecture);
        }

        $prefChanged = ($newPref !== $row->prefecture) ? " pref:{$row->prefecture}→{$newPref}" : '';
        $cityChanged = ($newCity !== $row->city) ? " city:{$row->city}→{$newCity}" : '';

        echo "  ID:{$row->id} [{$row->prefecture}|{$row->city}] → [{$newPref}|{$newCity}]{$prefChanged}{$cityChanged}\n";
        echo "    address: {$row->address}\n";

        if ($fix && ($newPref !== $row->prefecture || $newCity !== $row->city)) {
            $updates = [];
            if ($newCity !== '' && $newCity !== $row->city) {
                $updates['city'] = $newCity;
            }
            if ($newPref !== '' && $newPref !== $row->prefecture) {
                $updates['prefecture'] = $newPref;
            }
            if ($updates) {
                $row->update($updates);
                echo "    → 修正済み\n";
                $totalFixed++;
            }
        }
    }
    echo "\n";
}

// 追加チェック: 他にも不正cityがないか全国スキャン
echo "=== 全国スキャン: 不正city候補 ===\n";
$suspects = \Illuminate\Support\Facades\DB::select("
    SELECT city, prefecture, COUNT(*) as cnt
    FROM bike_parkings
    WHERE city != ''
      AND city IS NOT NULL
      AND (
        CHAR_LENGTH(city) <= 2 AND city NOT REGEXP '[市区]$'
        OR city REGEXP '^[^市区町村郡]*$'
        OR city LIKE '%県%'
        OR city LIKE '% %'
      )
    GROUP BY city, prefecture
    ORDER BY cnt DESC
    LIMIT 20
");
if (empty($suspects)) {
    echo "  不正city候補なし（クリーン）\n";
} else {
    foreach ($suspects as $s) {
        echo "  [{$s->prefecture}|{$s->city}] x{$s->cnt}\n";
    }
}

echo "\n";
if ($fix) {
    echo "修正完了: {$totalFixed}件\n";
} else {
    echo "調査完了。修正するには --fix オプションを付けて再実行してください。\n";
}

/**
 * AddressParserで空cityの場合の手動推定
 */
function inferCityManual(string $address, string $currentPref): string
{
    // 都道府県を除去
    $prefectures = [
        '北海道', '青森県', '岩手県', '宮城県', '秋田県', '山形県', '福島県',
        '茨城県', '栃木県', '群馬県', '埼玉県', '千葉県', '東京都', '神奈川県',
        '新潟県', '富山県', '石川県', '福井県', '山梨県', '長野県', '岐阜県',
        '静岡県', '愛知県', '三重県', '滋賀県', '京都府', '大阪府', '兵庫県',
        '奈良県', '和歌山県', '鳥取県', '島根県', '岡山県', '広島県', '山口県',
        '徳島県', '香川県', '愛媛県', '高知県', '福岡県', '佐賀県', '長崎県',
        '熊本県', '大分県', '宮崎県', '鹿児島県', '沖縄県',
    ];
    $rest = $address;
    foreach ($prefectures as $p) {
        if (str_starts_with($address, $p)) {
            $rest = mb_substr($address, mb_strlen($p));
            break;
        }
    }
    $rest = trim($rest);

    // 既知の問題パターンを手動マッチ
    if (preg_match('/^(武蔵村山市)/u', $rest)) return '武蔵村山市';
    if (preg_match('/^(羽村市)/u', $rest)) return '羽村市';
    if (preg_match('/^(町田市)/u', $rest)) return '町田市';
    if (preg_match('/^(東村山市)/u', $rest)) return '東村山市';

    return '';
}
