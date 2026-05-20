---
title: "Laravel + Anthropic API + QuickChartで、DBデータからSEO記事を全自動生成するPHPスクリプトを書いた"
emoji: "🤖"
type: "tech"
topics: ["laravel", "anthropic", "claude", "php", "seo"]
published: false
---

## はじめに

中古バイク22万台のデータを持つポータルサイト [MotoHub](https://motohub.jp) を運営しています。DAU 1,000を目指す中で、データ駆動のSEO戦略としてブログ記事の量産が必要になりました。

しかし問題は、「排気量帯別の市場分析」「維持費比較」といったデータ重視の記事を手作業で書くと、DBからSQLを叩いてデータを集め、グラフを作り、文章を書いて……と1記事あたり3〜4時間かかること。

そこで、**DBクエリ → データ整形 → Claude API → グラフ画像生成 → DB保存**を1スクリプトで完結させる仕組みを作りました。本記事ではその実装を公開します。

## 全体アーキテクチャ

スクリプトは7つのフェーズで構成されています。

1. **Phase 1: DBからデータ収集** — 排気量帯別統計、カテゴリ別在庫、人気車種、売却スピード、走行距離×価格、メーカーシェアなど6系統のクエリを実行
2. **Phase 2: データを自然言語に整形** — 収集データをテーブル形式・ラベル付きテキストに変換し、APIに渡す文字列を構築
3. **Phase 3: Anthropic APIでMarkdown記事生成** — プロンプトにデータを埋め込み、JSON形式でtitle/body/meta_descriptionを取得
4. **Phase 4: QuickChart APIでグラフ画像生成** — Chart.jsの設定JSONをURL経由で送り、PNG画像を取得・保存
5. **Phase 5: プレースホルダー置換** — 記事本文中の `<!-- CHART:xxx -->` を画像パスに差し替え
6. **Phase 6: バナー追加** — ブログ村などの外部バナーを末尾に追加
7. **Phase 7: DB保存** — `BlogPost` モデルにdraftとして保存

1スクリプト実行で「データ収集からブログ記事のDB保存まで」が完了します。

## Phase 1: DBからデータ収集

まずはLaravelアプリケーションをブートストラップします。Artisanコマンドではなくスタンドアロンスクリプトとして動かすため、手動でカーネルを起動します。

```php
<?php
require __DIR__ . '/../../vendor/autoload.php';
$app = require_once __DIR__ . '/../../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\BlogPost;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
```

:::message
`storage/app/` 配下にスクリプトを置き、`php storage/app/gen-bigbike.php` で実行しています。Artisanコマンド化しなかったのは、記事テーマごとにスクリプトをコピーして微調整する運用が楽だったためです。
:::

排気量帯ごとの在庫数・平均価格・売却データを取得するクエリがこちら。

```php
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

    // 直近3ヶ月の売却データも同様に取得
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
}
```

このほかに以下のクエリも実行しています。

- **カテゴリ別在庫**（ネイキッド、スポーツ等）
- **人気車種TOP20**（在庫15台以上の車種を在庫順にソート）
- **売却スピードTOP10**（掲載から売却までの平均日数）
- **走行距離別の平均価格**（CASE式で距離帯を分類）
- **メーカー別シェア**（在庫20台以上）
- **価格帯分布**（30万未満〜200万超を7段階に分類）

走行距離×価格の集計は、CASEで距離帯をバケット化するのがポイントです。

```php
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
    ->groupByRaw("CASE ... END") // 同じCASE式
    ->orderByRaw("MIN(l.mileage)")
    ->get();
```

## Phase 2: データを自然言語に整形

収集した生データをそのままAPIに渡しても良い結果は得られません。**テーブル形式やラベル付きで構造化する**と、LLMの出力精度が格段に上がります。

```php
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

// ... 人気車種、売却スピード、走行距離別、メーカーシェア等も同様
```

整形のコツは以下の3つです。

1. **セクション見出しに `■` を使う** — LLMがデータの区切りを認識しやすい
2. **テーブルはMarkdown形式** — 表形式のデータはLLMの理解精度が高い
3. **数値にはラベルを付ける** — 「在庫{N}台」「avg¥{N}」のように単位を明示

さらに、DB由来のデータだけでなく**一般的な知識データもハードコード**で追加しています。維持費の目安テーブルや「思ったより安い/高いポイント」など、記事の説得力を上げるための補助データです。

```php
$dataLines[] = "\n■ 排気量帯別 年間維持費の目安（一般的な相場）";
$dataLines[] = "| 項目 | 250cc | 400cc | 650cc | 1000cc超 |";
$dataLines[] = "|------|-------|-------|-------|---------|";
$dataLines[] = "| 車検（2年ごと）| なし | 約5~8万 | 約5~8万 | 約6~10万 |";
$dataLines[] = "| 任意保険（26歳以上/6等級）| 約3~5万 | 約3~6万 | 約4~7万 | 約5~10万 |";
// ...
```

最終的にデータ文字列は数KB程度になります。

## Phase 3: プロンプト設計が肝

ここが一番重要なフェーズです。Anthropic APIに渡すプロンプトの設計で記事の品質が決まります。

```php
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
あなたはバイク市場に精通したデータジャーナリストです。
以下の実データに基づき、Google Discover向けの長編ブログ記事を書いてください。

【テーマ】大型バイクの維持費は年間いくら？排気量別の実コストを
MotoHubデータで徹底比較【2026年版】

【実データ】
{$data}

【執筆ルール】
1. JSON形式で返す: {"title":"…","body":"…(Markdown)","meta_description":"…(120文字以内)"}
   ※bodyフィールド内のダブルクォートは\"でエスケープ
2. bodyはMarkdown。H2/H3で構成
3. 5,000〜7,000文字（15分読了の長編記事）
4. データの数字は正確に使用
5. 以下の5つの画像プレースホルダーを入れる:
   - <!-- CHART:eyecatch --> — アイキャッチ（記事冒頭）
   - <!-- CHART:annual_cost --> — 排気量帯別の年間維持費比較
   - <!-- CHART:price_class --> — 排気量帯別の中古平均価格比較
   - <!-- CHART:mileage_price --> — 走行距離別の平均価格
   - <!-- CHART:cost_breakdown --> — 維持費の内訳（400cc vs 1000cc）
PROMPT
    ]],
]);
```

プロンプト設計で意識したポイントを解説します。

### JSON出力を強制する

`JSON形式で返す` と明示し、キー構造まで指定します。Claude Sonnetはこの指示にかなり忠実に従ってくれます。ただし後述のように、JSONパースに失敗するケースもあるのでフォールバックは必須です。

### 画像プレースホルダー

記事本文中に `<!-- CHART:xxx -->` というHTMLコメントを埋め込むよう指示します。Phase 5でこれを実際の画像パスに置換するため、LLMは画像の配置場所だけ決めれば良い設計です。

### トーン参考文を渡す

同じシリーズの既存記事の冒頭を「参考トーン」として渡しています。

```
【参考: 同シリーズの250cc記事トーン】
「250ccクラスは、バイク市場で最も激戦区のカテゴリです。
2026年現在、なんと289モデルがひしめく中から最適な1台を見つけるのは至難の業。」
```

これにより、シリーズ全体で文体が統一されます。

### 内部リンクを指示する

SEO目的で、関連記事や検索ページへの内部リンクをプロンプト内で明示的に指示します。

```
7. 内部リンク多数:
   - [250cc全車種比較](/blog/250cc-all-models-comparison-2026)
   - [大型バイクを探す](/bikes/search?displacement_min=401)
   - 車種ページ例: /bikes/catalog/{slug}
```

LLMがこのURLパターンを使って記事内にリンクを散りばめてくれるので、手動でリンクを挿入する手間がなくなります。

## Phase 4: QuickChart APIで画像生成

ここが技術的に一番面白いパートです。[QuickChart](https://quickchart.io) はChart.jsの設定JSONをURLパラメータとして受け取り、PNG/SVG画像を返してくれるAPIです。

### 基本的な使い方

```php
function saveChart($json, $filename) {
    $url = 'https://quickchart.io/chart?c=' . urlencode($json)
         . '&w=1200&h=630&bkg=%231e293b&f=png';
    $ctx = stream_context_create(['http' => ['timeout' => 30]]);
    $img = @file_get_contents($url, false, $ctx);
    if ($img && strlen($img) > 1000) {
        Storage::disk('public')->put("blog/{$filename}", $img);
        return true;
    }
    return false;
}
```

`w=1200&h=630` はOGP画像としても使える16:9比率。`bkg=%231e293b` でダークテーマの背景色（slate-800相当）を指定しています。

### datalabelsのformatter関数ハック

QuickChart最大のハマりポイントがここです。Chart.jsの `datalabels.formatter` にはJavaScript関数を指定する必要がありますが、`json_encode` は関数をシリアライズできません。

そこで**プレースホルダー方式**を使います。

```php
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
                'text' => ['Annual Maintenance Cost by Displacement',
                           'Real Data from MotoHub 2026'],
                'color' => '#fff',
                'font' => ['size' => 26, 'weight' => 'bold'],
            ],
            'legend' => ['display' => false],
            'datalabels' => [
                'display' => true,
                'color' => '#fff',
                'anchor' => 'end',
                'align' => 'top',
                'font' => ['size' => 18, 'weight' => 'bold'],
                'formatter' => '__F1__', // プレースホルダー
            ],
        ],
        // scales設定...
    ],
]);

// プレースホルダーを実際のJS関数に置換
$c = str_replace('"__F1__"', "(v)=>v+'万円/年'", $c);

saveChart($c, 'bigbike-eyecatch.png');
```

`"__F1__"` というダミー文字列を `json_encode` で出力した後、`str_replace` で `"__F1__"` をアロー関数に書き換えます。QuickChartはこのJavaScript関数をサーバーサイドで評価してくれるので、データラベルに「31万円/年」のような書式が適用されます。

### 積み上げ棒グラフ

維持費の内訳を表す積み上げ棒グラフも同じ要領です。

```php
$c = json_encode([
    'type' => 'bar',
    'data' => [
        'labels' => ['250cc', '400cc', '650cc', '1000cc~'],
        'datasets' => [
            ['label' => 'Tax/Insurance', 'data' => [4.0, 5.5, 6.0, 7.5],
             'backgroundColor' => '#3b82f6'],
            ['label' => 'Maintenance',   'data' => [2.5, 5.0, 6.5, 9.0],
             'backgroundColor' => '#f59e0b'],
            ['label' => 'Consumables',   'data' => [2.0, 4.0, 5.5, 8.0],
             'backgroundColor' => '#ef4444'],
            ['label' => 'Gas',           'data' => [2.9, 3.5, 3.9, 5.0],
             'backgroundColor' => '#22c55e'],
        ],
    ],
    'options' => [
        'scales' => [
            'y' => ['stacked' => true, /* ... */],
            'x' => ['stacked' => true, /* ... */],
        ],
    ],
]);
```

`stacked: true` を x/y 両方に設定するだけで積み上げ表示になります。

### 折れ線グラフ（走行距離×価格）

DBから取得した走行距離別の平均価格データをそのまま使います。

```php
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
]);
```

`fill: true` + 半透明の `backgroundColor` で、面グラフ風の見た目になります。「走行距離が増えるほど価格が下がる」カーブが一目で分かる図が自動生成されます。

## ハマりポイント

### JSON解析失敗への対応

Claude APIからの応答が常に完璧なJSONとは限りません。実際に遭遇した問題と対策がこちら。

```php
// コードブロックの除去
$cleaned = preg_replace('/^```json\s*/m', '', $content);
$cleaned = preg_replace('/```\s*$/m', '', $cleaned);

// スマートクォート（curly quotes）の置換
$cleaned = str_replace(
    ["\u{201C}", "\u{201D}", "\u{2018}", "\u{2019}"],
    ['"', '"', "'", "'"],
    $cleaned
);

// まずjson_decodeを試行
if (preg_match('/\{[\s\S]*\}/u', $cleaned, $m)) {
    $article = json_decode($m[0], true);

    // json_decodeが失敗した場合、正規表現でフィールドを個別抽出
    if (!$article && preg_match('/"body"\s*:\s*"([\s\S]*)",\s*"meta_description"/u', $m[0], $bm)) {
        preg_match('/"title"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $m[0], $tm);
        preg_match('/"meta_description"\s*:\s*"((?:[^"\\\\]|\\\\.)*)"/u', $m[0], $dm);
        $article = [
            'title' => $tm[1] ?? '',
            'body' => stripcslashes($bm[1]),
            'meta_description' => $dm[1] ?? '',
        ];
    }
}
```

体感で10回に1回程度、`json_decode` が失敗します。原因はbodyフィールド内のエスケープ漏れが大半です。正規表現フォールバックを入れてからは、パース失敗率がほぼゼロになりました。

### タイムアウト180秒

5,000〜7,000文字の長文生成は時間がかかります。Laravelの `Http::timeout(180)` を明示的に設定しないと、デフォルトの30秒でタイムアウトします。

```php
$response = Http::withHeaders([/* ... */])
    ->timeout(180)  // これがないと長文生成で確実にタイムアウトする
    ->post('https://api.anthropic.com/v1/messages', [/* ... */]);
```

## 成果

この仕組みで以下の成果が出ました。

- **1スクリプト実行で15分級の長編記事が生成される**（実行時間は約2〜3分）
- **5記事を1日で本番投入**（排気量帯別の比較記事シリーズ）
- **コスト感: Claude API 1記事あたり約$0.05**（Sonnet、約8,000トークン出力）
- QuickChartは無料枠で十分（月500リクエストまで）
- 手作業だと3〜4時間かかる作業が、スクリプト実行 + 目視チェック + 微修正で30分に短縮

もちろん生成された記事をそのまま公開するわけではなく、ファクトチェックと文体の微調整は人間が行います。それでも作業量は劇的に減りました。

## まとめ

実装のポイントを振り返ります。

1. **Laravelのスタンドアロンブート**で、Artisanコマンド化せずにEloquent/DB/Storageをフル活用
2. **データの構造化整形**がLLMの出力品質を大きく左右する（テーブル形式 + ラベル付き）
3. **プロンプトに画像プレースホルダーを指示**し、後からグラフ画像に差し替える設計
4. **QuickChartのformatterハック**（`"__F1__"` → JS関数置換）でデータラベルを自在に制御
5. **JSONパースのフォールバック**を入れておくと、LLM応答の不安定さに対応できる

### 今後の改善案

- **バッチ化**: 現在は1テーマ1スクリプトだが、テーマ定義をYAML/JSONで管理してバッチ実行する
- **A/Bテスト**: タイトルやリード文のバリエーションを生成し、CTR比較する
- **画像のAlt最適化**: QuickChartの画像にはaltテキストを付けているが、LLMにalt文を生成させるとより適切になる
- **Artisanコマンド化**: テーマをオプションで渡せるようにして `php artisan blog:generate --theme=bigbike` のように実行できると運用しやすい

DBに蓄積されたデータ資産と、LLMの文章生成能力、そしてQuickChartの手軽なグラフ生成。この3つを組み合わせると、データ駆動のSEO記事を驚くほど低コストで量産できます。同じような課題を持つ方の参考になれば幸いです。
