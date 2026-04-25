<?php
/**
 * 本番用: 不正city一括調査＋修正スクリプト v4
 *
 * 検出パターン:
 *   A. 重複サフィックス（区市、町市、市市等）
 *   B. 重複文字（川川、高高等）
 *   C. 都道府県フル混入（神奈川県横浜市→横浜市）
 *   D. 都道府県部分混入（神奈川横浜市→横浜市、県なし）
 *   E. 住所混入（丁目、番地、地区）
 *   F. 長すぎ（10文字超）
 *   G. 短すぎ（2文字以下、市/区で終わらない）
 *   H. 政令指定都市の区欠落（横浜市→横浜市中区）
 *   I. 区のみ・市なし（多摩区→川崎市多摩区）※東京都除外
 *   J. 町のみ・郡なし（湯河原町→足柄下郡湯河原町）
 *   K. スペース/数字混入
 *   L. 都道府県不一致（横浜市なのに東京都等）
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

// ── 定数 ──────────────────────────────────────────

$prefectures = [
    '北海道','青森県','岩手県','宮城県','秋田県','山形県','福島県',
    '茨城県','栃木県','群馬県','埼玉県','千葉県','東京都','神奈川県',
    '新潟県','富山県','石川県','福井県','山梨県','長野県','岐阜県',
    '静岡県','愛知県','三重県','滋賀県','京都府','大阪府','兵庫県',
    '奈良県','和歌山県','鳥取県','島根県','岡山県','広島県','山口県',
    '徳島県','香川県','愛媛県','高知県','福岡県','佐賀県','長崎県',
    '熊本県','大分県','宮崎県','鹿児島県','沖縄県',
];
$prefRegexp = '(' . implode('|', $prefectures) . ')';

// 都道府県ベース（サフィックスなし）— city先頭に残存する部分プレフィックス検出用
// 3文字: 安全に検出可能。2文字: 市名と重複するため個別条件付き
$partialPrefBases = [
    '神奈川' => '神奈川県',
    '鹿児島' => '鹿児島県',
    '和歌山' => '和歌山県',
    '北海' => '北海道',
];

$designatedCities = [
    'さいたま市','横浜市','川崎市','相模原市','名古屋市','京都市',
    '大阪市','神戸市','福岡市','北九州市','札幌市','仙台市',
    '千葉市','新潟市','静岡市','浜松市','岡山市','広島市','熊本市','堺市',
];
$designatedCityList = "'" . implode("','", $designatedCities) . "'";

$cityToPref = [
    '札幌市' => '北海道', '仙台市' => '宮城県', 'さいたま市' => '埼玉県',
    '千葉市' => '千葉県', '横浜市' => '神奈川県', '川崎市' => '神奈川県',
    '相模原市' => '神奈川県', '新潟市' => '新潟県', '静岡市' => '静岡県',
    '浜松市' => '静岡県', '名古屋市' => '愛知県', '京都市' => '京都府',
    '大阪市' => '大阪府', '堺市' => '大阪府', '神戸市' => '兵庫県',
    '岡山市' => '岡山県', '広島市' => '広島県', '北九州市' => '福岡県',
    '福岡市' => '福岡県', '熊本市' => '熊本県',
];

// ── バリデーション関数 ─────────────────────────────

function isValidCity(string $city, array $prefectures): bool
{
    if ($city === '' || mb_strlen($city) < 2) return false;
    if (mb_strlen($city) > 15) return false;
    if (preg_match('/[\d０-９]|丁目|番地|号|先/u', $city)) return false;
    if (preg_match('/[（）()]/u', $city)) return false;
    if (!preg_match('/[市区町村郡]$/u', $city)) return false;
    // 重複サフィックス
    if (preg_match('/(区市|町市)$/u', $city)) return false;
    if (preg_match('/市市$/u', $city) && !in_array($city, ['四日市市', '廿日市市'])) return false;
    // 都道府県名が先頭に混入
    foreach ($prefectures as $p) {
        if (str_starts_with($city, $p) && mb_strlen($city) > mb_strlen($p)) return false;
    }
    return true;
}

// ── DB更新ヘルパー ─────────────────────────────────

function applyFix(object $row, string $newPref, string $newCity, string $method, bool $fix, int &$fixCount): void
{
    echo "  FIX [{$method}] ID:{$row->id} [{$row->prefecture}|{$row->city}] → [{$newPref}|{$newCity}]\n";
    if ($fix) {
        $updates = [];
        if ($newCity !== $row->city) $updates['city'] = $newCity;
        if ($newPref !== $row->prefecture) $updates['prefecture'] = $newPref;
        if ($updates) {
            DB::table('bike_parkings')->where('id', $row->id)->update($updates);
            $fixCount++;
        }
    }
}

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
// Part 2: 全パターン検出＋カスケード修正
// ============================================================
echo "\n========================================\n";
echo "Part 2: 全パターン検出＋修正\n";
echo "========================================\n\n";

$badRows = DB::select("
    SELECT id, address, prefecture, city
    FROM bike_parkings
    WHERE city IS NOT NULL AND city <> ''
      AND address IS NOT NULL AND address <> ''
      AND (
        -- A. 重複サフィックス
        city REGEXP '(区市|町市)$'
        OR (city REGEXP '市市' AND city NOT IN ('四日市市','廿日市市'))

        -- B. 重複文字（隣接する同一漢字）
        OR city REGEXP '(川川|高高|田田|島島|山山|崎崎|橋橋|浜浜|宮宮|井井|木木|野野|原原|谷谷|口口|西西|東東|南南|北北)'

        -- C. 都道府県フル混入
        OR city REGEXP '^{$prefRegexp}.+'

        -- D. 都道府県部分混入（県/都/府/道なしの先頭残存）
        OR (city LIKE '神奈川%' AND city NOT LIKE '神奈川県%')
        OR (city LIKE '鹿児島%' AND city NOT LIKE '鹿児島市%' AND city NOT LIKE '鹿児島県%')
        OR (city LIKE '和歌山%' AND city NOT LIKE '和歌山市%' AND city NOT LIKE '和歌山県%')

        -- E. 住所混入
        OR city REGEXP '(丁目|番地|地区)'

        -- F. 長すぎ
        OR CHAR_LENGTH(city) > 10

        -- G. 短すぎ（2文字以下で市/区で終わらない）
        OR (CHAR_LENGTH(city) <= 2 AND city NOT REGEXP '[市区]$')

        -- H. (政令指定都市の区欠落 → Part 2Aで専用処理)

        -- I. 区のみ・市なし（東京都の特別区は除外）
        OR (city REGEXP '区$' AND city NOT REGEXP '市' AND prefecture <> '東京都')

        -- J. 町のみ・郡なし（3～5文字の町名）
        OR (city REGEXP '町$' AND city NOT REGEXP '郡' AND CHAR_LENGTH(city) >= 3 AND CHAR_LENGTH(city) <= 5)

        -- K. スペース混入
        OR city LIKE '% %' OR city LIKE '%　%'

        -- L. 数字混入
        OR city REGEXP '[0-9０-９]'
      )
    ORDER BY city, id
");

echo "検出: " . count($badRows) . "件\n\n";

// パターン別集計
$groups = [];
foreach ($badRows as $r) { $groups[$r->city] = ($groups[$r->city] ?? 0) + 1; }
arsort($groups);
echo "--- 不正city別集計 (上位40) ---\n";
$i = 0;
foreach ($groups as $city => $cnt) {
    echo "  [{$city}] x{$cnt}\n";
    if (++$i >= 40) break;
}
echo "\n";

// 手動マッピング（パーサーでも対応できないパターン）
$manualMap = [
    '新宿区市' => ['city' => '新宿区'],
    '市川川市' => ['city' => '市川市'],
    '川崎市高高津区' => ['city' => '川崎市高津区'],
];

$stats = ['fixed' => 0, 'skipped' => 0, 'unresolved' => 0];
$unresolvedList = [];

foreach ($badRows as $row) {
    $newPref = $row->prefecture;
    $newCity = $row->city;
    $method = '';

    // ── カスケード修正 ──

    // 1. 手動マッピング
    if (isset($manualMap[$row->city])) {
        $m = $manualMap[$row->city];
        $newCity = $m['city'] ?? $newCity;
        $newPref = $m['prefecture'] ?? $newPref;
        $method = 'manual';
    }

    // 2. 重複文字除去（川川→川、高高→高等）
    if ($method === '' && preg_match('/([\p{Han}])\1/u', $row->city)) {
        $deduped = preg_replace('/([\p{Han}])\1/u', '$1', $row->city);
        if ($deduped !== $row->city && isValidCity($deduped, $prefectures)) {
            $newCity = $deduped;
            $method = 'dedup';
        }
    }

    // 3. AddressParser再パース（最も信頼性が高い）
    if ($method === '') {
        $parsed = $parser->parse($row->address);
        if ($parsed['city'] !== '' && $parsed['city'] !== $row->city
            && isValidCity($parsed['city'], $prefectures)) {
            $newCity = $parsed['city'];
            $newPref = $parsed['prefecture'] ?: $newPref;
            $method = 'reparse';
        }
    }

    // 4. 都道府県フルプレフィックス除去
    if ($method === '') {
        foreach ($prefectures as $p) {
            if (str_starts_with($row->city, $p) && mb_strlen($row->city) > mb_strlen($p)) {
                $stripped = mb_substr($row->city, mb_strlen($p));
                if (isValidCity($stripped, $prefectures)) {
                    $newPref = $p;
                    $newCity = $stripped;
                    $method = 'strip-pref-full';
                }
                break;
            }
        }
    }

    // 5. 都道府県部分プレフィックス除去（県/都/府/道なし: 神奈川→神奈川県）
    if ($method === '') {
        foreach ($partialPrefBases as $base => $fullPref) {
            if (str_starts_with($row->city, $base) && mb_strlen($row->city) > mb_strlen($base)) {
                $stripped = mb_substr($row->city, mb_strlen($base));
                if (isValidCity($stripped, $prefectures)) {
                    $newPref = $fullPref;
                    $newCity = $stripped;
                    $method = 'strip-pref-partial';
                }
                break;
            }
        }
    }

    // 6. 住所切り詰め（cityが長すぎて住所が混入したケース）
    if ($method === '' && mb_strlen($row->city) > 5) {
        // 政令指定都市+区パターン
        if (preg_match('/^(.+?市.+?区)/u', $row->city, $m)
            && isValidCity($m[1], $prefectures)) {
            $newCity = $m[1];
            $method = 'truncate';
        }
        // 一般市パターン
        elseif (preg_match('/^(.+?市)/u', $row->city, $m)
            && mb_strlen($m[1]) >= 2 && isValidCity($m[1], $prefectures)) {
            $newCity = $m[1];
            $method = 'truncate';
        }
        // 区パターン
        elseif (preg_match('/^(.+?区)/u', $row->city, $m)
            && mb_strlen($m[1]) >= 2 && isValidCity($m[1], $prefectures)) {
            $newCity = $m[1];
            $method = 'truncate';
        }
    }

    // 7. (政令指定都市の区補完 → Part 2Aで専用処理)

    // 8. 区のみ → 市+区に補完（addressから市を取得）
    if ($method === '' && preg_match('/区$/u', $row->city) && !str_contains($row->city, '市')) {
        $parsed = $parser->parse($row->address);
        if ($parsed['city'] !== '' && str_contains($parsed['city'], '市')) {
            $newCity = $parsed['city'];
            $newPref = $parsed['prefecture'] ?: $newPref;
            $method = 'add-shi-to-ku';
        }
    }

    // 9. 町のみ → 郡+町に補完（addressから郡を取得）
    if ($method === '' && preg_match('/町$/u', $row->city) && !str_contains($row->city, '郡')
        && mb_strlen($row->city) >= 3 && mb_strlen($row->city) <= 5) {
        $parsed = $parser->parse($row->address);
        if ($parsed['city'] !== '' && str_contains($parsed['city'], '郡')) {
            $newCity = $parsed['city'];
            $newPref = $parsed['prefecture'] ?: $newPref;
            $method = 'add-gun-to-machi';
        } else {
            // 郡補完できない → standalone町として許容
            $stats['skipped']++;
            continue;
        }
    }

    // 10. 区のみ + 都道府県から政令市を推定（大阪府+中央区 → 大阪市中央区等）
    if ($method === '' && preg_match('/区$/u', $newCity) && !str_contains($newCity, '市')
        && $newPref !== '' && $newPref !== '東京都') {
        $prefDesignated = [];
        foreach ($cityToPref as $c => $p) {
            if ($p === $newPref) $prefDesignated[] = $c;
        }
        if (count($prefDesignated) === 1) {
            // 都道府県内に政令指定都市が1つだけ → その市と確定
            $newCity = $prefDesignated[0] . $newCity;
            $method = 'infer-shi-from-pref';
        } elseif (count($prefDesignated) > 1) {
            // 複数の政令指定都市 → DB内の既存データで検証
            foreach ($prefDesignated as $dc) {
                $candidate = $dc . $newCity;
                $exists = DB::selectOne(
                    "SELECT 1 FROM bike_parkings WHERE city = ? AND prefecture = ? LIMIT 1",
                    [$candidate, $newPref]
                );
                if ($exists) {
                    $newCity = $candidate;
                    $method = 'infer-shi-from-pref-db';
                    break;
                }
            }
        }
    }

    // 11. 空prefの東京特別区推定
    //     品川区等の一意な区名 + pref空 → 東京都と推定
    if ($method === '' && $newPref === '' && preg_match('/区$/u', $newCity)) {
        // 他の政令指定都市と重複しない東京23区のみ（北区/中央区/港区等は除外）
        $uniqueTokyo23ku = [
            '千代田区','新宿区','文京区','台東区','墨田区','江東区',
            '品川区','目黒区','大田区','世田谷区','渋谷区','中野区',
            '杉並区','豊島区','荒川区','板橋区','練馬区','足立区',
            '葛飾区','江戸川区',
        ];
        if (in_array($newCity, $uniqueTokyo23ku)) {
            $newPref = '東京都';
            $method = 'infer-tokyo';
        }
    }

    // ── 都道府県の矯正（CITY_TO_PREF） ──
    foreach ($cityToPref as $cityName => $correctPref) {
        if ($newCity !== '' && str_starts_with($newCity, $cityName)) {
            $newPref = $correctPref;
            break;
        }
    }

    // ── 結果判定 ──
    if ($newPref === $row->prefecture && $newCity === $row->city) {
        $unresolvedList[] = $row;
        $stats['unresolved']++;
        continue;
    }

    applyFix($row, $newPref, $newCity, $method, $fix, $stats['fixed']);
}

// 未解決リスト
if (!empty($unresolvedList)) {
    echo "\n--- 未解決 ({$stats['unresolved']}件) ---\n";
    foreach ($unresolvedList as $u) {
        echo "  ID:{$u->id} [{$u->prefecture}|{$u->city}] addr={$u->address}\n";
    }
}

$fixable = count($badRows) - $stats['skipped'] - $stats['unresolved'];
echo "\nPart 2 結果: 検出=" . count($badRows) . ", 修正可能={$fixable}, スキップ={$stats['skipped']}, 未解決={$stats['unresolved']}\n";
$totalFixed += ($fix ? $stats['fixed'] : 0);

// ============================================================
// Part 2A: 政令指定都市の区補完（専用パス）
// ============================================================
echo "\n========================================\n";
echo "Part 2A: 政令指定都市の区補完\n";
echo "========================================\n\n";

$dcRows = DB::select("
    SELECT id, address, prefecture, city
    FROM bike_parkings
    WHERE city IN ({$designatedCityList})
      AND address IS NOT NULL AND address <> ''
    ORDER BY city, id
");

echo "検出: " . count($dcRows) . "件\n\n";

// 集計
$dcGroups = [];
foreach ($dcRows as $r) { $dcGroups[$r->city] = ($dcGroups[$r->city] ?? 0) + 1; }
arsort($dcGroups);
foreach ($dcGroups as $city => $cnt) { echo "  [{$city}] x{$cnt}\n"; }
echo "\n";

$dcFixed = 0;
$dcSkipped = 0;

foreach ($dcRows as $row) {
    $newCity = null;
    $newPref = $row->prefecture;
    $method = '';

    // 前処理（AddressParserと同じ）
    $addr = trim($row->address);
    if (class_exists(\Normalizer::class)) {
        $addr = \Normalizer::normalize($addr, \Normalizer::FORM_KC);
    }
    $addr = preg_replace('/[（(][^）)]*[）)]/u', '', $addr);

    // Try 1: AddressParser再パース
    $parsed = $parser->parse($row->address);
    if ($parsed['city'] !== '' && mb_strlen($parsed['city']) > mb_strlen($row->city)
        && str_starts_with($parsed['city'], $row->city)) {
        $newCity = $parsed['city'];
        $newPref = $parsed['prefecture'] ?: $newPref;
        $method = 'parser';
    }

    // Try 2: addressから直接「○○市○○区」を正規表現で抽出
    if ($newCity === null) {
        $cityPattern = preg_quote($row->city, '/');
        // 市名の直後に1～5文字（漢字/ひらがな）+区
        if (preg_match('/' . $cityPattern . '\s*([\p{Han}\p{Hiragana}]{1,5}区)/u', $addr, $m)) {
            $candidate = $row->city . $m[1];
            if (isValidCity($candidate, $prefectures)) {
                $newCity = $candidate;
                $method = 'regex';
            }
        }
    }

    // Try 3: 都道府県を除去してから再トライ
    if ($newCity === null) {
        $addrNoPref = $addr;
        foreach ($prefectures as $p) {
            if (str_starts_with($addr, $p)) {
                $addrNoPref = preg_replace('/^[\s　]+/u', '', mb_substr($addr, mb_strlen($p)));
                break;
            }
        }
        $cityPattern = preg_quote($row->city, '/');
        if (preg_match('/' . $cityPattern . '\s*([\p{Han}\p{Hiragana}]{1,5}区)/u', $addrNoPref, $m)) {
            $candidate = $row->city . $m[1];
            if (isValidCity($candidate, $prefectures)) {
                $newCity = $candidate;
                $method = 'regex-nopref';
            }
        }
    }

    if ($newCity === null) {
        echo "  SKIP ID:{$row->id} [{$row->prefecture}|{$row->city}] addr={$row->address}\n";
        $dcSkipped++;
        continue;
    }

    // CITY_TO_PREF矯正
    foreach ($cityToPref as $cityName => $correctPref) {
        if (str_starts_with($newCity, $cityName)) {
            $newPref = $correctPref;
            break;
        }
    }

    echo "  FIX [{$method}] ID:{$row->id} [{$row->prefecture}|{$row->city}] → [{$newPref}|{$newCity}]\n";
    if ($fix) {
        $updates = ['city' => $newCity];
        if ($newPref !== $row->prefecture) $updates['prefecture'] = $newPref;
        DB::table('bike_parkings')->where('id', $row->id)->update($updates);
        $dcFixed++;
    }
}

echo "\nPart 2A 結果: 修正可能=" . (count($dcRows) - $dcSkipped) . ", スキップ(addressに区なし)={$dcSkipped}\n";
$totalFixed += ($fix ? $dcFixed : 0);

// ============================================================
// Part 2B: 町→郡町 統一（エリアページの重複防止）
// ============================================================
echo "\n========================================\n";
echo "Part 2B: 町→郡町 統一\n";
echo "========================================\n\n";

// 既知の町→郡町マッピング
$machiToGunMachi = [
    '湯河原町' => '足柄下郡湯河原町',
    '葉山町' => '三浦郡葉山町',
];

// DB内の bare町/村 を自動検出して郡+町/村 form が存在すれば追加
$bareMachiCities = DB::select("
    SELECT DISTINCT city
    FROM bike_parkings
    WHERE city REGEXP '[町村]$' AND city NOT REGEXP '郡'
      AND CHAR_LENGTH(city) >= 3 AND CHAR_LENGTH(city) <= 5
");

foreach ($bareMachiCities as $bm) {
    if (isset($machiToGunMachi[$bm->city])) continue;

    // 同じ町/村名を含む郡+町/村 form がDB内に存在するか
    $gunForm = DB::selectOne(
        "SELECT DISTINCT city FROM bike_parkings WHERE city LIKE ? AND city REGEXP '郡' LIMIT 1",
        ['%' . $bm->city]
    );
    if ($gunForm) {
        $machiToGunMachi[$bm->city] = $gunForm->city;
    }
}

$bmFixed = 0;
$bmTotal = 0;
foreach ($machiToGunMachi as $bare => $canonical) {
    $cnt = DB::selectOne("SELECT COUNT(*) as c FROM bike_parkings WHERE city = ?", [$bare])->c;
    if ($cnt === 0) continue;
    $bmTotal += $cnt;

    // 正規form が実際に存在するか確認
    $canonicalExists = DB::selectOne("SELECT COUNT(*) as c FROM bike_parkings WHERE city = ?", [$canonical])->c;

    echo "  {$bare} → {$canonical} ({$cnt}件";
    if ($canonicalExists > 0) {
        echo ", 正規form既存={$canonicalExists}件";
    }
    echo ")\n";

    if ($fix) {
        DB::table('bike_parkings')->where('city', $bare)->update(['city' => $canonical]);
        $bmFixed += $cnt;
    }
}

if ($bmTotal === 0) {
    echo "統一対象なし\n";
}
echo "\nPart 2B 結果: 統一={$bmTotal}件\n";
$totalFixed += ($fix ? $bmFixed : 0);

// ============================================================
// Part 3: 都道府県不一致の修正
// ============================================================
echo "\n========================================\n";
echo "Part 3: 都道府県不一致チェック\n";
echo "========================================\n\n";

$prefMismatchFixed = 0;
$prefMismatchConditions = [];
foreach ($cityToPref as $cityName => $correctPref) {
    $prefMismatchConditions[] = "(city LIKE '{$cityName}%' AND prefecture <> '{$correctPref}')";
}
$prefMismatchWhere = implode("\n        OR ", $prefMismatchConditions);

$mismatchRows = DB::select("
    SELECT id, address, prefecture, city
    FROM bike_parkings
    WHERE {$prefMismatchWhere}
    ORDER BY city, id
");

if (empty($mismatchRows)) {
    echo "都道府県不一致なし\n";
} else {
    echo "検出: " . count($mismatchRows) . "件\n\n";
    foreach ($mismatchRows as $row) {
        $correctPref = null;
        foreach ($cityToPref as $cityName => $pref) {
            if (str_starts_with($row->city, $cityName)) {
                $correctPref = $pref;
                break;
            }
        }
        if ($correctPref && $correctPref !== $row->prefecture) {
            echo "  FIX ID:{$row->id} [{$row->prefecture}|{$row->city}] → pref={$correctPref}\n";
            if ($fix) {
                DB::table('bike_parkings')->where('id', $row->id)->update(['prefecture' => $correctPref]);
                $prefMismatchFixed++;
            }
        }
    }
    echo "\nPart 3 結果: 修正=" . ($fix ? $prefMismatchFixed : count($mismatchRows)) . "件\n";
    $totalFixed += ($fix ? $prefMismatchFixed : 0);
}

// ============================================================
// Part 4: 全国スキャン（残存不正city）
// ============================================================
echo "\n========================================\n";
echo "Part 4: 全国スキャン（残存不正city）\n";
echo "========================================\n\n";

$remaining = DB::select("
    SELECT city, prefecture, COUNT(*) as cnt
    FROM bike_parkings
    WHERE city IS NOT NULL AND city <> ''
      AND (
        -- 重複サフィックス/文字
        city REGEXP '(区市|町市)$'
        OR (city REGEXP '市市' AND city NOT IN ('四日市市','廿日市市'))
        OR city REGEXP '(川川|高高|田田|島島|山山|崎崎)'

        -- 都道府県混入
        OR city REGEXP '^{$prefRegexp}.+'
        OR (city LIKE '神奈川%' AND city NOT LIKE '神奈川県%')

        -- 住所混入
        OR city REGEXP '(丁目|番地|地区)'

        -- 長さ異常
        OR CHAR_LENGTH(city) > 10
        OR (CHAR_LENGTH(city) <= 2 AND city NOT REGEXP '[市区]$')

        -- スペース/数字
        OR city LIKE '% %' OR city LIKE '%　%'
        OR city REGEXP '[0-9０-９]'
      )
    GROUP BY city, prefecture
    ORDER BY cnt DESC
    LIMIT 30
");

if (empty($remaining)) {
    echo "重大な不正city候補なし（クリーン）\n";
} else {
    echo "残存する不正city候補:\n";
    foreach ($remaining as $s) {
        echo "  [{$s->prefecture}|{$s->city}] x{$s->cnt}\n";
    }
}

// ============================================================
// Part 5: 駅データ修正
// ============================================================
echo "\n========================================\n";
echo "Part 5: 駅データ\n";
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
