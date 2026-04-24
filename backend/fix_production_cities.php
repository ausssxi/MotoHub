<?php
/**
 * 本番用: 不正city一括調査＋修正スクリプト
 *
 * 使い方:
 *   docker compose exec app php /var/www/fix_production_cities.php          # 調査のみ
 *   docker compose exec app php /var/www/fix_production_cities.php --fix    # 修正実行
 */

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\BikeParking;
use App\Models\Station;
use App\Services\Parking\AddressParser;
use Illuminate\Support\Facades\DB;

$fix = in_array('--fix', $argv ?? []);
$parser = new AddressParser();
$totalFixed = 0;

echo $fix ? "=== 修正モード ===\n\n" : "=== 調査モード（--fix で修正実行）===\n\n";

// 全47都道府県のREGEXP用パターン
$prefRegexp = '(北海道|青森県|岩手県|宮城県|秋田県|山形県|福島県|茨城県|栃木県|群馬県|埼玉県|千葉県|東京都|神奈川県|新潟県|富山県|石川県|福井県|山梨県|長野県|岐阜県|静岡県|愛知県|三重県|滋賀県|京都府|大阪府|兵庫県|奈良県|和歌山県|鳥取県|島根県|岡山県|広島県|山口県|徳島県|香川県|愛媛県|高知県|福岡県|佐賀県|長崎県|熊本県|大分県|宮崎県|鹿児島県|沖縄県)';

// ============================================================
// Part 1: 個別の既知問題を修正
// ============================================================
echo "========================================\n";
echo "Part 1: 個別の既知問題\n";
echo "========================================\n\n";

$manualFixes = [
    ['ID:14710 武蔵村→武蔵村山市（address誤字）',
        fn () => BikeParking::where('id', 14710)->first(),
        fn () => ['city' => '武蔵村山市']],
    ['ID:15218 岩手県一関市→pref=岩手県,city=一関市',
        fn () => BikeParking::where('id', 15218)->first(),
        fn () => ['prefecture' => '岩手県', 'city' => '一関市']],
];

foreach ($manualFixes as [$desc, $finder, $updater]) {
    $row = $finder();
    if (!$row) { echo "  SKIP: {$desc} → なし\n"; continue; }
    $updates = $updater();
    $changed = false;
    foreach ($updates as $k => $v) { if ($row->$k !== $v) { $changed = true; break; } }
    if (!$changed) { echo "  OK:   {$desc}\n"; continue; }
    echo "  FIX:  {$desc}\n";
    if ($fix) { $row->update($updates); $totalFixed++; echo "        → 修正済み\n"; }
}

// ============================================================
// Part 2: パターンベースの一括検出＋修正
// ============================================================
echo "\n========================================\n";
echo "Part 2: パターンベース一括修正\n";
echo "========================================\n\n";

$badRows = DB::select("
    SELECT id, address, prefecture, city
    FROM bike_parkings
    WHERE city IS NOT NULL AND city != ''
      AND address IS NOT NULL AND address != ''
      AND (
        -- 1. 重複サフィックス（末尾が「区市」「町市」「村市」「川川」「区区」）
        city REGEXP '(区市|町市|川川|区区)$'
        -- 村市は末尾のみ（羽村市/東村山市/武蔵村山市を除外）
        OR (city REGEXP '村市$' AND city NOT REGEXP '^(羽村市|東村山市|武蔵村山市|中村市|田村市|志村市)$')
        -- 市市は四日市市/廿日市市を除外
        OR (city REGEXP '市市' AND city NOT IN ('四日市市','廿日市市'))
        -- 2. 住所混入（丁目,番地,地区 等の語）
        OR city REGEXP '(丁目|番地|地区|南部.*地区)'
        -- 3. 長すぎ（10文字超）
        OR CHAR_LENGTH(city) > 10
        -- 4. 都道府県名が先頭に混入（47都道府県名で始まり、その後に文字が続く）
        OR city REGEXP '^{$prefRegexp}.+'
        -- 5. 2文字以下で市/区で終わらない（東村,蒲郡,中郡 等の不完全名）
        OR (CHAR_LENGTH(city) <= 2 AND city NOT REGEXP '[市区]$')
        -- 6. 政令指定都市で区が欠落（○○市だけで○○区がない）
        OR city IN ('さいたま市','横浜市','川崎市','相模原市','名古屋市','京都市','大阪市','神戸市','福岡市','北九州市','札幌市','仙台市','千葉市','新潟市','静岡市','浜松市','岡山市','広島市','熊本市','堺市')
      )
    ORDER BY city, id
");

echo "検出: " . count($badRows) . "件\n\n";

// パターン別集計
$groups = [];
foreach ($badRows as $r) { $groups[$r->city] = ($groups[$r->city] ?? 0) + 1; }
arsort($groups);
echo "--- 不正city別集計 ---\n";
foreach ($groups as $city => $cnt) { echo "  [{$city}] x{$cnt}\n"; }
echo "\n";

// 手動マッピング（パーサーで処理できないパターン）
$manualMap = [
    '新宿区市' => ['city' => '新宿区'],
    '市川川市' => ['city' => '市川市'],
];

$fixed2 = 0; $skipped2 = 0; $unresolved2 = 0;

foreach ($badRows as $row) {
    $newPref = $row->prefecture;
    $newCity = $row->city;
    $method = '';

    // 手動マッピング
    if (isset($manualMap[$row->city])) {
        $m = $manualMap[$row->city];
        $newCity = $m['city'] ?? $newCity;
        $newPref = $m['prefecture'] ?? $newPref;
        $method = 'manual';
    }

    // AddressParserで再パース
    if ($method === '') {
        $parsed = $parser->parse($row->address);
        if ($parsed['city'] !== '' && $parsed['city'] !== $row->city) {
            $newCity = $parsed['city'];
            $newPref = $parsed['prefecture'] ?: $newPref;
            $method = 'reparse';
        }
    }

    // 都道府県prefix除去（パーサーでも解決しない場合）
    if ($method === '') {
        $prefectures = [
            '北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県',
            '茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県',
            '新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県',
            '静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県',
            '奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県',
            '徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県',
            '熊本県','大分県','宮崎県','鹿児島県','沖縄県',
        ];
        foreach ($prefectures as $p) {
            if (str_starts_with($row->city, $p) && mb_strlen($row->city) > mb_strlen($p)) {
                $stripped = mb_substr($row->city, mb_strlen($p));
                if (preg_match('/[市区町村]$/u', $stripped) && mb_strlen($stripped) >= 2) {
                    $newPref = $p;
                    $newCity = $stripped;
                    $method = 'strip-pref';
                }
                break;
            }
        }
    }

    // 住所混入: cityから市/区で切る
    if ($method === '' && mb_strlen($row->city) > 5) {
        if (preg_match('/^(.+?[市区])/u', $row->city, $m) && mb_strlen($m[1]) >= 2) {
            $newCity = $m[1];
            $method = 'truncate';
        }
    }

    // 政令指定都市の区補完: パーサーでより詳細な結果が得られるか
    if ($method === '' && preg_match('/^(さいたま市|横浜市|川崎市|相模原市|名古屋市|京都市|大阪市|神戸市|福岡市|北九州市|札幌市|仙台市|千葉市|新潟市|静岡市|浜松市|岡山市|広島市|熊本市|堺市)$/u', $row->city)) {
        $parsed = $parser->parse($row->address);
        if ($parsed['city'] !== '' && mb_strlen($parsed['city']) > mb_strlen($row->city)) {
            $newCity = $parsed['city'];
            $newPref = $parsed['prefecture'] ?: $newPref;
            $method = 'add-ku';
        } else {
            $skipped2++;
            continue;
        }
    }

    // 結果判定
    if ($newPref === $row->prefecture && $newCity === $row->city) {
        echo "  UNRESOLVED ID:{$row->id} [{$row->prefecture}|{$row->city}] addr={$row->address}\n";
        $unresolved2++;
        continue;
    }

    echo "  FIX [{$method}] ID:{$row->id} [{$row->prefecture}|{$row->city}] → [{$newPref}|{$newCity}]\n";

    if ($fix) {
        $updates = [];
        if ($newCity !== $row->city) { $updates['city'] = $newCity; }
        if ($newPref !== $row->prefecture) { $updates['prefecture'] = $newPref; }
        if ($updates) {
            DB::table('bike_parkings')->where('id', $row->id)->update($updates);
            $fixed2++;
        }
    }
}

$fixable = count($badRows) - $skipped2 - $unresolved2;
echo "\nPart 2 結果: 修正可能={$fixable}, スキップ(区なし政令市)={$skipped2}, 未解決={$unresolved2}\n";
$totalFixed += ($fix ? $fixed2 : 0);

// ============================================================
// Part 3: 全国スキャン（残存チェック）
// ============================================================
echo "\n========================================\n";
echo "Part 3: 全国スキャン（残存不正city）\n";
echo "========================================\n\n";

$remaining = DB::select("
    SELECT city, prefecture, COUNT(*) as cnt
    FROM bike_parkings
    WHERE city IS NOT NULL AND city != ''
      AND (
        city REGEXP '(区市|町市|川川|区区)$'
        OR (city REGEXP '村市$' AND city NOT REGEXP '^(羽村市|東村山市|武蔵村山市|中村市|田村市|志村市)$')
        OR (city REGEXP '市市' AND city NOT IN ('四日市市','廿日市市'))
        OR city REGEXP '(丁目|番地|地区)'
        OR CHAR_LENGTH(city) > 10
        OR city REGEXP '^{$prefRegexp}.+'
        OR (CHAR_LENGTH(city) <= 2 AND city NOT REGEXP '[市区]$')
        OR city LIKE '% %'
        OR city REGEXP '[0-9０-９]'
      )
    GROUP BY city, prefecture
    ORDER BY cnt DESC
    LIMIT 30
");

if (empty($remaining)) {
    echo "不正city候補なし（クリーン）\n";
} else {
    echo "残存する不正city候補:\n";
    foreach ($remaining as $s) {
        echo "  [{$s->prefecture}|{$s->city}] x{$s->cnt}\n";
    }
}

// ============================================================
// Part 4: 駅データ修正
// ============================================================
echo "\n========================================\n";
echo "Part 4: 駅データ\n";
echo "========================================\n\n";

$stationFixes = [
    ['町田', '東京都', '町田市'],
    ['南町田グランベリーパーク', '東京都', '町田市'],
];

foreach ($stationFixes as [$name, $correctPref, $correctCity]) {
    $station = Station::where('name', $name)->first();
    if (!$station) { echo "  SKIP: {$name}駅 → なし\n"; continue; }
    if ($station->prefecture === $correctPref && $station->city === $correctCity) {
        echo "  OK:   {$name}駅 [{$station->prefecture}|{$station->city}]\n";
        continue;
    }
    echo "  FIX:  {$name}駅 [{$station->prefecture}|{$station->city}] → [{$correctPref}|{$correctCity}]\n";
    if ($fix) {
        $station->update(['prefecture' => $correctPref, 'city' => $correctCity]);
        $totalFixed++;
        echo "        → 修正済み\n";
    }
}

// ============================================================
echo "\n========================================\n";
if ($fix) {
    echo "修正完了: 合計 {$totalFixed}件\n";
} else {
    echo "調査完了。修正するには --fix を付けて再実行してください。\n";
}
echo "========================================\n";
