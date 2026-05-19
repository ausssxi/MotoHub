<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== 125cc全体統計 ===\n";
$total = DB::table('listings as l')->join('bike_models as bm','l.bike_model_id','=','bm.id')
    ->where('l.is_sold_out',false)->whereNotNull('l.total_price')->where('l.total_price','>',0)
    ->whereBetween('bm.displacement',[51,125])
    ->selectRaw('COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg')->first();
$sold = DB::table('listings as l')->join('bike_models as bm','l.bike_model_id','=','bm.id')
    ->where('l.is_sold_out',true)->where('l.updated_at','>=',now()->subMonths(3))
    ->whereNotNull('l.total_price')->where('l.total_price','>',0)
    ->whereBetween('bm.displacement',[51,125])
    ->selectRaw('COUNT(*) as sold, ROUND(AVG(l.total_price)) as avg')->first();
echo "在庫:{$total->stock} avg¥".number_format($total->avg)." 3M売却:{$sold->sold} avg¥".number_format($sold->avg)."\n\n";

echo "=== カテゴリ別 ===\n";
$cats = DB::table('listings as l')->join('bike_models as bm','l.bike_model_id','=','bm.id')
    ->where('l.is_sold_out',false)->whereNotNull('l.total_price')->where('l.total_price','>',0)
    ->whereBetween('bm.displacement',[51,125])->whereNotNull('bm.category')
    ->selectRaw('bm.category, COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg')
    ->groupBy('bm.category')->having('stock','>=',20)->orderByDesc('stock')->get();
echo $cats->toJson(JSON_UNESCAPED_UNICODE)."\n\n";

echo "=== 全車種(stock>=20) ===\n";
$models = DB::table('listings as l')->join('bike_models as bm','l.bike_model_id','=','bm.id')
    ->join('manufacturers as m','bm.manufacturer_id','=','m.id')
    ->where('l.is_sold_out',false)->whereNotNull('l.total_price')->where('l.total_price','>',0)
    ->whereBetween('bm.displacement',[51,125])
    ->selectRaw('bm.id,CONCAT(m.name," ",bm.name) as name,bm.slug,bm.category,bm.displacement,COUNT(*) as stock,ROUND(AVG(l.total_price)) as avg,ROUND(MIN(l.total_price)) as min,ROUND(MAX(l.total_price)) as max')
    ->groupByRaw('bm.id,bm.name,bm.slug,m.name,bm.category,bm.displacement')
    ->having('stock','>=',20)->orderByDesc('stock')->get();
echo "車種数: ".$models->count()."\n";
foreach($models->take(25) as $m) echo "{$m->name} [{$m->category}] {$m->displacement}cc stk={$m->stock} avg=¥".number_format($m->avg)." min=¥".number_format($m->min)." slug={$m->slug}\n";
echo "\n";

echo "=== 売却日数 ===\n";
$speed = DB::table('listings as l')->join('bike_models as bm','l.bike_model_id','=','bm.id')
    ->join('manufacturers as m','bm.manufacturer_id','=','m.id')
    ->where('l.is_sold_out',true)->where('l.updated_at','>=',now()->subMonths(3))
    ->whereNotNull('l.total_price')->where('l.total_price','>',0)
    ->whereBetween('bm.displacement',[51,125])
    ->whereRaw('DATEDIFF(l.updated_at,l.created_at) BETWEEN 1 AND 180')
    ->selectRaw('bm.id,CONCAT(m.name," ",bm.name) as name,bm.slug,bm.category,ROUND(AVG(DATEDIFF(l.updated_at,l.created_at))) as days,COUNT(*) as sold,ROUND(AVG(l.total_price)) as avg')
    ->groupByRaw('bm.id,bm.name,bm.slug,m.name,bm.category')
    ->having('sold','>=',10)->orderBy('days')->get();
echo "TOP10:\n";
foreach($speed->take(10) as $s) echo "  {$s->name}: {$s->days}d ({$s->sold}台 ¥".number_format($s->avg).")\n";
echo "WORST10:\n";
foreach($speed->sortByDesc('days')->take(10) as $s) echo "  {$s->name}: {$s->days}d ({$s->sold}台 ¥".number_format($s->avg).")\n";
echo "\nTOP売却台数:\n";
foreach($speed->sortByDesc('sold')->take(10) as $s) echo "  {$s->name}: {$s->sold}台 ({$s->days}d ¥".number_format($s->avg).")\n";
