<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BlogPost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

$apiKey = config('services.anthropic.api_key') ?: env('ANTHROPIC_API_KEY');

// ===== Phase 1: Collect Data =====
echo "=== Collecting big bike data ===\n";

// Overall stats by displacement class
$classes = [
    '~125cc' => [0, 125],
    '126-250cc' => [126, 250],
    '251-400cc' => [251, 400],
    '401-750cc' => [401, 750],
    '751-1000cc' => [751, 1000],
    '1001cc~' => [1001, 9999],
];

$classStats = [];
foreach ($classes as $label => [$min, $max]) {
    $s = DB::table('listings as l')
        ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
        ->where('l.is_sold_out', false)
        ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
        ->whereBetween('bm.displacement', [$min, $max])
        ->selectRaw('COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg_price')
        ->first();
    $sl = DB::table('listings as l')
        ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
        ->where('l.is_sold_out', true)
        ->where('l.updated_at', '>=', now()->subMonths(3))
        ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
        ->whereBetween('bm.displacement', [$min, $max])
        ->selectRaw('COUNT(*) as sold, ROUND(AVG(l.total_price)) as avg_price')
        ->first();
    $classStats[$label] = [
        'stock' => $s->stock,
        'avg' => $s->avg_price,
        'sold' => $sl->sold,
        'sold_avg' => $sl->avg_price,
    ];
    echo "{$label}: stock={$s->stock} avg=¥" . number_format($s->avg_price) . " sold={$sl->sold}\n";
}

// Category breakdown for 401cc+
$categories = DB::table('listings as l')
    ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
    ->where('l.is_sold_out', false)
    ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
    ->where('bm.displacement', '>', 400)
    ->whereNotNull('bm.category')
    ->selectRaw('bm.category, COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg')
    ->groupBy('bm.category')
    ->having('stock', '>=', 10)
    ->orderByDesc('stock')
    ->get();

// Popular big bike models
$bigModels = DB::table('listings as l')
    ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
    ->join('manufacturers as m', 'bm.manufacturer_id', '=', 'm.id')
    ->where('l.is_sold_out', false)
    ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
    ->where('bm.displacement', '>', 400)
    ->selectRaw('bm.id, CONCAT(m.name," ",bm.name) as name, bm.slug, bm.category, bm.displacement, COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg, ROUND(MIN(l.total_price)) as min_p, ROUND(MAX(l.total_price)) as max_p')
    ->groupByRaw('bm.id, bm.name, bm.slug, m.name, bm.category, bm.displacement')
    ->having('stock', '>=', 15)
    ->orderByDesc('stock')
    ->get();
echo "Big bike models (stock>=15): " . $bigModels->count() . "\n";

// Sales speed for 401cc+
$speed = DB::table('listings as l')
    ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
    ->join('manufacturers as m', 'bm.manufacturer_id', '=', 'm.id')
    ->where('l.is_sold_out', true)
    ->where('l.updated_at', '>=', now()->subMonths(3))
    ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
    ->where('bm.displacement', '>', 400)
    ->whereRaw('DATEDIFF(l.updated_at, l.created_at) BETWEEN 1 AND 180')
    ->selectRaw('bm.id, CONCAT(m.name," ",bm.name) as name, bm.slug, bm.category, bm.displacement, ROUND(AVG(DATEDIFF(l.updated_at, l.created_at))) as days, COUNT(*) as sold, ROUND(AVG(l.total_price)) as avg')
    ->groupByRaw('bm.id, bm.name, bm.slug, m.name, bm.category, bm.displacement')
    ->having('sold', '>=', 8)
    ->orderBy('days')
    ->get();

// Mileage-price relation for 401cc+
$mileageData = DB::table('listings as l')
    ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
    ->where('l.is_sold_out', false)
    ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
    ->where('bm.displacement', '>', 400)
    ->whereNotNull('l.mileage')->where('l.mileage', '>', 0)
    ->selectRaw("
        CASE
            WHEN l.mileage < 5000 THEN '~5000km'
            WHEN l.mileage < 10000 THEN '5000-1万km'
            WHEN l.mileage < 20000 THEN '1-2万km'
            WHEN l.mileage < 30000 THEN '2-3万km'
            WHEN l.mileage < 50000 THEN '3-5万km'
            ELSE '5万km~'
        END as mileage_range,
        COUNT(*) as cnt, ROUND(AVG(l.total_price)) as avg
    ")
    ->groupByRaw("CASE
            WHEN l.mileage < 5000 THEN '~5000km'
            WHEN l.mileage < 10000 THEN '5000-1万km'
            WHEN l.mileage < 20000 THEN '1-2万km'
            WHEN l.mileage < 30000 THEN '2-3万km'
            WHEN l.mileage < 50000 THEN '3-5万km'
            ELSE '5万km~'
        END")
    ->orderByRaw("MIN(l.mileage)")
    ->get();

// Manufacturer share for 401cc+
$mfrShare = DB::table('listings as l')
    ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
    ->join('manufacturers as m', 'bm.manufacturer_id', '=', 'm.id')
    ->where('l.is_sold_out', false)
    ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
    ->where('bm.displacement', '>', 400)
    ->selectRaw('m.name, COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg')
    ->groupBy('m.name')
    ->having('stock', '>=', 20)
    ->orderByDesc('stock')
    ->get();

// Price ranges for 401cc+
$priceRanges = DB::table('listings as l')
    ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
    ->where('l.is_sold_out', false)
    ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
    ->where('bm.displacement', '>', 400)
    ->selectRaw("
        CASE
            WHEN l.total_price < 300000 THEN '~30万'
            WHEN l.total_price < 500000 THEN '30-50万'
            WHEN l.total_price < 800000 THEN '50-80万'
            WHEN l.total_price < 1000000 THEN '80-100万'
            WHEN l.total_price < 1500000 THEN '100-150万'
            WHEN l.total_price < 2000000 THEN '150-200万'
            ELSE '200万~'
        END as price_range, COUNT(*) as cnt
    ")
    ->groupByRaw("CASE
            WHEN l.total_price < 300000 THEN '~30万'
            WHEN l.total_price < 500000 THEN '30-50万'
            WHEN l.total_price < 800000 THEN '50-80万'
            WHEN l.total_price < 1000000 THEN '80-100万'
            WHEN l.total_price < 1500000 THEN '100-150万'
            WHEN l.total_price < 2000000 THEN '150-200万'
            ELSE '200万~'
        END")
    ->orderByRaw("MIN(l.total_price)")
    ->get();

// ===== Phase 2: Build data string =====
$dataLines = [];
$dataLines[] = "■ 排気量帯別の市場統計（在庫・平均価格・売却データ）";
foreach ($classStats as $label => $s) {
    $dataLines[] = "- {$label}: 在庫{$s['stock']}台 平均¥" . number_format($s['avg'])
        . " / 3M売却{$s['sold']}台 売却平均¥" . number_format($s['sold_avg']);
}

$dataLines[] = "\n■ 大型バイク(401cc~) カテゴリ別";
$dataLines[] = "| カテゴリ | 在庫 | 平均価格 |";
$dataLines[] = "|---------|------|---------|";
foreach ($categories as $c) {
    $dataLines[] = "| {$c->category} | {$c->stock}台 | ¥" . number_format($c->avg) . " |";
}

$dataLines[] = "\n■ 大型バイク人気車種TOP20(401cc~)";
foreach ($bigModels->take(20) as $i => $m) {
    $n = $i + 1;
    $dataLines[] = "{$n}. {$m->name} [{$m->category}] {$m->displacement}cc: {$m->stock}台 avg¥"
        . number_format($m->avg) . " min¥" . number_format($m->min_p) . " slug={$m->slug}";
}

$dataLines[] = "\n■ 売却スピードTOP10(401cc~)";
foreach ($speed->take(10) as $i => $s) {
    $n = $i + 1;
    $dataLines[] = "{$n}. {$s->name} ({$s->displacement}cc): {$s->days}日 ({$s->sold}台 ¥" . number_format($s->avg) . ")";
}

$dataLines[] = "\n■ 走行距離別の平均価格(401cc~)";
foreach ($mileageData as $m) {
    $dataLines[] = "- {$m->mileage_range}: {$m->cnt}台 avg¥" . number_format($m->avg);
}

$dataLines[] = "\n■ メーカー別シェア(401cc~)";
foreach ($mfrShare->take(15) as $m) {
    $dataLines[] = "- {$m->name}: {$m->stock}台 avg¥" . number_format($m->avg);
}

$dataLines[] = "\n■ 価格帯分布(401cc~)";
foreach ($priceRanges as $r) {
    $dataLines[] = "- {$r->price_range}: {$r->cnt}台";
}

$dataLines[] = "\n■ 排気量帯別 年間維持費の目安（一般的な相場）";
$dataLines[] = "| 項目 | 250cc | 400cc | 650cc | 1000cc超 |";
$dataLines[] = "|------|-------|-------|-------|---------|";
$dataLines[] = "| 車検（2年ごと）| なし | 約5~8万 | 約5~8万 | 約6~10万 |";
$dataLines[] = "| 自賠責保険（2年）| 約9,000円 | 約9,000円 | 約9,000円 | 約9,000円 |";
$dataLines[] = "| 重量税（2年）| なし（新車時のみ） | 約3,800円 | 約3,800円 | 約3,800円 |";
$dataLines[] = "| 軽自動車税（年） | 3,600円 | 6,000円 | 6,000円 | 6,000円 |";
$dataLines[] = "| 任意保険（26歳以上/6等級）| 約3~5万 | 約3~6万 | 約4~7万 | 約5~10万 |";
$dataLines[] = "| オイル交換（年3回）| 約6,000円 | 約9,000円 | 約12,000円 | 約15,000円 |";
$dataLines[] = "| タイヤ交換（前後セット）| 約2~3万/2年 | 約3~5万/2年 | 約4~6万/1.5年 | 約5~8万/1年 |";
$dataLines[] = "| ガソリン代（年5,000km, ¥175/L）| 約29,000円(30km/L) | 約35,000円(25km/L) | 約39,000円(22km/L) | 約50,000円(17km/L) |";
$dataLines[] = "| 年間合計目安 | 約8~13万 | 約14~22万 | 約17~27万 | 約22~40万 |";

$dataLines[] = "\n■ 「思ったより安い」ポイント";
$dataLines[] = "- 車検は250ccと400ccの差は年間2.5~4万円程度（月2,000~3,300円）";
$dataLines[] = "- 自賠責保険は排気量に関係なく同額（251cc以上は同じ）";
$dataLines[] = "- 大型は中古でコスパが良い車種が多い（401-750ccで50万以下も多数）";
$dataLines[] = "- 重量税は年間1,900円程度の差";

$dataLines[] = "\n■ 「思ったより高い」ポイント";
$dataLines[] = "- タイヤ代: 大型ハイグリップは前後で5~8万円、交換サイクルも短い";
$dataLines[] = "- 任意保険: 若年層（21歳未満）は排気量で大差（年10万超えも）";
$dataLines[] = "- パーツ代: 輸入車はパーツが高い（ハーレー、BMW、ドゥカティ）";
$dataLines[] = "- ガソリン: リッターバイクは17km/L程度、ツーリングで差が出る";

$data = implode("\n", $dataLines);
echo "Data collected. Length: " . strlen($data) . " bytes\n";

// ===== Phase 3: Call API =====
echo "Calling Anthropic API for big bike maintenance cost article...\n";
$response = Http::withHeaders([
    'x-api-key' => $apiKey,
    'anthropic-version' => '2023-06-01',
    'content-type' => 'application/json',
])->timeout(180)->post('https://api.anthropic.com/v1/messages', [
    'model' => 'claude-sonnet-4-20250514',
    'max_tokens' => 8000,
    'messages' => [[
        'role' => 'user',
        'content' => <<<PROMPT
あなたはバイク市場に精通したデータジャーナリストです。以下の実データに基づき、Google Discover向けの長編ブログ記事を書いてください。

【テーマ】大型バイクの維持費は年間いくら？排気量別の実コストをMotoHubデータで徹底比較【2026年版】

【実データ】
{$data}

【参考: 同シリーズの250cc記事トーン】
「250ccクラスは、バイク市場で最も激戦区のカテゴリです。2026年現在、なんと289モデルがひしめく中から最適な1台を見つけるのは至難の業。そこで、実際の在庫35,545台と直近3ヶ月の売却4,415台のリアルデータを徹底分析。」

【執筆ルール】
1. JSON形式で返す: {{"title":"…","body":"…(Markdown)","meta_description":"…(120文字以内)"}}
   ※bodyフィールド内のダブルクォートは\"でエスケープ
2. bodyはMarkdown。H2/H3で構成
3. **5,000〜7,000文字**（15分読了の長編記事）
4. データの数字は正確に使用
5. 以下の5つの画像プレースホルダーを入れる:
   - <!-- CHART:eyecatch --> — アイキャッチ（記事冒頭）
   - <!-- CHART:annual_cost --> — 排気量帯別の年間維持費比較（棒グラフ）
   - <!-- CHART:price_class --> — 排気量帯別の中古平均価格比較
   - <!-- CHART:mileage_price --> — 走行距離別の平均価格（値落ちカーブ）
   - <!-- CHART:cost_breakdown --> — 維持費の内訳（400cc vs 1000cc）

6. 構成:
   - リード文: MotoHubの実売データで大型バイクの維持費を徹底分析
   - 排気量帯別の年間維持費一覧表（250cc/400cc/650cc/1000cc超）
   - 車検は本当に高い？250ccとの実質差額を月額換算
   - タイヤ・オイル等の消耗品コスト比較（排気量で差が大きい項目）
   - 保険料の実態（年齢別・排気量別のリアル）
   - 中古大型バイクの市場概況（人気車種TOP10と価格帯）
   - 走行距離と値落ちの関係（走るほど安くなるペース）
   - 「思ったより安い」大型バイクの真実
   - 「思ったより高い」見落としがちなコスト
   - 維持費を抑えるコツ5選
   - 排気量帯別おすすめモデル（コスパ重視）
   - まとめ + 排気量帯別 年間コスト比較テーブル

7. 内部リンク多数:
   - [250cc全車種比較](/blog/250cc-all-models-comparison-2026)
   - [400cc全車種比較](/blog/400cc-all-models-comparison-2026)
   - [125cc全車種比較](/blog/125cc-all-models-comparison-2026)
   - [初心者おすすめバイク20選](/blog/best-bikes-for-beginners-2026)
   - [2026年夏の相場予測](/blog/bike-market-forecast-summer-2026)
   - [大型バイクを探す](/bikes/search?displacement_min=401)
   - [中古バイクを探す](/bikes/search)
   - 車種ページ例: /bikes/catalog/{slug}

8. ポータルサイトの分析記事としての信頼感あるトーン
9. 表を3つ以上（年間維持費比較、車検コスト比較、排気量帯別おすすめモデル等）
10. 年は2026年で統一
11. 記事末尾にMotoHubの検索ページへの内部リンクCTAを設置
PROMPT
    ]],
]);

if (!$response->successful()) {
    echo "API Error: " . $response->body() . "\n";
    exit(1);
}

$content = $response->json('content.0.text');
echo "API response received. Length: " . strlen($content) . "\n";

// Parse JSON
$cleaned = preg_replace('/^```json\s*/m', '', $content);
$cleaned = preg_replace('/```\s*$/m', '', $cleaned);
$cleaned = str_replace(["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}"], ['"', '"', "'", "'"], $cleaned);

if (preg_match('/\{[\s\S]*\}/u', $cleaned, $m)) {
    $article = json_decode($m[0], true);
    if (!$article && preg_match('/"body"\s*:\s*"([\s\S]*)",\s*"meta_description"/u', $m[0], $bm)) {
        preg_match('/"title"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $m[0], $tm);
        preg_match('/"meta_description"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $m[0], $dm);
        $article = ['title' => $tm[1] ?? '', 'body' => stripcslashes($bm[1]), 'meta_description' => $dm[1] ?? ''];
    }
}

if (!$article || !isset($article['body'])) {
    echo "Failed to parse JSON\n";
    file_put_contents(__DIR__ . '/debug-bigbike.txt', $content);
    exit(1);
}

$title = str_replace('2024年', '2026年', $article['title']);
$body = str_replace('2024年', '2026年', $article['body']);
$metaDesc = $article['meta_description'];

echo "Title: {$title}\nBody: " . mb_strlen($body) . " chars\n";

// ===== Phase 4: Generate Charts =====
function saveChart($json, $filename) {
    $url = 'https://quickchart.io/chart?c=' . urlencode($json) . '&w=1200&h=630&bkg=%231e293b&f=png';
    $ctx = stream_context_create(['http' => ['timeout' => 30]]);
    $img = @file_get_contents($url, false, $ctx);
    if ($img && strlen($img) > 1000) {
        Storage::disk('public')->put("blog/{$filename}", $img);
        echo "OK {$filename}\n";
        return true;
    }
    echo "FAIL {$filename}\n";
    return false;
}

// Extract chart data from collected stats
$classLabels = array_keys($classStats);
$classAvgs = array_map(fn($s) => round($s['avg'] / 10000, 1), array_values($classStats));

// 1. Eyecatch - Annual cost comparison
$c = json_encode([
    'type' => 'bar',
    'data' => [
        'labels' => ['250cc', '400cc', '650cc', '1000cc~'],
        'datasets' => [[
            'data' => [10, 18, 22, 31],
            'backgroundColor' => ['#22c55e', '#3b82f6', '#f59e0b', '#ef4444'],
            'borderRadius' => 8,
        ]],
    ],
    'options' => [
        'plugins' => [
            'title' => [
                'display' => true,
                'text' => ['Annual Maintenance Cost by Displacement', 'Real Data from MotoHub 2026'],
                'color' => '#fff',
                'font' => ['size' => 26, 'weight' => 'bold'],
            ],
            'legend' => ['display' => false],
            'datalabels' => [
                'display' => true, 'color' => '#fff',
                'anchor' => 'end', 'align' => 'top',
                'font' => ['size' => 18, 'weight' => 'bold'],
                'formatter' => '__F1__',
            ],
        ],
        'scales' => [
            'y' => [
                'ticks' => ['color' => '#94a3b8'],
                'grid' => ['color' => '#334155'],
                'title' => ['display' => true, 'text' => 'man-yen / year', 'color' => '#94a3b8'],
            ],
            'x' => ['ticks' => ['color' => '#fff', 'font' => ['size' => 16]], 'grid' => ['display' => false]],
        ],
    ],
]);
$c = str_replace('"__F1__"', "(v)=>v+'万円/年'", $c);
saveChart($c, 'bigbike-eyecatch.png');

// 2. Annual cost breakdown
$c = json_encode([
    'type' => 'bar',
    'data' => [
        'labels' => ['250cc', '400cc', '650cc', '1000cc~'],
        'datasets' => [
            ['label' => 'Tax/Insurance', 'data' => [4.0, 5.5, 6.0, 7.5], 'backgroundColor' => '#3b82f6', 'borderRadius' => 4],
            ['label' => 'Maintenance', 'data' => [2.5, 5.0, 6.5, 9.0], 'backgroundColor' => '#f59e0b', 'borderRadius' => 4],
            ['label' => 'Consumables', 'data' => [2.0, 4.0, 5.5, 8.0], 'backgroundColor' => '#ef4444', 'borderRadius' => 4],
            ['label' => 'Gas', 'data' => [2.9, 3.5, 3.9, 5.0], 'backgroundColor' => '#22c55e', 'borderRadius' => 4],
        ],
    ],
    'options' => [
        'plugins' => [
            'title' => ['display' => true, 'text' => 'Annual Cost Breakdown by Displacement', 'color' => '#fff', 'font' => ['size' => 22]],
            'legend' => ['labels' => ['color' => '#e2e8f0']],
            'datalabels' => ['display' => false],
        ],
        'scales' => [
            'y' => ['stacked' => true, 'ticks' => ['color' => '#94a3b8'], 'grid' => ['color' => '#334155'], 'title' => ['display' => true, 'text' => 'man-yen', 'color' => '#94a3b8']],
            'x' => ['stacked' => true, 'ticks' => ['color' => '#fff', 'font' => ['size' => 14]], 'grid' => ['display' => false]],
        ],
    ],
]);
saveChart($c, 'bigbike-annual-cost.png');

// 3. Price by displacement class
$c = json_encode([
    'type' => 'bar',
    'data' => [
        'labels' => $classLabels,
        'datasets' => [[
            'data' => $classAvgs,
            'backgroundColor' => ['#22c55e', '#3b82f6', '#8b5cf6', '#f59e0b', '#ef4444', '#dc2626'],
            'borderRadius' => 8,
        ]],
    ],
    'options' => [
        'plugins' => [
            'title' => ['display' => true, 'text' => 'Average Used Price by Displacement', 'color' => '#fff', 'font' => ['size' => 22]],
            'legend' => ['display' => false],
            'datalabels' => [
                'display' => true, 'color' => '#fff',
                'anchor' => 'end', 'align' => 'top',
                'font' => ['size' => 14, 'weight' => 'bold'],
                'formatter' => '__F3__',
            ],
        ],
        'scales' => [
            'y' => ['ticks' => ['color' => '#94a3b8'], 'grid' => ['color' => '#334155'], 'title' => ['display' => true, 'text' => 'man-yen', 'color' => '#94a3b8']],
            'x' => ['ticks' => ['color' => '#fff', 'font' => ['size' => 11]], 'grid' => ['display' => false]],
        ],
    ],
]);
$c = str_replace('"__F3__"', "(v)=>v+'万'", $c);
saveChart($c, 'bigbike-price-class.png');

// 4. Mileage vs price
$mLabels = $mileageData->pluck('mileage_range')->toArray();
$mAvgs = $mileageData->map(fn($m) => round($m->avg / 10000, 1))->toArray();
$c = json_encode([
    'type' => 'line',
    'data' => [
        'labels' => $mLabels,
        'datasets' => [[
            'data' => $mAvgs,
            'borderColor' => '#3b82f6',
            'backgroundColor' => 'rgba(59,130,246,0.2)',
            'fill' => true,
            'tension' => 0.3,
            'pointRadius' => 6,
            'pointBackgroundColor' => '#3b82f6',
        ]],
    ],
    'options' => [
        'plugins' => [
            'title' => ['display' => true, 'text' => 'Big Bike Price by Mileage (401cc+)', 'color' => '#fff', 'font' => ['size' => 22]],
            'legend' => ['display' => false],
            'datalabels' => [
                'display' => true, 'color' => '#fff',
                'anchor' => 'end', 'align' => 'top',
                'font' => ['size' => 14, 'weight' => 'bold'],
                'formatter' => '__F4__',
            ],
        ],
        'scales' => [
            'y' => ['ticks' => ['color' => '#94a3b8'], 'grid' => ['color' => '#334155'], 'title' => ['display' => true, 'text' => 'man-yen', 'color' => '#94a3b8']],
            'x' => ['ticks' => ['color' => '#fff', 'font' => ['size' => 12]], 'grid' => ['color' => '#334155']],
        ],
    ],
]);
$c = str_replace('"__F4__"', "(v)=>v+'万'", $c);
saveChart($c, 'bigbike-mileage-price.png');

// 5. Cost breakdown comparison: 400cc vs 1000cc
$c = json_encode([
    'type' => 'horizontalBar',
    'data' => [
        'labels' => ['Vehicle Tax', 'Insurance', 'Inspection', 'Oil Change', 'Tires', 'Gas (5000km)'],
        'datasets' => [
            ['label' => '400cc', 'data' => [0.6, 4.5, 3.5, 0.9, 2.0, 3.5], 'backgroundColor' => '#3b82f6', 'borderRadius' => 4],
            ['label' => '1000cc+', 'data' => [0.6, 7.5, 5.0, 1.5, 6.5, 5.0], 'backgroundColor' => '#ef4444', 'borderRadius' => 4],
        ],
    ],
    'options' => [
        'plugins' => [
            'title' => ['display' => true, 'text' => '400cc vs 1000cc+ Annual Cost Items', 'color' => '#fff', 'font' => ['size' => 22]],
            'legend' => ['labels' => ['color' => '#e2e8f0']],
            'datalabels' => [
                'display' => true, 'color' => '#fff',
                'anchor' => 'end', 'align' => 'right',
                'font' => ['size' => 13],
                'formatter' => '__F5__',
            ],
        ],
        'scales' => [
            'x' => ['ticks' => ['color' => '#94a3b8'], 'grid' => ['color' => '#334155'], 'title' => ['display' => true, 'text' => 'man-yen', 'color' => '#94a3b8']],
            'y' => ['ticks' => ['color' => '#fff', 'font' => ['size' => 12]], 'grid' => ['display' => false]],
        ],
    ],
]);
$c = str_replace('"__F5__"', "(v)=>v+'万'", $c);
saveChart($c, 'bigbike-cost-breakdown.png');

// ===== Phase 5: Replace placeholders =====
$body = str_replace('<!-- CHART:eyecatch -->', '![排気量別年間維持費](/storage/blog/bigbike-eyecatch.png)', $body);
$body = str_replace('<!-- CHART:annual_cost -->', '![年間維持費内訳](/storage/blog/bigbike-annual-cost.png)', $body);
$body = str_replace('<!-- CHART:price_class -->', '![排気量帯別中古平均価格](/storage/blog/bigbike-price-class.png)', $body);
$body = str_replace('<!-- CHART:mileage_price -->', '![走行距離別平均価格](/storage/blog/bigbike-mileage-price.png)', $body);
$body = str_replace('<!-- CHART:cost_breakdown -->', '![400cc vs 1000cc 維持費比較](/storage/blog/bigbike-cost-breakdown.png)', $body);

// ===== Phase 6: Add blogmura banner =====
$blogmuraBanner = <<<'BANNER'

---

<a href="https://bike.blogmura.com/ranking/in?p_cid=11215019" target="_blank"><img src="https://b.blogmura.com/bike/88_31.gif" width="88" height="31" border="0" alt="にほんブログ村 バイクブログへ" /></a><br /><a href="https://bike.blogmura.com/ranking/in?p_cid=11215019" target="_blank">にほんブログ村</a>
BANNER;

$body .= $blogmuraBanner;

// ===== Phase 7: Save to DB =====
$post = BlogPost::create([
    'author_id' => 2,
    'title' => $title,
    'slug' => 'big-bike-annual-cost-comparison-2026',
    'body' => $body,
    'eyecatch_image' => 'blog/bigbike-eyecatch.png',
    'status' => 'draft',
    'meta_title' => $title,
    'meta_description' => $metaDesc,
    'og_image' => 'blog/bigbike-eyecatch.png',
    'series_id' => 2,
    'reading_time_minutes' => 15,
]);

echo "\n===== BIG BIKE MAINTENANCE COST DONE =====\n";
echo "BlogPost ID: {$post->id}\n";
echo "Slug: big-bike-annual-cost-comparison-2026\n";
echo "Title: {$title}\n";
echo "Body: " . mb_strlen($body) . " chars\n";
