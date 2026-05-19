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
■ 初心者向けバイクの定義（MotoHubデータ基準）
- 中古平均価格が手頃（125cc: ~50万 / 250cc: ~60万 / 400cc: ~100万）
- 在庫が豊富（30台以上 = 選びやすい）
- 回転速度が速い（売却日数が短い = 需要がある = 人気）
- 独自スコア = 在庫数 + 価格の手頃さ + 売却スピード

■ 125ccクラス 初心者おすすめTOP10
1. スーパーカブ110: stock=1,548 avg¥357,014 min¥130,000 45日 score189
2. クロスカブ110: stock=1,263 avg¥406,046 min¥189,100 45日 score156
3. アドレス125: stock=938 avg¥272,707 min¥95,000 44日 score139
4. モンキー125: stock=1,161 avg¥482,370 min¥150,000 45日 score138
5. リード125: stock=942 avg¥352,074 min¥208,000 44日 score131
6. CT125ハンターカブ: stock=923 avg¥478,272 min¥322,600 45日 score115
7. Dio110ベーシック: stock=631 avg¥260,007 min¥195,000 43日 score111
8. アヴェニス125: stock=615 avg¥280,012 min¥199,899 43日 score108
9. PCX125: stock=536 avg¥382,441 min¥159,000 37日 score101 ★最速売却
10. Dio110 Lite: stock=484 avg¥260,594 min¥230,000 42日 score98

■ 250ccクラス 初心者おすすめTOP10
1. レブル250: stock=1,350 avg¥594,481 min¥242,000 45日 score146 ★圧倒的人気
2. NMAX: stock=990 avg¥371,212 min¥149,000 44日 score134
3. PCX160: stock=993 avg¥477,624 min¥341,200 43日 score126
4. Axis Z: stock=652 avg¥267,453 min¥99,000 42日 score115 ★最安
5. GSX250R: stock=938 avg¥520,322 min¥258,000 45日 score112
6. ダンク: stock=556 avg¥230,593 min¥90,000 45日 score103
7. ズーマー: stock=534 avg¥248,801 min¥87,000 45日 score99
8. ジャイロキャノピー: stock=551 avg¥330,627 min¥118,000 43日 score96
9. タクト: stock=437 avg¥180,401 min¥40,000 45日 score96
10. ADV160: stock=431 avg¥539,122 43日

■ 400ccクラス 初心者おすすめTOP7
1. SR400: stock=1,004 avg¥830,601 min¥350,000 44日 score112
2. GB350: stock=855 avg¥616,408 min¥440,000 45日 score96 ★コスパ◎
3. ドラッグスター400: stock=748 avg¥791,638 min¥105,000 43日 score89
4. YZF-R25(392cc): stock=401 avg¥566,535 min¥307,100 37日 score69 ★最速売却
5. GB350S: stock=410 avg¥652,001 min¥458,000 44日 score53
6. エリミネーター400: stock=514 avg¥903,139 min¥250,000 50日
7. Ninja 400: stock=417 avg¥694,961 min¥350,000 50日

■ 初心者が避けるべき車種の特徴
- 旧車プレミアム（Z1 ¥572万, CBX400F ¥441万, Z400FX ¥400万）
- 理由: 部品入手困難、整備に専門知識必要、盗難リスク高い
- ハーレー系（ブレイクアウト114 ¥301万、ファットボーイ114 ¥289万）
- 理由: 維持費高い、取り回し重い

■ 全体統計
- MotoHub全在庫: 138,345台
- 全車種: 4,569モデル
DATA;

echo "Calling API for beginners article...\n";
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

【テーマ】初心者におすすめのバイク20選｜13万台の販売データで見る"売れてる×値落ちしにくい"モデル【2026年版】

【実データ】
{$data}

【執筆ルール】
1. JSON: {{"title":"…","body":"…(Markdown)","meta_description":"…(120文字以内)"}}
2. **5,000〜7,000文字**
3. 画像プレースホルダー5つ:
   - <!-- CHART:eyecatch --> — アイキャッチ（冒頭）
   - <!-- CHART:125cc --> — 125cc初心者向けTOP5（横棒・スコア）
   - <!-- CHART:250cc --> — 250cc初心者向けTOP5（横棒・スコア）
   - <!-- CHART:400cc --> — 400cc初心者向けTOP5（横棒）
   - <!-- CHART:avoid --> — 避けるべき車種の価格（旧車プレミアム）

4. 構成:
   - リード文: 4,569車種からデータで「初心者に最適な20台」を厳選
   - 「初心者におすすめ」の定義をデータで再定義（在庫数×手頃さ×回転速度のスコアリング）
   - 125ccクラスTOP7（各車種: 価格帯・特徴・おすすめポイント・注意点）
   - 250ccクラスTOP7（同上）
   - 400ccクラスTOP6（同上、車検の話も）
   - 初心者が避けるべきバイクの特徴（旧車プレミアム、ハーレー系、理由を明確に）
   - 目的別チャート（通勤→PCX/Dio、ツーリング→レブル250/GB350、街乗り→モンキー/カブ）
   - まとめ: 購入チェックリスト

5. 内部リンク:
   - [レブル250の在庫を見る](/bikes/catalog/rebel-250)
   - [GB350の在庫を見る](/bikes/catalog/gb350)
   - [PCX125の在庫を見る](/bikes/catalog/pcx)
   - [SR400の在庫を見る](/bikes/catalog/sr400)
   - [250cc全車種比較](/blog/250cc-all-models-comparison-2026)
   - [400cc全車種比較](/blog/400cc-all-models-comparison-2026)
   - [中古バイク購入ガイド](/blog/used-bike-buying-guide-2026)
   - [125ccバイクを探す](/bikes/search?displacement_min=51&displacement_max=125)
   - その他: super-cub-110, cross-cub-110, monkey-125, ct125, nmax, gsx250r, ninja-400 等

6. ポータルサイトの分析記事トーン
7. 表を2つ以上（排気量別比較、全20台一覧テーブル）
8. 2026年統一
PROMPT
    ]],
]);

if (!$response->successful()) { echo "API Error: " . $response->body() . "\n"; exit(1); }

$content = $response->json('content.0.text');
echo "API OK. Length: " . strlen($content) . "\n";

// Remove markdown code fence if present
$cleaned = $content;
$cleaned = preg_replace('/^```json\s*/m', '', $cleaned);
$cleaned = preg_replace('/```\s*$/m', '', $cleaned);
// Fix smart quotes
$cleaned = str_replace(["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}"], ['"', '"', "'", "'"], $cleaned);

if (preg_match('/\{[\s\S]*\}/u', $cleaned, $m)) {
    $article = json_decode($m[0], true);
    if (!$article) {
        // Try fixing escaped quotes in body
        $raw = $m[0];
        // Extract title
        preg_match('/"title"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $raw, $tm);
        preg_match('/"meta_description"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $raw, $dm);
        // Extract body between "body": " and the closing
        if (preg_match('/"body"\s*:\s*"([\s\S]*)",\s*"meta_description"/u', $raw, $bm)) {
            $article = [
                'title' => $tm[1] ?? 'Untitled',
                'body' => stripcslashes($bm[1]),
                'meta_description' => $dm[1] ?? '',
            ];
        }
    }
}
if (!$article||!isset($article['body'])) {
    echo "Parse failed. Saving debug.\n";
    file_put_contents(__DIR__.'/debug-begin.txt',$content);
    exit(1);
}

$title = str_replace('2024年','2026年',$article['title']);
$body = str_replace('2024年','2026年',$article['body']);
$metaDesc = $article['meta_description'];
echo "Title: {$title}\nBody: " . mb_strlen($body) . " chars\n";

function saveChart($json, $fn) {
    $url = 'https://quickchart.io/chart?c=' . urlencode($json) . '&w=1200&h=630&bkg=%231e293b&f=png';
    $img = @file_get_contents($url, false, stream_context_create(['http'=>['timeout'=>30]]));
    if ($img && strlen($img) > 1000) { Storage::disk('public')->put("blog/{$fn}", $img); echo "OK {$fn}\n"; return true; }
    echo "FAIL {$fn}\n"; return false;
}

// Eyecatch
$c = json_encode(['type'=>'bar','data'=>['labels'=>['SuperCub','Rebel250','GB350','PCX125','NMAX','GSX250R','SR400','Monkey125'],'datasets'=>[['data'=>[35.7,59.4,61.6,38.2,37.1,52.0,83.1,48.2],'backgroundColor'=>['#22c55e','#3b82f6','#f59e0b','#06b6d4','#64748b','#ef4444','#8b5cf6','#f97316'],'borderRadius'=>8]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>['Best Bikes for Beginners 2026','Data-Driven Top 20 Picks'],'color'=>'#fff','font'=>['size'=>26,'weight'=>'bold']],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'top','font'=>['size'=>13,'weight'=>'bold'],'formatter'=>'__F__']],'scales'=>['y'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155']],'x'=>['ticks'=>['color'=>'#fff','font'=>['size'=>11]],'grid'=>['display'=>false]]]]]);
$c=str_replace('"__F__"',"(v)=>v+'万'",$c);
saveChart($c,'beginners-eyecatch.png');

// 125cc
$c = json_encode(['type'=>'horizontalBar','data'=>['labels'=>['SuperCub110','CrossCub110','Address125','Monkey125','Lead125','CT125','Dio110','Avenus125'],'datasets'=>[['data'=>[189,156,139,138,131,115,111,108],'backgroundColor'=>'#22c55e','borderRadius'=>4]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>'125cc Beginner Score Ranking','color'=>'#fff','font'=>['size'=>22]],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'right','font'=>['size'=>14]]],'scales'=>['x'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155']],'y'=>['ticks'=>['color'=>'#fff','font'=>['size'=>12]],'grid'=>['display'=>false]]]]]);
saveChart($c,'beginners-125cc.png');

// 250cc
$c = json_encode(['type'=>'horizontalBar','data'=>['labels'=>['Rebel250','NMAX','PCX160','AxisZ','GSX250R','Dunk','Zoomer'],'datasets'=>[['data'=>[146,134,126,115,112,103,99],'backgroundColor'=>'#3b82f6','borderRadius'=>4]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>'250cc Beginner Score Ranking','color'=>'#fff','font'=>['size'=>22]],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'right','font'=>['size'=>14]]],'scales'=>['x'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155']],'y'=>['ticks'=>['color'=>'#fff','font'=>['size'=>12]],'grid'=>['display'=>false]]]]]);
saveChart($c,'beginners-250cc.png');

// 400cc
$c = json_encode(['type'=>'horizontalBar','data'=>['labels'=>['SR400','GB350','DragStar400','YZF-R25','GB350S','Eliminator400'],'datasets'=>[['data'=>[112,96,89,69,53,51],'backgroundColor'=>'#f59e0b','borderRadius'=>4]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>'400cc Beginner Score Ranking','color'=>'#fff','font'=>['size'=>22]],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'right','font'=>['size'=>14]]],'scales'=>['x'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155']],'y'=>['ticks'=>['color'=>'#fff','font'=>['size'=>13]],'grid'=>['display'=>false]]]]]);
saveChart($c,'beginners-400cc.png');

// Avoid
$c = json_encode(['type'=>'bar','data'=>['labels'=>['Z1','CBX400F','Z400FX','CBR400F','KH400','Breakout114'],'datasets'=>[['data'=>[572,441,400,350,323,301],'backgroundColor'=>'#ef4444','borderRadius'=>6]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>'Bikes Beginners Should Avoid (man-yen)','color'=>'#fff','font'=>['size'=>20]],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'top','font'=>['size'=>14,'weight'=>'bold'],'formatter'=>'__FA__']],'scales'=>['y'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155']],'x'=>['ticks'=>['color'=>'#fff','font'=>['size'=>11]],'grid'=>['display'=>false]]]]]);
$c=str_replace('"__FA__"',"(v)=>v+'万'",$c);
saveChart($c,'beginners-avoid.png');

$body = str_replace('<!-- CHART:eyecatch -->','![初心者おすすめバイク](/storage/blog/beginners-eyecatch.png)',$body);
$body = str_replace('<!-- CHART:125cc -->','![125cc初心者ランキング](/storage/blog/beginners-125cc.png)',$body);
$body = str_replace('<!-- CHART:250cc -->','![250cc初心者ランキング](/storage/blog/beginners-250cc.png)',$body);
$body = str_replace('<!-- CHART:400cc -->','![400cc初心者ランキング](/storage/blog/beginners-400cc.png)',$body);
$body = str_replace('<!-- CHART:avoid -->','![避けるべき車種](/storage/blog/beginners-avoid.png)',$body);

$post = BlogPost::create([
    'author_id'=>2,'title'=>$title,'slug'=>'best-bikes-for-beginners-2026',
    'body'=>$body,'eyecatch_image'=>'blog/beginners-eyecatch.png','status'=>'draft',
    'meta_title'=>$title,'meta_description'=>$metaDesc,'og_image'=>'blog/beginners-eyecatch.png',
    'series_id'=>2,'reading_time_minutes'=>15,
]);
echo "\n===== Beginners DONE =====\nBlogPost ID: {$post->id}\nTitle: {$title}\nBody: ".mb_strlen($body)." chars\n";
