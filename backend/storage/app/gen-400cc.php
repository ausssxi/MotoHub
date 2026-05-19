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
■ 400ccクラス全体統計
- 在庫: 13,792台
- 在庫平均価格: ¥969,947
- 直近3ヶ月売却: 1,703台, 売却平均¥825,794
- 対象車種数: 161モデル（在庫10台以上）
- 250ccとの比較: 250cc在庫35,545台/平均¥56万 → 400ccは在庫3分の1、価格は1.7倍

■ カテゴリ別
| カテゴリ | 在庫 | 平均価格 |
|---------|------|---------|
| オールドルック | 3,400台 | ¥851,619 |
| ネイキッド | 3,036台 | ¥1,449,572 |
| アメリカン | 2,776台 | ¥836,197 |
| スポーツ/レプリカ | 2,049台 | ¥770,299 |
| 輸入車 | 829台 | ¥738,038 |
| アドベンチャー | 359台 | ¥740,526 |
| スクーター | 212台 | ¥854,230 |
| オフロード | 206台 | ¥1,153,280 |
| スクランブラー | 172台 | ¥820,000 |

■ 在庫台数TOP15車種
1. SR400: 1,004台 ¥830,601（オールドルック）★王者
2. GB350: 855台 ¥616,408（オールドルック）★最も手頃
3. ドラッグスター400: 748台 ¥791,638（アメリカン）
4. エリミネーター400: 514台 ¥903,139（アメリカン）★新型
5. Ninja 400: 417台 ¥694,961（スポーツ）
6. GB350S: 410台 ¥652,001（オールドルック）
7. YZF-R25(392cc): 401台 ¥566,535（スポーツ）
8. エリミネーター400SE: 400台 ¥972,587（アメリカン）
9. ドラッグスター400クラシック: 397台 ¥814,737（アメリカン）
10. CBR400R: 333台 ¥727,674（スポーツ）
11. Ninja400(旧型): 314台 ¥685,584（スポーツ）
12. Z400: 306台 ¥709,040（ネイキッド）
13. CB400SF VTEC Revo: 220台 ¥1,256,127（ネイキッド）★伝説のCB
14. MT-03: 197台 ¥618,924（ネイキッド）
15. CB400F(旧車): 196台 ¥2,802,183（オールドルック）★旧車プレミアム

■ 旧車プレミアム注目
- CBX400F: 111台 平均¥4,413,017（最高¥19,980,000！）
- CB400F: 196台 平均¥2,802,183
- Zephyr400: 141台 平均¥1,436,539
- ZRX400: 185台 平均¥1,143,380

■ 売却日数TOP10（最速）
1. CB400SF(不明型): 37日（10台）
2. RC390: 39日（17台, ¥669,000）
3. CB400SS: 41日（29台, ¥567,962）
4. 390デューク: 41日（15台, ¥501,540）
5. XJR400R: 41日（14台, ¥828,471）
6. XJR400: 41日（18台, ¥959,561）
7. NX400: 41日（11台, ¥943,264）
8. メテオ350ステラ: 42日（16台）
9. CB400SF: 42日（17台）
10. X350: 42日（29台, ¥651,786）

■ 売却台数TOP10
1. SR400: 162台（44日, ¥748,820）
2. GB350: 112台（45日, ¥602,367）
3. ドラッグスター400: 103台（43日, ¥747,450）
4. CB400SF VTEC Revo: 94台（44日, ¥1,129,671）
5. CBR400R: 85台（44日, ¥708,685）
6. GB350S: 81台（44日, ¥616,985）
7. 400X: 78台（44日, ¥728,596）
8. DS400クラシック: 63台（44日, ¥764,216）
9. MT-03: 52台（45日, ¥581,863）
10. CB400SBボルドール Revo: 46台（45日, ¥998,852）

■ 月次値動き（2月→3月）
値下がり:
- CBX400F: -33.8%（¥667万→¥442万）
- CB400SF: -25.3%
- 400X: -7.6%
値上がり:
- G310R(BMW): +24.4%
- G310GS(BMW): +16.9%
- 390デューク(KTM): +15.8%
- DS400クラシック: +14.8%

■ 250ccとの維持費差ポイント
- 400ccは車検あり（2年ごと約5〜8万円）
- 自賠責・重量税が250ccより高い
- ただし高速道路での余裕は圧倒的
- 保険は排気量よりバイクの価格で決まる部分が大きい
DATA;

echo "Calling Anthropic API for 400cc article...\n";
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

【テーマ】400ccバイク全車種を実売データで徹底比較【2026年版完全ガイド】

【実データ】
{$data}

【参考: 同シリーズの250cc記事トーン】
「250ccクラスは、バイク市場で最も激戦区のカテゴリです。2026年現在、なんと289モデルがひしめく中から最適な1台を見つけるのは至難の業。そこで、実際の在庫35,545台と直近3ヶ月の売却4,415台のリアルデータを徹底分析。」

【執筆ルール】
1. JSON形式で返す: {{"title":"…","body":"…(Markdown)","meta_description":"…(120文字以内)"}}
2. bodyはMarkdown。H2/H3で構成
3. **5,000〜7,000文字**（15分読了の長編記事）
4. データの数字は正確に使用
5. 以下の5つの画像プレースホルダーを入れる:
   - <!-- CHART:eyecatch --> — アイキャッチ（記事冒頭）
   - <!-- CHART:category --> — カテゴリ別平均価格
   - <!-- CHART:sales --> — 売却台数TOP10
   - <!-- CHART:speed --> — 売却日数TOP10
   - <!-- CHART:premium --> — 旧車プレミアムの価格（CBX400F/CB400F/Zephyr/ZRX）

6. 構成:
   - リード文: MotoHubの13,792台のデータから400ccを徹底分析
   - 400ccクラスの市場概況（在庫/平均価格/250ccとの比較）
   - カテゴリ別ガイド（オールドルック/ネイキッド/アメリカン/スポーツ/輸入車 各3〜5車種詳細）
   - 旧車プレミアムの世界（CBX400F ¥441万、CB400F ¥280万）
   - 総合ランキング: コスパ最強TOP5
   - 総合ランキング: 最速売却TOP5
   - 総合ランキング: 売却台数TOP5（人気）
   - 値動き注目: BMW/KTMの輸入車が値上がり
   - 250ccとの維持費差（車検の有無）
   - 目的別おすすめ（通勤/ツーリング/初心者/カフェレーサー）
   - まとめ + 主要車種比較テーブル

7. 内部リンク多数:
   - [SR400の在庫を見る](/bikes/catalog/sr400)
   - [GB350の在庫を見る](/bikes/catalog/gb350)
   - [Ninja 400の在庫を見る](/bikes/catalog/ninja-400)
   - [CBR400Rの在庫を見る](/bikes/catalog/cbr400r)
   - [400ccバイクを探す](/bikes/search?displacement_min=301&displacement_max=400)
   - [250cc全車種比較記事](/blog/250cc-all-models-comparison-2026)
   - その他: gb350s, drag-star-400, z400, 400x, mt-03, cb400super-four-vtec-revo, burgman-400 等

8. ポータルサイトの分析記事としての信頼感あるトーン
9. 表を3つ以上（カテゴリ比較、旧車プレミアム、全車種比較テーブル）
10. 年は2026年で統一
PROMPT
    ]],
]);

if (!$response->successful()) {
    echo "API Error: " . $response->body() . "\n";
    exit(1);
}

$content = $response->json('content.0.text');
echo "API response received. Length: " . strlen($content) . "\n";

if (preg_match('/\{[\s\S]*\}/u', $content, $m)) {
    $article = json_decode($m[0], true);
} else {
    echo "Failed to parse JSON\n";
    file_put_contents(__DIR__ . '/debug-400cc.txt', $content);
    exit(1);
}

if (!$article || !isset($article['body'])) {
    echo "Missing body\n";
    file_put_contents(__DIR__ . '/debug-400cc.txt', $content);
    exit(1);
}

$title = $article['title'];
$body = $article['body'];
$metaDesc = $article['meta_description'];
$body = str_replace('2024年', '2026年', $body);
$title = str_replace('2024年', '2026年', $title);

echo "Title: {$title}\nBody: " . mb_strlen($body) . " chars\n";

// ===== Charts =====
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

// 1. Eyecatch
$c = json_encode(['type'=>'bar','data'=>['labels'=>['SR400','GB350','DS400','Elim400','Ninja400','GB350S','CBR400R','Z400','MT-03'],'datasets'=>[['data'=>[83.1,61.6,79.2,90.3,69.5,65.2,72.8,70.9,61.9],'backgroundColor'=>['#f59e0b','#22c55e','#3b82f6','#8b5cf6','#ef4444','#22c55e','#ef4444','#06b6d4','#06b6d4'],'borderRadius'=>8]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>['400cc All Models Price Guide 2026','13,792 Units / 161 Models'],'color'=>'#fff','font'=>['size'=>26,'weight'=>'bold']],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'top','font'=>['size'=>13,'weight'=>'bold'],'formatter'=>'__F1__']],'scales'=>['y'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155'],'title'=>['display'=>true,'text'=>'man-yen','color'=>'#94a3b8']],'x'=>['ticks'=>['color'=>'#fff','font'=>['size'=>11]],'grid'=>['display'=>false]]]]]);
$c = str_replace('"__F1__"', "(v)=>v+'万'", $c);
saveChart($c, '400cc-eyecatch.png');

// 2. Category
$c = json_encode(['type'=>'horizontalBar','data'=>['labels'=>['Naked','Off-road','Eliminator','Scooter','OldLook','American','Sports','ADV','Import'],'datasets'=>[['data'=>[144.9,115.3,90.3,85.4,85.2,83.6,77.0,74.1,73.8],'backgroundColor'=>['#ef4444','#22c55e','#8b5cf6','#64748b','#f59e0b','#3b82f6','#06b6d4','#f97316','#94a3b8'],'borderRadius'=>6]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>'400cc Category Avg Price','color'=>'#fff','font'=>['size'=>22]],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'right','font'=>['size'=>14],'formatter'=>'__F2__']],'scales'=>['x'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155'],'title'=>['display'=>true,'text'=>'man-yen','color'=>'#94a3b8']],'y'=>['ticks'=>['color'=>'#fff','font'=>['size'=>13]],'grid'=>['display'=>false]]]]]);
$c = str_replace('"__F2__"', "(v)=>v+'万'", $c);
saveChart($c, '400cc-category.png');

// 3. Sales TOP10
$c = json_encode(['type'=>'horizontalBar','data'=>['labels'=>['SR400','GB350','DS400','CB400SF Revo','CBR400R','GB350S','400X','DS400C','MT-03','CB400SB'],'datasets'=>[['data'=>[162,112,103,94,85,81,78,63,52,46],'backgroundColor'=>'#3b82f6','borderRadius'=>4]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>'400cc Sales Volume TOP10 (3mo)','color'=>'#fff','font'=>['size'=>22]],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'right','font'=>['size'=>14],'formatter'=>'__F3__']],'scales'=>['x'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155']],'y'=>['ticks'=>['color'=>'#fff','font'=>['size'=>12]],'grid'=>['display'=>false]]]]]);
$c = str_replace('"__F3__"', "(v)=>v+'units'", $c);
saveChart($c, '400cc-sales.png');

// 4. Speed TOP10
$c = json_encode(['type'=>'horizontalBar','data'=>['labels'=>['CB400SF','RC390','CB400SS','390Duke','XJR400R','XJR400','NX400','Meteor350','CB400SF4','X350'],'datasets'=>[['data'=>[37,39,41,41,41,41,41,42,42,42],'backgroundColor'=>'#22c55e','borderRadius'=>4]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>'400cc Fastest Selling TOP10 (days)','color'=>'#fff','font'=>['size'=>22]],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'right','font'=>['size'=>14],'formatter'=>'__F4__']],'scales'=>['x'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155'],'min'=>30,'max'=>50],'y'=>['ticks'=>['color'=>'#fff','font'=>['size'=>12]],'grid'=>['display'=>false]]]]]);
$c = str_replace('"__F4__"', "(v)=>v+'days'", $c);
saveChart($c, '400cc-speed.png');

// 5. Premium
$c = json_encode(['type'=>'bar','data'=>['labels'=>['CBX400F','CB400F','Zephyr400','ZRX400','CB400SF Revo','XJR400R'],'datasets'=>[['data'=>[441.3,280.2,143.7,114.3,125.6,87.6],'backgroundColor'=>['#ef4444','#f59e0b','#8b5cf6','#3b82f6','#06b6d4','#64748b'],'borderRadius'=>8]]],'options'=>['plugins'=>['title'=>['display'=>true,'text'=>'400cc Legend Premium Models','color'=>'#fff','font'=>['size'=>22]],'legend'=>['display'=>false],'datalabels'=>['display'=>true,'color'=>'#fff','anchor'=>'end','align'=>'top','font'=>['size'=>14,'weight'=>'bold'],'formatter'=>'__F5__']],'scales'=>['y'=>['ticks'=>['color'=>'#94a3b8'],'grid'=>['color'=>'#334155'],'title'=>['display'=>true,'text'=>'man-yen','color'=>'#94a3b8']],'x'=>['ticks'=>['color'=>'#fff','font'=>['size'=>11]],'grid'=>['display'=>false]]]]]);
$c = str_replace('"__F5__"', "(v)=>v+'万'", $c);
saveChart($c, '400cc-premium.png');

// ===== Insert images =====
$body = str_replace('<!-- CHART:eyecatch -->', '![400cc全車種比較](/storage/blog/400cc-eyecatch.png)', $body);
$body = str_replace('<!-- CHART:category -->', '![カテゴリ別平均価格](/storage/blog/400cc-category.png)', $body);
$body = str_replace('<!-- CHART:sales -->', '![売却台数TOP10](/storage/blog/400cc-sales.png)', $body);
$body = str_replace('<!-- CHART:speed -->', '![売却日数TOP10](/storage/blog/400cc-speed.png)', $body);
$body = str_replace('<!-- CHART:premium -->', '![旧車プレミアム価格](/storage/blog/400cc-premium.png)', $body);

// ===== Save =====
$post = BlogPost::create([
    'author_id' => 2,
    'title' => $title,
    'slug' => '400cc-all-models-comparison-2026',
    'body' => $body,
    'eyecatch_image' => 'blog/400cc-eyecatch.png',
    'status' => 'draft',
    'meta_title' => $title,
    'meta_description' => $metaDesc,
    'og_image' => 'blog/400cc-eyecatch.png',
    'series_id' => 2,
    'reading_time_minutes' => 15,
]);

echo "\n===== 400cc DONE =====\n";
echo "BlogPost ID: {$post->id}\nSlug: 400cc-all-models-comparison-2026\nTitle: {$title}\nBody: " . mb_strlen($body) . " chars\n";
