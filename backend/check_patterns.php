<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;

// 1. Bare 区 (non-Tokyo)
$bareKu = DB::select("
    SELECT city, prefecture, COUNT(*) as cnt
    FROM bike_parkings
    WHERE city REGEXP '区$' AND city NOT REGEXP '市' AND prefecture <> '東京都'
    GROUP BY city, prefecture ORDER BY cnt DESC LIMIT 10
");
echo "--- bare 区 (non-Tokyo) ---\n";
foreach ($bareKu as $r) echo "  [{$r->prefecture}|{$r->city}] x{$r->cnt}\n";

// 2. Bare 町 (no 郡)
$bareMachi = DB::select("
    SELECT city, prefecture, COUNT(*) as cnt
    FROM bike_parkings
    WHERE city REGEXP '町$' AND city NOT REGEXP '郡' AND CHAR_LENGTH(city) >= 3
    GROUP BY city, prefecture ORDER BY cnt DESC LIMIT 15
");
echo "\n--- bare 町 (no 郡) ---\n";
foreach ($bareMachi as $r) echo "  [{$r->prefecture}|{$r->city}] x{$r->cnt}\n";

// 3. Bare 村 (no 郡)
$bareMura = DB::select("
    SELECT city, prefecture, COUNT(*) as cnt
    FROM bike_parkings
    WHERE city REGEXP '村$' AND city NOT REGEXP '郡' AND CHAR_LENGTH(city) >= 3
    GROUP BY city, prefecture ORDER BY cnt DESC LIMIT 10
");
echo "\n--- bare 村 (no 郡) ---\n";
foreach ($bareMura as $r) echo "  [{$r->prefecture}|{$r->city}] x{$r->cnt}\n";

// 4. Prefecture mismatch for designated cities
$cityToPref = [
    '横浜市' => '神奈川県', '川崎市' => '神奈川県', 'さいたま市' => '埼玉県',
    '千葉市' => '千葉県', '名古屋市' => '愛知県', '大阪市' => '大阪府',
    '京都市' => '京都府', '神戸市' => '兵庫県', '福岡市' => '福岡県',
    '札幌市' => '北海道', '仙台市' => '宮城県', '広島市' => '広島県',
    '相模原市' => '神奈川県', '堺市' => '大阪府', '北九州市' => '福岡県',
    '熊本市' => '熊本県', '新潟市' => '新潟県', '静岡市' => '静岡県',
    '浜松市' => '静岡県', '岡山市' => '岡山県',
];
echo "\n--- Prefecture mismatches ---\n";
$total = 0;
foreach ($cityToPref as $city => $pref) {
    $rows = DB::select("SELECT id, prefecture, city FROM bike_parkings WHERE city LIKE ? AND prefecture <> ? LIMIT 5", [$city.'%', $pref]);
    foreach ($rows as $r) {
        echo "  ID:{$r->id} [{$r->prefecture}|{$r->city}] should be {$pref}\n";
        $total++;
    }
}
echo "Total: {$total}\n";

// 5. Total counts for each category
echo "\n--- Category counts ---\n";
$counts = [
    'bare_ku' => DB::selectOne("SELECT COUNT(*) as c FROM bike_parkings WHERE city REGEXP '区$' AND city NOT REGEXP '市' AND prefecture <> '東京都'")->c,
    'bare_machi' => DB::selectOne("SELECT COUNT(*) as c FROM bike_parkings WHERE city REGEXP '町$' AND city NOT REGEXP '郡' AND CHAR_LENGTH(city) >= 3")->c,
    'bare_mura' => DB::selectOne("SELECT COUNT(*) as c FROM bike_parkings WHERE city REGEXP '村$' AND city NOT REGEXP '郡' AND CHAR_LENGTH(city) >= 3")->c,
    'designated_no_ku' => DB::selectOne("SELECT COUNT(*) as c FROM bike_parkings WHERE city IN ('さいたま市','横浜市','川崎市','相模原市','名古屋市','京都市','大阪市','神戸市','福岡市','北九州市','札幌市','仙台市','千葉市','新潟市','静岡市','浜松市','岡山市','広島市','熊本市','堺市')")->c,
    'pref_in_city' => DB::selectOne("SELECT COUNT(*) as c FROM bike_parkings WHERE city REGEXP '^(北海道|青森県|岩手県|宮城県|秋田県|山形県|福島県|茨城県|栃木県|群馬県|埼玉県|千葉県|東京都|神奈川県|新潟県|富山県|石川県|福井県|山梨県|長野県|岐阜県|静岡県|愛知県|三重県|滋賀県|京都府|大阪府|兵庫県|奈良県|和歌山県|鳥取県|島根県|岡山県|広島県|山口県|徳島県|香川県|愛媛県|高知県|福岡県|佐賀県|長崎県|熊本県|大分県|宮崎県|鹿児島県|沖縄県).+'")->c,
    'too_short' => DB::selectOne("SELECT COUNT(*) as c FROM bike_parkings WHERE city IS NOT NULL AND city <> '' AND CHAR_LENGTH(city) <= 2 AND city NOT REGEXP '[市区]$'")->c,
];
foreach ($counts as $k => $v) echo "  {$k}: {$v}\n";
