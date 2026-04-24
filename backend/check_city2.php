<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

// 全レコードを取得
$rows = DB::select("SELECT id, prefecture, city, address FROM bike_parkings WHERE CHAR_LENGTH(city) > 0 AND CHAR_LENGTH(address) > 0");

$problems = [];
$summary = ['correct' => 0, 'prefix_only' => 0, 'truncated' => 0, 'mismatch' => 0, 'empty_pref' => 0];

// 日本の全都道府県リスト
$prefs = ['北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県','茨城県','栃木県','群馬県',
    '埼玉県','千葉県','東京都','神奈川県','新潟県','富山県','石川県','福井県','山梨県','長野県',
    '岐阜県','静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県','奈良県','和歌山県',
    '鳥取県','島根県','岡山県','広島県','山口県','徳島県','香川県','愛媛県','高知県','福岡県',
    '佐賀県','長崎県','熊本県','大分県','宮崎県','鹿児島県','沖縄県'];

// addressから正しいprefectureとcityを抽出
function parseAddress(string $address, array $prefs): array {
    $pref = '';
    $city = '';
    $rest = $address;

    // 都道府県を抽出
    foreach ($prefs as $p) {
        if (str_starts_with($address, $p)) {
            $pref = $p;
            $rest = mb_substr($address, mb_strlen($p));
            break;
        }
    }

    // 市区町村を抽出 (政令指定都市の区にも対応)
    // 優先順位: ○○市○○区 > ○○市 > ○○区 > ○○郡○○町 > ○○郡○○村 > ○○町 > ○○村
    if (preg_match('/^(.+?市.+?区)/u', $rest, $m)) {
        $city = $m[1];
    } elseif (preg_match('/^(.+?市)/u', $rest, $m)) {
        $city = $m[1];
    } elseif (preg_match('/^(.+?区)/u', $rest, $m)) {
        $city = $m[1];
    } elseif (preg_match('/^(.+?郡.+?[町村])/u', $rest, $m)) {
        $city = $m[1];
    } elseif (preg_match('/^(.+?町)/u', $rest, $m)) {
        $city = $m[1];
    } elseif (preg_match('/^(.+?村)/u', $rest, $m)) {
        $city = $m[1];
    }

    return ['prefecture' => $pref, 'city' => $city];
}

foreach ($rows as $r) {
    $parsed = parseAddress($r->address, $prefs);
    $expectedCity = $parsed['city'];

    // 現在のcityからprefecture部分を除去
    $currentCity = $r->city;
    if (str_starts_with($currentCity, $r->prefecture)) {
        $currentCity = mb_substr($currentCity, mb_strlen($r->prefecture));
    }

    if ($currentCity === $expectedCity) {
        $summary['correct']++;
    } elseif (str_starts_with($r->city, $r->prefecture) && $currentCity !== $expectedCity) {
        // prefixあり + 中身が違う → truncated or wrong
        $summary['truncated']++;
        $problems[] = [
            'id' => $r->id,
            'pref' => $r->prefecture,
            'current_city' => $r->city,
            'expected_city' => $expectedCity,
            'address' => mb_substr($r->address, 0, 50),
            'type' => 'truncated',
        ];
    } else {
        $summary['mismatch']++;
        $problems[] = [
            'id' => $r->id,
            'pref' => $r->prefecture,
            'current_city' => $r->city,
            'expected_city' => $expectedCity,
            'address' => mb_substr($r->address, 0, 50),
            'type' => 'mismatch',
        ];
    }
}

echo "=== サマリー ===" . PHP_EOL;
echo "  正常 (city一致): {$summary['correct']}" . PHP_EOL;
echo "  切れてる/不正 (prefix付き+不一致): {$summary['truncated']}" . PHP_EOL;
echo "  不一致 (prefix無し): {$summary['mismatch']}" . PHP_EOL;

echo PHP_EOL . "=== 切れてる/不正のcity (prefix付き) ===" . PHP_EOL;
$truncated = array_filter($problems, fn($p) => $p['type'] === 'truncated');
$grouped = [];
foreach ($truncated as $p) {
    $key = $p['current_city'] . ' → ' . $p['expected_city'];
    $grouped[$key] = ($grouped[$key] ?? 0) + 1;
}
arsort($grouped);
foreach ($grouped as $key => $cnt) {
    echo "  {$key} ({$cnt}件)" . PHP_EOL;
}

echo PHP_EOL . "=== 不一致のcity (prefix無し) ===" . PHP_EOL;
$mismatched = array_filter($problems, fn($p) => $p['type'] === 'mismatch');
$grouped2 = [];
foreach ($mismatched as $p) {
    $key = "[{$p['pref']}] {$p['current_city']} → {$p['expected_city']}";
    $grouped2[$key] = ($grouped2[$key] ?? 0) + 1;
}
arsort($grouped2);
foreach (array_slice($grouped2, 0, 30) as $key => $cnt) {
    echo "  {$key} ({$cnt}件)" . PHP_EOL;
}
