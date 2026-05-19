<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// ===== 400cc全体統計 =====
echo "=== 400cc全体統計 ===\n";
$total = DB::table('listings as l')
    ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
    ->where('l.is_sold_out', false)
    ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
    ->whereBetween('bm.displacement', [301, 400])
    ->selectRaw('COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg_price')
    ->first();
echo "在庫: {$total->stock}台, 平均¥" . number_format($total->avg_price) . "\n";

$sold = DB::table('listings as l')
    ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
    ->where('l.is_sold_out', true)
    ->where('l.updated_at', '>=', now()->subMonths(3))
    ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
    ->whereBetween('bm.displacement', [301, 400])
    ->selectRaw('COUNT(*) as sold, ROUND(AVG(l.total_price)) as avg_price')
    ->first();
echo "直近3ヶ月売却: {$sold->sold}台, 平均¥" . number_format($sold->avg_price) . "\n\n";

// ===== カテゴリ別 =====
echo "=== 400cc カテゴリ別 ===\n";
$cats = DB::table('listings as l')
    ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
    ->where('l.is_sold_out', false)
    ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
    ->whereBetween('bm.displacement', [301, 400])
    ->whereNotNull('bm.category')
    ->selectRaw('bm.category, COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg_price')
    ->groupBy('bm.category')
    ->having('stock', '>=', 10)
    ->orderByDesc('stock')
    ->get();
echo $cats->toJson(JSON_UNESCAPED_UNICODE) . "\n\n";

// ===== 全車種（在庫10台以上）=====
echo "=== 400cc 全車種データ ===\n";
$models = DB::table('listings as l')
    ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
    ->join('manufacturers as m', 'bm.manufacturer_id', '=', 'm.id')
    ->where('l.is_sold_out', false)
    ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
    ->whereBetween('bm.displacement', [301, 400])
    ->selectRaw('bm.id, CONCAT(m.name," ",bm.name) as full_name, bm.slug, m.slug as maker_slug, bm.category, bm.displacement, COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg_price, ROUND(MIN(l.total_price)) as min_price, ROUND(MAX(l.total_price)) as max_price')
    ->groupByRaw('bm.id, bm.name, bm.slug, m.name, m.slug, bm.category, bm.displacement')
    ->having('stock', '>=', 10)
    ->orderByDesc('stock')
    ->get();
echo "車種数: " . $models->count() . "\n";
foreach ($models->take(30) as $m) {
    echo "{$m->full_name} [{$m->category}] {$m->displacement}cc stock={$m->stock} avg=¥" . number_format($m->avg_price) . " min=¥" . number_format($m->min_price) . " max=¥" . number_format($m->max_price) . " slug={$m->slug}\n";
}
echo "\n";

// ===== 売却日数 =====
echo "=== 400cc 売却日数ランキング ===\n";
$speed = DB::table('listings as l')
    ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
    ->join('manufacturers as m', 'bm.manufacturer_id', '=', 'm.id')
    ->where('l.is_sold_out', true)
    ->where('l.updated_at', '>=', now()->subMonths(3))
    ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
    ->whereBetween('bm.displacement', [301, 400])
    ->whereRaw('DATEDIFF(l.updated_at, l.created_at) BETWEEN 1 AND 180')
    ->selectRaw('bm.id, CONCAT(m.name," ",bm.name) as full_name, bm.slug, bm.category, ROUND(AVG(DATEDIFF(l.updated_at, l.created_at))) as avg_days, COUNT(*) as sold, ROUND(AVG(l.total_price)) as avg_price')
    ->groupByRaw('bm.id, bm.name, bm.slug, m.name, bm.category')
    ->having('sold', '>=', 10)
    ->orderBy('avg_days')
    ->get();

echo "TOP10最速:\n";
foreach ($speed->take(10) as $s) {
    echo "  {$s->full_name} [{$s->category}]: {$s->avg_days}日 ({$s->sold}台, avg¥" . number_format($s->avg_price) . ")\n";
}
echo "WORST10最遅:\n";
foreach ($speed->sortByDesc('avg_days')->take(10) as $s) {
    echo "  {$s->full_name} [{$s->category}]: {$s->avg_days}日 ({$s->sold}台, avg¥" . number_format($s->avg_price) . ")\n";
}
echo "\n";

// ===== 売却台数 =====
echo "=== 400cc 売却台数TOP10 ===\n";
foreach ($speed->sortByDesc('sold')->take(10) as $s) {
    echo "  {$s->full_name}: {$s->sold}台 ({$s->avg_days}日, avg¥" . number_format($s->avg_price) . ")\n";
}
echo "\n";

// ===== 月次変動 =====
echo "=== 400cc 月次変動 ===\n";
$mm = DB::table('listings as l')
    ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
    ->join('manufacturers as m', 'bm.manufacturer_id', '=', 'm.id')
    ->where('l.is_sold_out', true)
    ->whereIn(DB::raw('DATE_FORMAT(l.updated_at, "%Y-%m")'), ['2026-02', '2026-03'])
    ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
    ->whereBetween('bm.displacement', [301, 400])
    ->selectRaw('bm.id, CONCAT(m.name," ",bm.name) as full_name, DATE_FORMAT(l.updated_at, "%Y-%m") as month, ROUND(AVG(l.total_price)) as avg_price, COUNT(*) as sold')
    ->groupByRaw('bm.id, bm.name, m.name, DATE_FORMAT(l.updated_at, "%Y-%m")')
    ->having('sold', '>=', 5)
    ->orderByRaw('bm.id, month')
    ->get()
    ->groupBy('id');

$changes = [];
foreach ($mm as $id => $rows) {
    if ($rows->count() < 2) continue;
    $feb = $rows->firstWhere('month', '2026-02');
    $mar = $rows->firstWhere('month', '2026-03');
    if (!$feb || !$mar) continue;
    $pct = round(($mar->avg_price - $feb->avg_price) / $feb->avg_price * 100, 1);
    $changes[] = ['name' => $feb->full_name, 'feb' => $feb->avg_price, 'mar' => $mar->avg_price, 'change' => $pct];
}
usort($changes, fn($a, $b) => $a['change'] <=> $b['change']);
echo "値下がり:\n";
foreach (array_slice($changes, 0, 5) as $c) {
    echo "  {$c['name']}: ¥" . number_format($c['feb']) . " → ¥" . number_format($c['mar']) . " ({$c['change']}%)\n";
}
echo "値上がり:\n";
foreach (array_slice($changes, -5) as $c) {
    echo "  {$c['name']}: ¥" . number_format($c['feb']) . " → ¥" . number_format($c['mar']) . " ({$c['change']}%)\n";
}

// ===== 250ccとの比較 =====
echo "\n=== 250cc vs 400cc 比較 ===\n";
$cc250 = DB::table('listings as l')
    ->join('bike_models as bm', 'l.bike_model_id', '=', 'bm.id')
    ->where('l.is_sold_out', false)
    ->whereNotNull('l.total_price')->where('l.total_price', '>', 0)
    ->whereBetween('bm.displacement', [126, 250])
    ->selectRaw('COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg_price')
    ->first();
echo "250cc: 在庫{$cc250->stock}台 平均¥" . number_format($cc250->avg_price) . "\n";
echo "400cc: 在庫{$total->stock}台 平均¥" . number_format($total->avg_price) . "\n";
