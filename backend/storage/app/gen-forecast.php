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
■ 月別全体平均売却価格
- 2026年2月: ¥748,167（8,367台売却）
- 2026年3月: ¥746,646（13,402台売却）+60%の取引量で価格横ばい

■ 排気量帯別の月次変動（2月→3月）
- ~125cc: ¥461,272→¥485,392 (+5.2%) ★値上がり
- 126-250cc: ¥483,100→¥489,398 (+1.3%) 微増
- 251-400cc: ¥815,829→¥772,110 (-5.4%) ★値下がり
- 401-750cc: ¥635,004→¥588,713 (-7.3%) ★大きく値下がり
- 751cc~: ¥1,569,819→¥1,587,032 (+1.1%) 横ばい

■ 値上がりTOP10（2月→3月、8台以上売却）
1. YZF-R15: +29.7%（¥384,356→¥498,372）
2. VMAX 1200: +29.6%（¥873,380→¥1,132,193）
3. BMW G310R: +24.4%（¥434,000→¥539,917）
4. フォルツァZ: +23.4%（¥249,300→¥307,595）
5. ヘリテイジソフテイルクラシック: +20.7%
6. BMW G310GS: +16.9%
7. SV650X: +16.4%（¥668,463→¥778,375）
8. XV250ビラーゴ: +15.5%
9. DS400クラシック: +14.8%
10. クロスカブ50: +13.8%
→ 旧車・ビンテージ系（VMAX, ビラーゴ, DS400C）と輸入車（BMW G310系）が値上がり

■ 値下がりTOP10
1. YZF-R1: -28.8%（¥1,927,555→¥1,372,206）
2. CBR1000RR: -23.3%（¥1,272,658→¥976,358）
3. XL1200Cカスタム(ハーレー): -20.6%
4. トリシティ125: -16.3%
5. ソフテイルブレイクアウト114: -16.3%
6. マグザム: -15%
7. アドレスV125: -13.3%
8. CB125R: -12.2%
9. クレアスクーピー: -11.7%
10. BMW M1000R: -11.2%
→ SS/スーパースポーツ（R1, CBR1000RR）とハーレー高額車が大幅下落

■ カテゴリ別取引量の増加率（2月→3月）
- ツアラー: +134.6%（107→251台）★爆増
- 電動バイク: +100%
- ストリート: +96.7%
- 輸入車: +91.2%
- アドベンチャー: +91.3%
- スポーツ/レプリカ: +89.6%
- スクランブラー: +89.3%
- ミニバイク: +87.9%
→ 春のツーリングシーズンでツアラー・ADVが激増

■ 夏に向けての予測ポイント
- 春→夏は取引量がさらに増加（夏ボーナス効果）
- 125cc/原付は値上がり傾向継続（通勤需要+免許取得しやすい）
- 中型（251-750cc）は値下がり→買い時
- SS系は調整局面→今が買い時の可能性
- ハーレー高額車は値下がり傾向→夏ボーナスで狙い目
- 旧車プレミアムは上昇継続→買うなら早め
DATA;

echo "Calling API for forecast article...\n";
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

【テーマ】2026年夏のバイク相場はこうなる｜実データで読む値上がり・値下がり車種

【実データ】
{$data}

【執筆ルール】
1. JSON: {{"title":"…","body":"…(Markdown)","meta_description":"…(120文字以内)"}}
   ※JSONのbodyフィールド内のダブルクォートは必ずエスケープ（\"）すること
2. **5,000〜7,000文字**
3. 画像プレースホルダー5つ:
   - <!-- CHART:eyecatch -->
   - <!-- CHART:cc_trend --> — 排気量帯別の価格変動（棒グラフ）
   - <!-- CHART:gainers --> — 値上がりTOP10
   - <!-- CHART:losers --> — 値下がりTOP10
   - <!-- CHART:category_volume --> — カテゴリ別取引量変化

4. 構成:
   - リード文: 13万台のデータから夏の相場を読み解く
   - 全体概況: 取引量+60%で価格横ばい、春の出回りシーズン
   - 排気量帯別トレンド（125ccは値上がり、中型は値下がり、大型は横ばい）
   - 値上がりしている車種TOP10（旧車+輸入車がトレンド）
   - 値下がりしている車種TOP10（SS+ハーレーが狙い目）
   - カテゴリ別の需要分析（ツアラー+134%の爆増）
   - 夏ボーナスで狙うべきモデル5選
   - 「今買うべきか、秋まで待つべきか」の判断基準
   - まとめ: 排気量帯別の買い時チャート

5. 内部リンク:
   - [値下がりランキング詳細](/blog/best-deals-bikes-2026-05)
   - [250cc全車種比較](/blog/250cc-all-models-comparison-2026)
   - [400cc全車種比較](/blog/400cc-all-models-comparison-2026)
   - [中古バイク購入ガイド](/blog/used-bike-buying-guide-2026)
   - [中古バイクを探す](/bikes/search)
   - 各車種: /bikes/catalog/{slug}

6. ポータルの分析記事トーン、表2つ以上、2026年統一
PROMPT
    ]],
]);

if (!$response->successful()) { echo "API Error: ".$response->body()."\n"; exit(1); }
$content = $response->json('content.0.text');
echo "API OK. Length: ".strlen($content)."\n";

// Parse
$cleaned = preg_replace('/^```json\s*/m', '', $content);
$cleaned = preg_replace('/```\s*$/m', '', $cleaned);
$cleaned = str_replace(["\u{201C}","\u{201D}","\u{2018}","\u{2019}"], ['"','"',"'","'"], $cleaned);
if (preg_match('/\{[\s\S]*\}/u', $cleaned, $m)) {
    $article = json_decode($m[0], true);
    if (!$article && preg_match('/"body"\s*:\s*"([\s\S]*)",\s*"meta_description"/u', $m[0], $bm)) {
        preg_match('/"title"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $m[0], $tm);
        preg_match('/"meta_description"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $m[0], $dm);
        $article = ['title'=>$tm[1]??'','body'=>stripcslashes($bm[1]),'meta_description'=>$dm[1]??''];
    }
}
if (!$article||!isset($article['body'])) { echo "Parse fail\n"; file_put_contents(__DIR__.'/debug-fc.txt',$content); exit(1); }

$title = str_replace('2024年','2026年',$article['title']);
$body = str_replace('2024年','2026年',$article['body']);
$metaDesc = $article['meta_description'];
echo "Title: {$title}\nBody: ".mb_strlen($body)." chars\n";

function saveChart($json,$fn) {
    $url='https://quickchart.io/chart?c='.urlencode($json).'&w=1200&h=630&bkg=%231e293b&f=png';
    $img=@file_get_contents($url,false,stream_context_create(['http'=>['timeout'=>30]]));
    if($img&&strlen($img)>1000){Storage::disk('public')->put("blog/{$fn}",$img);echo "OK {$fn}\n";return true;}
    echo "FAIL {$fn}\n";return false;
}

// Eyecatch
$c=json_encode(['type'=>'bar','data'=>['labels'=>['~125cc','126-250','251-400','401-750','751cc~'],'datasets'=>[['label'=>'Feb','data'=>[46.1,48.3,81.6,63.5,157.0],'backgroundColor'=>'#3b82f6','borderRadius'=>6],['label'=>'Mar','data'=>[48.5,48.9,77.2,58.9,158.7],'backgroundColor'=>'#22c55e','borderRadius'=>6]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>['Summer 2026 Market Forecast','Price Trend by Displacement'],'color'=>'#fff','font'=>['size'=>26,'weight'=>'bold']],'legend'=>['labels'=>['color'=>'#e2e8f0']],'datalabels'=>['display'=>false]],'scales'=>['y'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155'],'title'=>['display'=>true,'text'=>'man-yen','color'=>'#94a3b8']],'x'=>['ticks'=>['color'=>'#fff'],'grid'=>['display'=>false]]]]]);
saveChart($c,'forecast-eyecatch.png');

// CC trend
$c=json_encode(['type'=>'bar','data'=>['labels'=>['~125cc','126-250cc','251-400cc','401-750cc','751cc~'],'datasets'=>[['data'=>[5.2,1.3,-5.4,-7.3,1.1],'backgroundColor'=>['#22c55e','#22c55e','#ef4444','#ef4444','#64748b'],'borderRadius'=>6]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>'Price Change by Displacement (Feb→Mar)','color'=>'#fff','font'=>['size'=>22]],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'top','font'=>['size'=>15,'weight'=>'bold'],'formatter'=>'__F1__']],'scales'=>['y'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155']],'x'=>['ticks'=>['color'=>'#fff','font'=>['size'=>13]],'grid'=>['display'=>false]]]]]);
$c=str_replace('"__F1__"',"(v)=>v+'%'",$c);
saveChart($c,'forecast-cc-trend.png');

// Gainers
$c=json_encode(['type'=>'horizontalBar','data'=>['labels'=>['YZF-R15','VMAX','G310R','ForzaZ','Heritage','G310GS','SV650X','Virago','DS400C','CrossCub50'],'datasets'=>[['data'=>[29.7,29.6,24.4,23.4,20.7,16.9,16.4,15.5,14.8,13.8],'backgroundColor'=>'#22c55e','borderRadius'=>4]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>'Price Gainers TOP10 (Feb→Mar %)','color'=>'#fff','font'=>['size'=>22]],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'right','font'=>['size'=>14],'formatter'=>'__F2__']],'scales'=>['x'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155']],'y'=>['ticks'=>['color'=>'#fff','font'=>['size'=>12]],'grid'=>['display'=>false]]]]]);
$c=str_replace('"__F2__"',"(v)=>'+'+v+'%'",$c);
saveChart($c,'forecast-gainers.png');

// Losers
$c=json_encode(['type'=>'horizontalBar','data'=>['labels'=>['YZF-R1','CBR1000RR','XL1200C','Tricity125','Breakout114','Majestam','AddressV125','CB125R','Scoopy','M1000R'],'datasets'=>[['data'=>[-28.8,-23.3,-20.6,-16.3,-16.3,-15.0,-13.3,-12.2,-11.7,-11.2],'backgroundColor'=>'#ef4444','borderRadius'=>4]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>'Price Losers TOP10 (Feb→Mar %)','color'=>'#fff','font'=>['size'=>22]],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'start','align'=>'left','font'=>['size'=>14],'formatter'=>'__F3__']],'scales'=>['x'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155']],'y'=>['ticks'=>['color'=>'#fff','font'=>['size'=>12]],'grid'=>['display'=>false]]]]]);
$c=str_replace('"__F3__"',"(v)=>v+'%'",$c);
saveChart($c,'forecast-losers.png');

// Category volume
$c=json_encode(['type'=>'horizontalBar','data'=>['labels'=>['Tourer','EV','Street','ADV','Import','Sport','Scrambler','MiniBike','Naked','American'],'datasets'=>[['data'=>[134.6,100,96.7,91.3,91.2,89.6,89.3,87.9,70.9,68.4],'backgroundColor'=>['#ef4444','#f59e0b','#f59e0b','#22c55e','#22c55e','#3b82f6','#3b82f6','#06b6d4','#64748b','#64748b'],'borderRadius'=>4]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>'Sales Volume Growth by Category (Feb→Mar)','color'=>'#fff','font'=>['size'=>20]],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'right','font'=>['size'=>13],'formatter'=>'__F4__']],'scales'=>['x'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155']],'y'=>['ticks'=>['color'=>'#fff','font'=>['size'=>12]],'grid'=>['display'=>false]]]]]);
$c=str_replace('"__F4__"',"(v)=>'+'+v+'%'",$c);
saveChart($c,'forecast-category.png');

$body = str_replace('<!-- CHART:eyecatch -->','![夏の相場予測](/storage/blog/forecast-eyecatch.png)',$body);
$body = str_replace('<!-- CHART:cc_trend -->','![排気量帯別変動](/storage/blog/forecast-cc-trend.png)',$body);
$body = str_replace('<!-- CHART:gainers -->','![値上がりTOP10](/storage/blog/forecast-gainers.png)',$body);
$body = str_replace('<!-- CHART:losers -->','![値下がりTOP10](/storage/blog/forecast-losers.png)',$body);
$body = str_replace('<!-- CHART:category_volume -->','![カテゴリ別取引量](/storage/blog/forecast-category.png)',$body);

$post = BlogPost::create([
    'author_id'=>2,'title'=>$title,'slug'=>'bike-market-forecast-summer-2026',
    'body'=>$body,'eyecatch_image'=>'blog/forecast-eyecatch.png','status'=>'draft',
    'meta_title'=>$title,'meta_description'=>$metaDesc,'og_image'=>'blog/forecast-eyecatch.png',
    'series_id'=>2,'reading_time_minutes'=>15,
]);
echo "\n===== Forecast DONE =====\nID: {$post->id}\nTitle: {$title}\nBody: ".mb_strlen($body)." chars\n";
