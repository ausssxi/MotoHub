<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

// 月別平均価格（全体+排気量帯別）
echo "=== 月別全体平均 ===\n";
$monthly = DB::table('listings')
    ->where('is_sold_out', true)
    ->whereNotNull('total_price')->where('total_price', '>', 0)
    ->where('updated_at', '>=', '2026-01-01')
    ->selectRaw('DATE_FORMAT(updated_at, "%Y-%m") as month, ROUND(AVG(total_price)) as avg, COUNT(*) as sold')
    ->groupByRaw('DATE_FORMAT(updated_at, "%Y-%m")')
    ->orderBy('month')->get();
echo $monthly->toJson(JSON_UNESCAPED_UNICODE) . "\n\n";

// 排気量帯別月別
echo "=== 排気量帯別月別 ===\n";
foreach (['~125cc'=>[1,125], '126-250cc'=>[126,250], '251-400cc'=>[251,400], '401-750cc'=>[401,750], '751cc~'=>[751,9999]] as $label=>[$min,$max]) {
    $data = DB::table('listings as l')
        ->join('bike_models as bm','l.bike_model_id','=','bm.id')
        ->where('l.is_sold_out', true)
        ->whereNotNull('l.total_price')->where('l.total_price','>',0)
        ->whereBetween('bm.displacement', [$min,$max])
        ->whereIn(DB::raw('DATE_FORMAT(l.updated_at,"%Y-%m")'), ['2026-02','2026-03'])
        ->selectRaw('DATE_FORMAT(l.updated_at,"%Y-%m") as month, ROUND(AVG(l.total_price)) as avg, COUNT(*) as sold')
        ->groupByRaw('DATE_FORMAT(l.updated_at,"%Y-%m")')
        ->orderBy('month')->get();
    $feb = $data->firstWhere('month','2026-02');
    $mar = $data->firstWhere('month','2026-03');
    if ($feb && $mar) {
        $pct = round(($mar->avg - $feb->avg) / $feb->avg * 100, 1);
        echo "{$label}: Feb ¥".number_format($feb->avg)."({$feb->sold}) → Mar ¥".number_format($mar->avg)."({$mar->sold}) {$pct}%\n";
    }
}
echo "\n";

// 値上がりTOP10（2月→3月、売却10台以上）
echo "=== 値上がりTOP10 ===\n";
$mm = DB::table('listings as l')
    ->join('bike_models as bm','l.bike_model_id','=','bm.id')
    ->join('manufacturers as m','bm.manufacturer_id','=','m.id')
    ->where('l.is_sold_out', true)
    ->whereIn(DB::raw('DATE_FORMAT(l.updated_at,"%Y-%m")'), ['2026-02','2026-03'])
    ->whereNotNull('l.total_price')->where('l.total_price','>',0)
    ->selectRaw('bm.id, CONCAT(m.name," ",bm.name) as name, bm.slug, bm.displacement, DATE_FORMAT(l.updated_at,"%Y-%m") as month, ROUND(AVG(l.total_price)) as avg, COUNT(*) as sold')
    ->groupByRaw('bm.id,bm.name,bm.slug,m.name,bm.displacement,DATE_FORMAT(l.updated_at,"%Y-%m")')
    ->having('sold','>=',8)
    ->orderByRaw('bm.id,month')
    ->get()->groupBy('id');

$changes = [];
foreach ($mm as $id=>$rows) {
    if ($rows->count()<2) continue;
    $feb = $rows->firstWhere('month','2026-02');
    $mar = $rows->firstWhere('month','2026-03');
    if (!$feb||!$mar) continue;
    $pct = round(($mar->avg - $feb->avg) / $feb->avg * 100, 1);
    $changes[] = ['name'=>$feb->name, 'slug'=>$feb->slug, 'cc'=>$feb->displacement, 'feb'=>(int)$feb->avg, 'mar'=>(int)$mar->avg, 'change'=>$pct, 'sold'=>(int)$mar->sold];
}
usort($changes, fn($a,$b) => $b['change'] <=> $a['change']);

echo "値上がり:\n";
foreach (array_slice($changes, 0, 12) as $c) {
    echo "  {$c['name']} ({$c['cc']}cc): ¥".number_format($c['feb'])." → ¥".number_format($c['mar'])." ({$c['change']}%) sold={$c['sold']}\n";
}

usort($changes, fn($a,$b) => $a['change'] <=> $b['change']);
echo "値下がり:\n";
foreach (array_slice($changes, 0, 12) as $c) {
    echo "  {$c['name']} ({$c['cc']}cc): ¥".number_format($c['feb'])." → ¥".number_format($c['mar'])." ({$c['change']}%) sold={$c['sold']}\n";
}
echo "\n";

// 在庫台数の変化（春の出回り）
echo "=== 在庫推移 ===\n";
$stockNow = DB::table('listings')->where('is_sold_out', false)->count();
$soldRecent = DB::table('listings')->where('is_sold_out', true)->where('updated_at', '>=', now()->subMonths(1))->count();
echo "現在庫: {$stockNow}台, 直近1ヶ月売却: {$soldRecent}台\n\n";

// 季節性: 夏に需要が高まる車種カテゴリ
echo "=== カテゴリ別売却台数 ===\n";
$catSold = DB::table('listings as l')
    ->join('bike_models as bm','l.bike_model_id','=','bm.id')
    ->where('l.is_sold_out', true)
    ->whereIn(DB::raw('DATE_FORMAT(l.updated_at,"%Y-%m")'), ['2026-02','2026-03'])
    ->whereNotNull('bm.category')
    ->selectRaw('bm.category, DATE_FORMAT(l.updated_at,"%Y-%m") as month, COUNT(*) as sold')
    ->groupByRaw('bm.category, DATE_FORMAT(l.updated_at,"%Y-%m")')
    ->having('sold','>=',20)
    ->orderByRaw('bm.category, month')
    ->get()->groupBy('category');

foreach ($catSold as $cat=>$rows) {
    $feb = $rows->firstWhere('month','2026-02');
    $mar = $rows->firstWhere('month','2026-03');
    if ($feb && $mar) {
        $pct = round(($mar->sold - $feb->sold) / $feb->sold * 100, 1);
        echo "  {$cat}: {$feb->sold} → {$mar->sold} ({$pct}%)\n";
    }
}
