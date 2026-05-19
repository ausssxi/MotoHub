<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// 初心者向け = 手頃(~50万) + 在庫豊富 + 回転速い + リセール良い
// 125cc / 250cc / 400cc 各排気量帯

foreach ([
    '125cc' => [51, 125],
    '250cc' => [126, 250],
    '400cc' => [301, 400],
] as $label => [$min, $max]) {
    echo "=== {$label} 初心者向け候補 ===\n";

    // 在庫50台以上、平均50万以下、売却10台以上
    $candidates = DB::table('listings as l')
        ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
        ->join('manufacturers as m', 'bm.manufacturer_id', '=', 'm.id')
        ->where('l.is_sold_out', false)
        ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
        ->whereBetween('bm.displacement', [$min, $max])
        ->selectRaw('bm.id, CONCAT(m.name," ",bm.name) as full_name, bm.slug, bm.category, bm.displacement, COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg_price, ROUND(MIN(l.total_price)) as min_price')
        ->groupByRaw('bm.id, bm.name, bm.slug, m.name, bm.category, bm.displacement')
        ->having('stock', '>=', 30)
        ->orderByDesc('stock')
        ->get();

    // 売却日数も取得
    $speedData = DB::table('listings as l')
        ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
        ->where('l.is_sold_out', true)
        ->where('l.updated_at', '>=', now()->subMonths(3))
        ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
        ->whereBetween('bm.displacement', [$min, $max])
        ->whereRaw('DATEDIFF(l.updated_at, l.created_at) BETWEEN 1 AND 180')
        ->selectRaw('bm.id, ROUND(AVG(DATEDIFF(l.updated_at, l.created_at))) as avg_days, COUNT(*) as sold')
        ->groupBy('bm.id')
        ->having('sold', '>=', 5)
        ->pluck('avg_days', 'id');

    // 初心者スコア計算
    $scored = [];
    foreach ($candidates as $c) {
        if ($label === '400cc' && $c->avg_price > 1000000) continue; // 400ccは100万以下
        if ($label !== '400cc' && $c->avg_price > 600000) continue; // 125/250は60万以下

        $days = $speedData[$c->id] ?? 50;
        // スコア: 在庫多い + 安い + 回転速い
        $score = ($c->stock / 10) + (600000 - min($c->avg_price, 600000)) / 10000 + (50 - min($days, 50)) * 2;
        $scored[] = [
            'name' => $c->full_name,
            'slug' => $c->slug,
            'category' => $c->category,
            'cc' => $c->displacement,
            'stock' => $c->stock,
            'avg' => $c->avg_price,
            'min' => $c->min_price,
            'days' => $days,
            'score' => round($score, 1),
        ];
    }
    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

    foreach (array_slice($scored, 0, 10) as $s) {
        echo "  {$s['name']} [{$s['category']}] {$s['cc']}cc stock={$s['stock']} avg=¥" . number_format($s['avg']) . " min=¥" . number_format($s['min']) . " days={$s['days']} score={$s['score']}\n";
    }
    echo "\n";
}

// 避けるべきバイクの特徴（高額旧車、在庫少、回転遅い）
echo "=== 初心者が避けるべき車種の例 ===\n";
$avoid = DB::table('listings as l')
    ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
    ->join('manufacturers as m', 'bm.manufacturer_id', '=', 'm.id')
    ->where('l.is_sold_out', false)
    ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
    ->whereBetween('bm.displacement', [51, 400])
    ->selectRaw('CONCAT(m.name," ",bm.name) as full_name, bm.category, COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg_price')
    ->groupByRaw('bm.id, bm.name, m.name, bm.category')
    ->having('stock', '>=', 20)
    ->havingRaw('AVG(l.total_price) > 1500000')
    ->orderByDesc(DB::raw('AVG(l.total_price)'))
    ->limit(10)
    ->get();
foreach ($avoid as $a) {
    echo "  {$a->full_name} [{$a->category}] stock={$a->stock} avg=¥" . number_format($a->avg_price) . "\n";
}
