<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;

$all = DB::select("SELECT DISTINCT city, prefecture FROM bike_parkings WHERE CHAR_LENGTH(city) > 0 ORDER BY city");

$bad = [];
foreach ($all as $r) {
    $last = mb_substr($r->city, -1);
    if (!in_array($last, ['市', '区', '町', '村', '郡'])) {
        $bad[] = $r;
    }
}

echo "=== 市区町村郡で終わらないcity (" . count($bad) . "種) ===" . PHP_EOL;
foreach ($bad as $r) {
    echo "  pref=[{$r->prefecture}] city=[{$r->city}]" . PHP_EOL;
}

// prefecture-in-city but city is truncated
echo PHP_EOL . "=== 都道府県含みで切れてるcity ===" . PHP_EOL;
foreach ($all as $r) {
    if (str_starts_with($r->city, $r->prefecture) && mb_strlen($r->city) > mb_strlen($r->prefecture)) {
        $cityPart = mb_substr($r->city, mb_strlen($r->prefecture));
        $last = mb_substr($cityPart, -1);
        if (!in_array($last, ['市', '区', '町', '村', '郡'])) {
            echo "  pref=[{$r->prefecture}] city=[{$r->city}] extracted=[{$cityPart}]" . PHP_EOL;
        }
    }
}

// Count: correct vs needs-fix
$correctCity = 0;
$prefInCity = 0;
$truncCity = 0;
$otherBad = 0;
foreach ($all as $r) {
    if ($r->city === '' || $r->city === null) continue;
    if (str_starts_with($r->city, $r->prefecture)) {
        $cityPart = mb_substr($r->city, mb_strlen($r->prefecture));
        $lastChar = mb_substr($cityPart, -1);
        if (in_array($lastChar, ['市', '区', '町', '村', '郡'])) {
            $prefInCity++;
        } else {
            $truncCity++;
        }
    } else {
        $lastChar = mb_substr($r->city, -1);
        if (in_array($lastChar, ['市', '区', '町', '村', '郡'])) {
            $correctCity++;
        } else {
            $otherBad++;
        }
    }
}
echo PHP_EOL . "=== サマリー ===" . PHP_EOL;
echo "  正常 (cityのみ): {$correctCity}" . PHP_EOL;
echo "  都道府県+city (修正可能): {$prefInCity}" . PHP_EOL;
echo "  切れてる (要addressから再抽出): {$truncCity}" . PHP_EOL;
echo "  その他不正: {$otherBad}" . PHP_EOL;
