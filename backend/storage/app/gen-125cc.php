<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
use App\Models\BlogPost;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

$apiKey = config('services.anthropic.api_key') ?: env('ANTHROPIC_API_KEY');

$data = <<<'DATA'
■ 125ccクラス全体統計
- 在庫: 26,780台
- 平均価格: ¥540,602
- 直近3ヶ月売却: 3,588台, 売却平均¥498,338
- 対象車種数: 152モデル（在庫20台以上）

■ カテゴリ別
| カテゴリ | 在庫 | 平均価格 |
|---------|------|---------|
| ミニバイク | 6,410台 | ¥394,826 |
| スクーター(51cc以上) | 6,073台 | ¥286,529 |
| 輸入車 | 2,553台 | ¥1,178,600 |
| 原付スクーター | 2,196台 | ¥274,300 |
| ネイキッド | 2,129台 | ¥756,024 |
| スクランブラー | 1,707台 | ¥484,393 |
| スポーツ/レプリカ | 1,449台 | ¥878,856 |
| オールドルック | 1,347台 | ¥790,368 |
| オフロード | 862台 | ¥477,795 |
| 電動バイク | 207台 | ¥301,010 |

■ 在庫台数TOP15
1. スーパーカブ110: 1,548台 ¥357,014 min¥130,000（ミニバイク）★最多
2. クロスカブ110: 1,263台 ¥406,046 min¥189,100
3. モンキー125: 1,161台 ¥482,370 min¥150,000
4. リード125: 942台 ¥352,074 min¥208,000（スクーター）
5. アドレス125: 938台 ¥272,707 min¥95,000 ★最安クラス
6. CT125ハンターカブ: 923台 ¥478,272 min¥322,600
7. Dio110ベーシック: 631台 ¥260,007
8. アヴェニス125: 615台 ¥280,012
9. ダックス125: 614台 ¥476,550
10. PCX125: 536台 ¥382,441
11. XSR125: 493台 ¥508,428
12. バーグマンストリート125EX: 489台 ¥316,499
13. Dio110 Lite: 484台 ¥260,594
14. Dio110: 393台 ¥243,001
15. スーパーカブC125: 387台 ¥477,385

■ 売却日数TOP10（最速）
1. スーパーカブ110MD(郵政): 37日
2. PG-1: 37日
3. GN125: 38日
4. ジャイロX: 39日
5. WR125R: 40日（55台, ¥561,973）
6. スウィッシュ: 41日
7. CRF125F: 42日
8. Dio110 Lite: 42日（42台）
9. ボンネビルT100: 42日（46台）
10. Dio110: 42日（73台）

■ 売却台数TOP10（人気度）
1. モンキー125: 382台（45日）★圧倒的人気
2. CT125ハンターカブ: 278台（45日）
3. ダックス125: 175台（45日）
4. スーパーカブ110: 151台（45日）
5. クロスカブ110: 148台（45日）
6. アドレス125: 141台（44日）
7. リード125: 133台（44日）
8. ジョグ125: 120台（45日）
9. Dio110ベーシック: 84台（43日）
10. Dio110: 73台（42日）

■ 125ccの特徴
- 原付二種 = AT限定小型二輪免許でOK（最短2日で取得可能）
- ファミリーバイク特約で保険料激安（年間1万円程度）
- 車検なし
- 高速道路は走行不可
- 通勤・通学に最適
- ホンダのカブシリーズが市場を支配（上位10車種中7車種がホンダ）
DATA;

echo "Calling API for 125cc...\n";
$response = Http::withHeaders([
    'x-api-key'=>$apiKey, 'anthropic-version'=>'2023-06-01', 'content-type'=>'application/json',
])->timeout(180)->post('https://api.anthropic.com/v1/messages', [
    'model'=>'claude-sonnet-4-20250514', 'max_tokens'=>8000,
    'messages'=>[['role'=>'user','content'=><<<PROMPT
あなたはバイク市場に精通したデータジャーナリストです。

【テーマ】125ccバイク全車種を実売データで徹底比較【2026年版完全ガイド】

【実データ】
{$data}

【執筆ルール】
1. JSON: {{"title":"…","body":"…(Markdown)","meta_description":"…(120文字以内)"}}
   ※bodyフィールド内のダブルクォートは\"でエスケープ
2. **5,000〜7,000文字**
3. 画像5つ:
   - <!-- CHART:eyecatch --> — 主要車種価格比較
   - <!-- CHART:category --> — カテゴリ別平均価格
   - <!-- CHART:sales --> — 売却台数TOP10
   - <!-- CHART:speed --> — 売却日数TOP10
   - <!-- CHART:honda --> — ホンダカブシリーズ比較

4. 構成:
   - リード文: 26,780台の125ccデータを徹底分析
   - 市場概況（在庫/価格/ホンダ支配の実態）
   - 125ccのメリット（免許、保険、車検なし、通勤）
   - カテゴリ別ガイド（ミニバイク/スクーター/ネイキッド/スクランブラー/オフロード 各3〜5車種）
   - ホンダカブファミリー徹底比較（カブ110/クロスカブ/C125/CT125/リトルカブ）
   - 通勤スクーター最強決定戦（リード/アドレス/Dio/PCX/アヴェニス）
   - 売却台数ランキング（モンキー125が382台で圧倒）
   - コスパ最強TOP5
   - 目的別おすすめ（通勤/ツーリング/趣味/2台目）
   - まとめ + 主要車種比較テーブル

5. 内部リンク:
   - [スーパーカブ110の在庫を見る](/bikes/catalog/super-cub-110)
   - [モンキー125の在庫を見る](/bikes/catalog/monkey-125)
   - [CT125ハンターカブの在庫を見る](/bikes/catalog/ct125)
   - [PCX125の在庫を見る](/bikes/catalog/pcx-125)
   - [250cc全車種比較](/blog/250cc-all-models-comparison-2026)
   - [初心者おすすめバイク](/blog/best-bikes-for-beginners-2026)
   - [125ccを探す](/bikes/search?displacement_min=51&displacement_max=125)
   - その他: cross-cub-110, dax-125, lead-125, address-125, xsr125, cb125r, wr125r

6. ポータルの分析記事トーン、表3つ以上、2026年統一
PROMPT
    ]],
]);

if(!$response->successful()){echo "Error: ".$response->body()."\n";exit(1);}
$content=$response->json('content.0.text');
echo "API OK. Len: ".strlen($content)."\n";

$cleaned=preg_replace('/^```json\s*/m','',$content);
$cleaned=preg_replace('/```\s*$/m','',$cleaned);
$cleaned=str_replace(["\u{201C}","\u{201D}","\u{2018}","\u{2019}"],['"','"',"'","'"],$cleaned);
if(preg_match('/\{[\s\S]*\}/u',$cleaned,$m)){$article=json_decode($m[0],true);
if(!$article&&preg_match('/"body"\s*:\s*"([\s\S]*)",\s*"meta_description"/u',$m[0],$bm)){
preg_match('/"title"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u',$m[0],$tm);
preg_match('/"meta_description"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u',$m[0],$dm);
$article=['title'=>$tm[1]??'','body'=>stripcslashes($bm[1]),'meta_description'=>$dm[1]??''];}}
if(!$article||!isset($article['body'])){echo "Parse fail\n";file_put_contents(__DIR__.'/debug-125.txt',$content);exit(1);}

$title=str_replace('2024年','2026年',$article['title']);
$body=str_replace('2024年','2026年',$article['body']);
$metaDesc=$article['meta_description'];
echo "Title: {$title}\nBody: ".mb_strlen($body)." chars\n";

function saveChart($json,$fn){
$url='https://quickchart.io/chart?c='.urlencode($json).'&w=1200&h=630&bkg=%231e293b&f=png';
$img=@file_get_contents($url,false,stream_context_create(['http'=>['timeout'=>30]]));
if($img&&strlen($img)>1000){Storage::disk('public')->put("blog/{$fn}",$img);echo "OK {$fn}\n";return true;}echo "FAIL {$fn}\n";return false;}

// Eyecatch
$c=json_encode(['type'=>'bar','data'=>['labels'=>['SuperCub','CrossCub','Monkey125','CT125','Dax125','PCX125','XSR125','Address'],'datasets'=>[['data'=>[35.7,40.6,48.2,47.8,47.7,38.2,50.8,27.3],'backgroundColor'=>['#ef4444','#f59e0b','#22c55e','#3b82f6','#8b5cf6','#06b6d4','#f97316','#64748b'],'borderRadius'=>8]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>['125cc All Models Guide 2026','26,780 Units in Stock'],'color'=>'#fff','font'=>['size'=>26,'weight'=>'bold']],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'top','font'=>['size'=>13,'weight'=>'bold'],'formatter'=>'__F__']],'scales'=>['y'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155']],'x'=>['ticks'=>['color'=>'#fff','font'=>['size'=>11]],'grid'=>['display'=>false]]]]]);
$c=str_replace('"__F__"',"(v)=>v+'万'",$c);saveChart($c,'125cc-eyecatch.png');

// Category
$c=json_encode(['type'=>'horizontalBar','data'=>['labels'=>['Import','Sports','OldLook','Naked','Scrambler','MiniBike','Scooter51+','OrigScooter','Off-road','EV'],'datasets'=>[['data'=>[117.9,87.9,79.0,75.6,48.4,39.5,28.7,27.4,47.8,30.1],'backgroundColor'=>['#ef4444','#f59e0b','#8b5cf6','#3b82f6','#22c55e','#06b6d4','#64748b','#94a3b8','#f97316','#22d3ee'],'borderRadius'=>4]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>'125cc Category Avg Price','color'=>'#fff','font'=>['size'=>22]],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'right','font'=>['size'=>14],'formatter'=>'__FC__']],'scales'=>['x'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155']],'y'=>['ticks'=>['color'=>'#fff','font'=>['size'=>12]],'grid'=>['display'=>false]]]]]);
$c=str_replace('"__FC__"',"(v)=>v+'万'",$c);saveChart($c,'125cc-category.png');

// Sales
$c=json_encode(['type'=>'horizontalBar','data'=>['labels'=>['Monkey125','CT125','Dax125','SuperCub','CrossCub','Address','Lead125','Jog125','Dio110B','Dio110'],'datasets'=>[['data'=>[382,278,175,151,148,141,133,120,84,73],'backgroundColor'=>'#3b82f6','borderRadius'=>4]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>'125cc Sales Volume TOP10 (3mo)','color'=>'#fff','font'=>['size'=>22]],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'right','font'=>['size'=>14],'formatter'=>'__FS__']],'scales'=>['x'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155']],'y'=>['ticks'=>['color'=>'#fff','font'=>['size'=>12]],'grid'=>['display'=>false]]]]]);
$c=str_replace('"__FS__"',"(v)=>v+'units'",$c);saveChart($c,'125cc-sales.png');

// Speed
$c=json_encode(['type'=>'horizontalBar','data'=>['labels'=>['CubMD','PG-1','GN125','GyroX','WR125R','Swish','CRF125F','Dio110L','T100','Dio110'],'datasets'=>[['data'=>[37,37,38,39,40,41,42,42,42,42],'backgroundColor'=>'#22c55e','borderRadius'=>4]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>'125cc Fastest Selling (days)','color'=>'#fff','font'=>['size'=>22]],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'right','font'=>['size'=>14],'formatter'=>'__FD__']],'scales'=>['x'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155'],'min'=>30,'max'=>50],'y'=>['ticks'=>['color'=>'#fff','font'=>['size'=>12]],'grid'=>['display'=>false]]]]]);
$c=str_replace('"__FD__"',"(v)=>v+'d'",$c);saveChart($c,'125cc-speed.png');

// Honda Cub family
$c=json_encode(['type'=>'bar','data'=>['labels'=>['LittleCub','SuperCub110','CrossCub110','C125','CT125','Dax125','Monkey125'],'datasets'=>[['label'=>'Avg Price','data'=>[22.7,35.7,40.6,47.7,47.8,47.7,48.2],'backgroundColor'=>['#94a3b8','#ef4444','#f59e0b','#8b5cf6','#3b82f6','#22c55e','#06b6d4'],'borderRadius'=>8]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>'Honda Cub Family Price Comparison','color'=>'#fff','font'=>['size'=>22]],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'top','font'=>['size'=>14,'weight'=>'bold'],'formatter'=>'__FH__']],'scales'=>['y'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155']],'x'=>['ticks'=>['color'=>'#fff','font'=>['size'=>11]],'grid'=>['display'=>false]]]]]);
$c=str_replace('"__FH__"',"(v)=>v+'万'",$c);saveChart($c,'125cc-honda.png');

$body=str_replace('<!-- CHART:eyecatch -->','![125cc全車種比較](/storage/blog/125cc-eyecatch.png)',$body);
$body=str_replace('<!-- CHART:category -->','![カテゴリ別価格](/storage/blog/125cc-category.png)',$body);
$body=str_replace('<!-- CHART:sales -->','![売却台数TOP10](/storage/blog/125cc-sales.png)',$body);
$body=str_replace('<!-- CHART:speed -->','![売却日数TOP10](/storage/blog/125cc-speed.png)',$body);
$body=str_replace('<!-- CHART:honda -->','![ホンダカブファミリー比較](/storage/blog/125cc-honda.png)',$body);

$post=BlogPost::create(['author_id'=>2,'title'=>$title,'slug'=>'125cc-all-models-comparison-2026',
'body'=>$body,'eyecatch_image'=>'blog/125cc-eyecatch.png','status'=>'draft',
'meta_title'=>$title,'meta_description'=>$metaDesc,'og_image'=>'blog/125cc-eyecatch.png',
'series_id'=>2,'reading_time_minutes'=>15]);
echo "\n===== 125cc DONE =====\nID: {$post->id}\nTitle: {$title}\nBody: ".mb_strlen($body)." chars\n";
