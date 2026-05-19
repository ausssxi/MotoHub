<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use Illuminate\Support\Facades\DB;

echo "=== 大型バイク(401cc~)全体統計 ===\n";
$total = DB::table('listings as l')->join('bike_models as bm','l.bike_model_id','=','bm.id')
    ->where('l.is_sold_out',false)->whereNotNull('l.total_price')->where('l.total_price','>',0)
    ->where('bm.displacement','>',400)
    ->selectRaw('COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg, ROUND(MIN(l.total_price)) as min, ROUND(MAX(l.total_price)) as max')->first();
$sold = DB::table('listings as l')->join('bike_models as bm','l.bike_model_id','=','bm.id')
    ->where('l.is_sold_out',true)->where('l.updated_at','>=',now()->subMonths(3))
    ->whereNotNull('l.total_price')->where('l.total_price','>',0)
    ->where('bm.displacement','>',400)
    ->selectRaw('COUNT(*) as sold, ROUND(AVG(l.total_price)) as avg')->first();
echo "在庫:{$total->stock} avg¥".number_format($total->avg)." min¥".number_format($total->min)." max¥".number_format($total->max)."\n";
echo "3M売却:{$sold->sold} avg¥".number_format($sold->avg)."\n\n";

echo "=== 排気量帯別 ===\n";
foreach (['401-750cc'=>[401,750], '751-1000cc'=>[751,1000], '1001cc~'=>[1001,9999]] as $label=>[$min,$max]) {
    $s = DB::table('listings as l')->join('bike_models as bm','l.bike_model_id','=','bm.id')
        ->where('l.is_sold_out',false)->whereNotNull('l.total_price')->where('l.total_price','>',0)
        ->whereBetween('bm.displacement',[$min,$max])
        ->selectRaw('COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg')->first();
    $sl = DB::table('listings as l')->join('bike_models as bm','l.bike_model_id','=','bm.id')
        ->where('l.is_sold_out',true)->where('l.updated_at','>=',now()->subMonths(3))
        ->whereNotNull('l.total_price')->where('l.total_price','>',0)
        ->whereBetween('bm.displacement',[$min,$max])
        ->selectRaw('COUNT(*) as sold, ROUND(AVG(l.total_price)) as avg')->first();
    echo "{$label}: stock={$s->stock} avg¥".number_format($s->avg)." / sold={$sl->sold} avg¥".number_format($sl->avg)."\n";
}
echo "\n";

echo "=== カテゴリ別(401cc~) ===\n";
$cats = DB::table('listings as l')->join('bike_models as bm','l.bike_model_id','=','bm.id')
    ->where('l.is_sold_out',false)->whereNotNull('l.total_price')->where('l.total_price','>',0)
    ->where('bm.displacement','>',400)->whereNotNull('bm.category')
    ->selectRaw('bm.category, COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg')
    ->groupBy('bm.category')->having('stock','>=',10)->orderByDesc('stock')->get();
foreach($cats as $c) echo "  {$c->category}: {$c->stock}台 avg¥".number_format($c->avg)."\n";
echo "\n";

echo "=== 人気車種TOP30(401cc~, stock>=15) ===\n";
$models = DB::table('listings as l')->join('bike_models as bm','l.bike_model_id','=','bm.id')
    ->join('manufacturers as m','bm.manufacturer_id','=','m.id')
    ->where('l.is_sold_out',false)->whereNotNull('l.total_price')->where('l.total_price','>',0)
    ->where('bm.displacement','>',400)
    ->selectRaw('bm.id,CONCAT(m.name," ",bm.name) as name,bm.slug,bm.category,bm.displacement,COUNT(*) as stock,ROUND(AVG(l.total_price)) as avg,ROUND(MIN(l.total_price)) as min,ROUND(MAX(l.total_price)) as max')
    ->groupByRaw('bm.id,bm.name,bm.slug,m.name,bm.category,bm.displacement')
    ->having('stock','>=',15)->orderByDesc('stock')->get();
echo "車種数: ".$models->count()."\n";
foreach($models->take(30) as $m) echo "  {$m->name} [{$m->category}] {$m->displacement}cc stk={$m->stock} avg=¥".number_format($m->avg)." min=¥".number_format($m->min)." max=¥".number_format($m->max)." slug={$m->slug}\n";
echo "\n";

echo "=== 維持費関連: 車検あり車種の価格帯別分布(401cc~) ===\n";
$priceRanges = DB::table('listings as l')->join('bike_models as bm','l.bike_model_id','=','bm.id')
    ->where('l.is_sold_out',false)->whereNotNull('l.total_price')->where('l.total_price','>',0)
    ->where('bm.displacement','>',400)
    ->selectRaw("
        CASE
            WHEN l.total_price < 300000 THEN '~30万'
            WHEN l.total_price < 500000 THEN '30-50万'
            WHEN l.total_price < 800000 THEN '50-80万'
            WHEN l.total_price < 1000000 THEN '80-100万'
            WHEN l.total_price < 1500000 THEN '100-150万'
            WHEN l.total_price < 2000000 THEN '150-200万'
            ELSE '200万~'
        END as price_range,
        COUNT(*) as cnt
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
    ->orderByRaw("MIN(l.total_price)")->get();
foreach($priceRanges as $r) echo "  {$r->price_range}: {$r->cnt}台\n";
echo "\n";

echo "=== 売却日数(401cc~) ===\n";
$speed = DB::table('listings as l')->join('bike_models as bm','l.bike_model_id','=','bm.id')
    ->join('manufacturers as m','bm.manufacturer_id','=','m.id')
    ->where('l.is_sold_out',true)->where('l.updated_at','>=',now()->subMonths(3))
    ->whereNotNull('l.total_price')->where('l.total_price','>',0)
    ->where('bm.displacement','>',400)
    ->whereRaw('DATEDIFF(l.updated_at,l.created_at) BETWEEN 1 AND 180')
    ->selectRaw('bm.id,CONCAT(m.name," ",bm.name) as name,bm.slug,bm.category,bm.displacement,ROUND(AVG(DATEDIFF(l.updated_at,l.created_at))) as days,COUNT(*) as sold,ROUND(AVG(l.total_price)) as avg')
    ->groupByRaw('bm.id,bm.name,bm.slug,m.name,bm.category,bm.displacement')
    ->having('sold','>=',8)->orderBy('days')->get();
echo "速売TOP10:\n";
foreach($speed->take(10) as $s) echo "  {$s->name} ({$s->displacement}cc): {$s->days}d ({$s->sold}台 ¥".number_format($s->avg).")\n";
echo "長期在庫TOP10:\n";
foreach($speed->sortByDesc('days')->take(10) as $s) echo "  {$s->name} ({$s->displacement}cc): {$s->days}d ({$s->sold}台 ¥".number_format($s->avg).")\n";
echo "\n";

echo "=== コスパ良好車種(401-750cc, avg50万以下, stock>=15) ===\n";
$costEffective = $models->filter(fn($m)=>$m->displacement<=750 && $m->avg<=500000 && $m->stock>=15)->sortBy('avg');
foreach($costEffective->take(10) as $m) echo "  {$m->name} [{$m->category}] {$m->displacement}cc avg=¥".number_format($m->avg)." stk={$m->stock}\n";
echo "\n";

echo "=== メーカー別シェア(401cc~) ===\n";
$mfr = DB::table('listings as l')->join('bike_models as bm','l.bike_model_id','=','bm.id')
    ->join('manufacturers as m','bm.manufacturer_id','=','m.id')
    ->where('l.is_sold_out',false)->whereNotNull('l.total_price')->where('l.total_price','>',0)
    ->where('bm.displacement','>',400)
    ->selectRaw('m.name, COUNT(*) as stock, ROUND(AVG(l.total_price)) as avg')
    ->groupBy('m.name')->having('stock','>=',20)->orderByDesc('stock')->get();
foreach($mfr as $m) echo "  {$m->name}: {$m->stock}台 avg¥".number_format($m->avg)."\n";
echo "\n";

echo "=== 走行距離別平均価格(401cc~) ===\n";
$mileage = DB::table('listings as l')->join('bike_models as bm','l.bike_model_id','=','bm.id')
    ->where('l.is_sold_out',false)->whereNotNull('l.total_price')->where('l.total_price','>',0)
    ->where('bm.displacement','>',400)
    ->whereNotNull('l.mileage')->where('l.mileage','>',0)
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
    ->orderByRaw("MIN(l.mileage)")->get();
foreach($mileage as $m) echo "  {$m->mileage_range}: {$m->cnt}台 avg¥".number_format($m->avg)."\n";
