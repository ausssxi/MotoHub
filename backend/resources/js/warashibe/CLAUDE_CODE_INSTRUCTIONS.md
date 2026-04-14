# バイクわらしべ長者 - Claude Code 実装指示書

## 概要
MotoHubに新しいブラウザゲーム「バイクわらしべ長者」を実装します。
BikeQuiz（/quiz）と同じ構成で、React島としてBladeに埋め込みます。

## ルーティング・Blade・Vite

### 1. ルート追加
```php
// routes/web.php に追加
Route::get('/warashibe', function () {
    return view('warashibe');
})->name('warashibe');
```

### 2. Bladeテンプレート
```html
<!-- resources/views/warashibe.blade.php -->
@extends('layouts.app')

@section('title', 'バイクわらしべ長者 | MotoHub')
@section('description', 'カブ50から始まる、交換の旅。バイクを交換しながらドリームバイクを目指すブラウザゲーム。')

@section('content')
<div id="warashibe-root"></div>
@endsection

@push('scripts')
@viteReactRefresh
@vite('resources/js/BikeWarashibe.jsx')
@endpush
```

### 3. Vite設定（エントリポイント追加）
```javascript
// vite.config.js の input 配列に追加
'resources/js/BikeWarashibe.jsx'
```

### 4. エントリポイント
```javascript
// resources/js/BikeWarashibe.jsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './warashibe/App';

const el = document.getElementById('warashibe-root');
if (el) {
  createRoot(el).render(<App />);
}
```

## 画像配置

画像は `public/images/warashibe/` に配置（全15枚）：

```
public/images/warashibe/
├── title_screen.png      # タイトル画面
├── npc_commuter.png      # 通勤おじさん（透過PNG）
├── npc_delivery.png      # 出前バイトくん（透過PNG）
├── npc_college_girl.png  # 女子大生（透過PNG）
├── npc_camper.png        # キャンプライダー（透過PNG）
├── npc_bike_girl.png     # バイク女子（透過PNG）
├── npc_soba_shop.png     # 蕎麦屋のおやじ（透過PNG）
├── bg_street.png         # 街エリア背景
├── bg_suburb.png         # 郊外エリア背景
├── bike_cub50.png        # スーパーカブ50アイコン（透過PNG）
├── bike_pcx.png          # PCX125アイコン（透過PNG）
├── bike_address.png      # アドレス110アイコン（透過PNG）
├── bike_ct125.png        # CT125アイコン（透過PNG）
├── bike_crf.png          # CRF250Lアイコン（透過PNG）
└── bike_gb350.png        # GB350アイコン（透過PNG）
```

## ゲーム仕様

### コンセプト
ミミクリーマン（RPGツクール2000製フリーゲーム）のバイク版。
わらしべ長者式にバイクを交換しながらグレードアップしていくパズルゲーム。

### コアメカニクス
- プレイヤーはバイクを持ってNPCに話しかける
- NPCが欲しいバイクを見せると「交換成立」（バイクを渡して新しいバイクをもらう）
- 間違ったバイクを見せると「ハズレリアクション」（NPCごとに個別の面白いコメント）
- エリア間を移動して新しいNPCと出会う
- 最終的にドリームバイクを目指す

### UI構造（ミミクリーマン準拠）
- タイトル画面 → ゲーム開始
- エリア内はNPC1人ずつ表示。◀▶ボタンで切り替え（ミミクリーマンの↑↓キーに対応）
- NPCに「話しかける」→ セリフ表示 → 「バイクを見せる」or「やめる」
- バイク選択 → 正解/ハズレ判定 → リアクション表示
- 🗺️ボタンでエリアマップ、📦ボタンでインベントリ

### 交換ルール
- 交換するとバイクは消費される（渡したバイクは手元からなくなる）
- NPCは何度でも交換に応じる（再挑戦可能）
- 蕎麦屋のおやじ（街エリア）はスーパーカブ50を無限に提供（引退で余ってる設定）
- カブを既に持っている場合は蕎麦屋に断られる

### エリア構成
- **街**（最初から開放）: 蕎麦屋のおやじ → 通勤おじさん → 女子大生 → 出前バイトくん
- **郊外**（CT125入手で開放）: キャンプライダー → バイク女子

### 交換ツリー（Phase 1）
```
蕎麦屋 → カブ50（無限供給）
  ├→ 通勤おじさん: カブ50 → PCX125
  │    └→ 女子大生: PCX125 → CT125 ハンターカブ ★郊外エリア開放
  │         ├→ キャンプライダー: CT125 → CRF250L
  │         └→ バイク女子: CT125 → GB350
  └→ 出前バイトくん: カブ50 → アドレス110
```

CT125で分岐するので、蕎麦屋でカブを再入手 → チェーンをもう一度回す、で両ルート攻略可能。

### バイクデータ（6車種）

| ID | 名前 | 排気量 | カテゴリ | 相場 | アイコン |
|----|------|--------|---------|------|---------|
| cub50 | スーパーカブ50 | 50cc | 実用 | 10-20万 | bike_cub50.png |
| pcx | PCX125 | 125cc | スクーター | 20-35万 | bike_pcx.png |
| address | アドレス110 | 110cc | スクーター | 10-20万 | bike_address.png |
| ct125 | CT125 ハンターカブ | 125cc | アウトドア | 30-45万 | bike_ct125.png |
| crf | CRF250L | 250cc | オフロード | 40-55万 | bike_crf.png |
| gb350 | GB350 | 350cc | ネオクラシック | 45-60万 | bike_gb350.png |

### NPCデータ（6人）

| ID | 名前 | エリア | 欲しいバイク | くれるバイク | 画像 |
|----|------|--------|-------------|-------------|------|
| soba_shop | 蕎麦屋のおやじ（ゲンさん）| 街 | なし（無条件） | カブ50 | npc_soba_shop.png |
| commuter | 通勤おじさん（田中さん）| 街 | カブ50 | PCX125 | npc_commuter.png |
| college_girl | 女子大生（ミサキ）| 街 | PCX125 | CT125 | npc_college_girl.png |
| delivery | 出前バイトくん（ユウキ）| 街 | カブ50 | アドレス110 | npc_delivery.png |
| camper | キャンプライダー（タケシ）| 郊外 | CT125 | CRF250L | npc_camper.png |
| bike_girl | バイク女子（アヤカ）| 郊外 | CT125 | GB350 | npc_bike_girl.png |

## ゲームステート設計（useReducer）

```javascript
const initialState = {
  phase: "title",           // title | playing
  currentArea: "street",    // street | suburb
  npcIndex: 0,              // エリア内のNPC位置（◀▶で切替）
  scene: "explore",         // explore | dialog | bikeSelect | inventory | map

  bikes: ["cub50"],         // 所持バイクIDの配列
  parts: [],                // 所持パーツ（Phase 2以降）
  selectedBike: null,       // NPCに見せようとしているバイク

  unlockedAreas: ["street"],
  defeatedNpcs: [],         // 交換済みNPC（再交換は可能だが、図鑑記録用）

  activeNpc: null,          // 会話中のNPC ID
  dialogLines: [],          // 表示するセリフ配列
  dialogIndex: 0,           // 現在表示中のセリフインデックス
  dialogPhase: "idle",      // idle | greeting | choice | correct | wrong | done | shopGreeting | afterChat

  exchangeAnim: false,      // 交換演出中フラグ
  message: null,            // トースト表示用メッセージ

  encyclopedia: {
    bikesFound: ["cub50"],
    wrongSeen: 0,
  },
};
```

## Reducerアクション一覧

| action.type | 説明 |
|------------|------|
| START | ゲーム開始 |
| NAV | エリア内NPC切替（dir: -1 or 1） |
| TALK | NPCに話しかける |
| NEXT_LINE | セリフ送り |
| PICK_BIKE | バイク選択画面を開く |
| SHOW | バイクをNPCに見せる（bikeId） |
| EXCHANGE | 交換実行 |
| BACK_DIALOG | バイク選択からダイアログに戻る |
| LEAVE | 会話終了 |
| OPEN_MAP | エリアマップを開く |
| OPEN_INV | インベントリを開く |
| GO_AREA | エリア移動（areaId） |
| CLOSE | シーンを閉じてexploreに戻る |
| CLEAR_MSG | トーストメッセージを消す |

## セリフデータ

各NPCのセリフは以下の構造：
- idle: 待機時の吹き出しセリフ（1行）
- greeting: 話しかけた時のセリフ（配列、1行ずつ表示）
- correct: 正解バイクを見せた時（配列）
- correctAfter: 交換成立後のセリフ（配列）
- wrong: ハズレ時のセリフ（バイクID別のオブジェクト、_defaultキーあり）
- afterExchange: 交換済みNPCに再度話しかけた時（配列）

### 蕎麦屋のおやじ
```
idle: "もう引退だからなぁ…カブが余っちまって…"
greeting: ["おう、若いの。バイク乗りかい。", "ウチは来月で店じまいなんだ。", "出前用のカブが何台も余っちまってよ。", "欲しけりゃ持っていきな。タダでいいよ"]
（蕎麦屋はバイク選択なし。セリフ後に自動でカブ50付与）
（カブ50を既に持っている場合：「もうカブ持ってるじゃねぇか！ また必要になったら来な」）
afterExchange: ["おう、また来たか！ カブが要るのか？", "何台でも持ってきな。倉庫パンパンだからよ"]
```

### 通勤おじさん
```
idle: "はぁ…ガソリン代が…今月もう3回も給油したよ…"
greeting: ["ん？ キミ、バイク乗りかい？", "いやね、ウチのPCXも悪くないんだけど、", "もっと燃費のいいバイクがないかなぁって…"]
correct(カブ50): ["こっ…これは！ スーパーカブ！！", "リッター60km…いや、70km走るって聞いたことある…！", "これだよ、これが欲しかったんだ！"]
correctAfter: ["大事にしてくれよ、PCX。", "まぁ俺にはもうカブがあるからいいんだけどね…ふふ…"]
wrong:
  ct125: "ハンターカブ？ いいねぇ…でも若い子向けでしょ？ おじさんが乗ったら似合わないよ…"
  gb350: "クラシックか…味はあるけど、燃費で選ぶ俺には贅沢だなぁ"
  crf: "泥がつくやつはちょっと… 奥さんに怒られる未来が見える"
  address: "アドレス…通勤にはいいけど、もっと燃費が…もっと…"
  pcx: "それ俺が今乗ってるやつなんだけど…？"
  _default: "うーん…もっと燃費のいいやつが…"
afterExchange: ["カブ最高だよ…今朝の通勤、燃費リッター68kmだった…", "もうPCXには戻れないね。ふふ…"]
```

### 女子大生
```
idle: "もっとオシャレなバイクでカフェ巡りしたいなぁ…"
greeting: ["あ、こんにちは！", "私、去年ハンターカブ買ったんだけど、", "結局キャンプ1回しか行ってなくて…", "街乗りでオシャレなバイクに乗り換えたいなって"]
correct(PCX): ["えっ、PCX!? めっちゃいいじゃん！", "メットインに荷物入るし、スカートでも乗れるし…", "CT125…正直ちょっと持て余してたの。交換してくれる？"]
correctAfter: ["ハンターカブ、すっごくいいバイクだよ！", "…私が使いこなせなかっただけで"]
wrong:
  cub50: "カブ…？ おばあちゃんが乗ってるイメージ… いや、レトロ可愛いのはわかるよ？ でもちょっと…"
  address: "アドレス…配達の人が乗ってるイメージ… おしゃれじゃないかも…"
  ct125: "え、それ私が今乗ってるやつなんだけど…？"
  crf: "泥だらけのバイクでスタバ行けないでしょ…"
  gb350: "GB350！ 可愛いけど…メットインがないのはちょっと…"
  _default: "うーん、それはちょっと私には合わないかも…"
afterExchange: ["PCX最高～！ 今日もカフェ3軒ハシゴしちゃった！", "メットインにエコバッグ入るのが神すぎる"]
```

### 出前バイトくん
```
idle: "あーーー、今日も配達30件…ケツ痛い…"
greeting: ["あ、先輩ライダーっすか？", "俺、今アドレスで配達してんすけど、", "正直キツくて。もっと頑丈で燃費いいのないっすかね"]
correct(カブ50): ["うおっ！ カブじゃん！！", "配達の先輩がみんな『最終的にカブに戻る』って言ってたんすよ！", "これが配達の最終兵器…！"]
correctAfter: ["アドレスもいいバイクっすよ。", "…ただ、カブの前ではすべてが霞むんすわ"]
wrong:
  pcx: "PCXか…悪くないけど、カブほどの耐久性はないんすよね"
  ct125: "ハンターカブ…かっこいいっすけど、配達にはちょいオーバースペックっす"
  gb350: "オシャレっすね…でも汁こぼしてシート汚したら泣くじゃないっすか"
  crf: "オフ車で配達…？ 段差には強そうだけど箱が積めないっす"
  _default: "えっ…それ配達に使えないっすよ…"
afterExchange: ["カブ、マジ最強っす！ 配達効率が爆上がりっすよ！", "先輩に感謝っす！"]
```

### キャンプライダー
```
idle: "テント、寝袋、焚き火台…全部積みたい…"
greeting: ["よう！ いいバイク乗ってるな。", "俺、CRFでキャンプ行ってるんだけどさ、", "荷物が積めなくてパンパンなんだよ。", "もっと積載できるバイクに乗り換えたいんだわ"]
correct(CT125): ["ハンターカブ！！ これだよこれ！！", "リアキャリアにホムセン箱つけたら最強じゃん！", "キャンプツーリングの最適解はこれだったんだ…！"]
correctAfter: ["CRFはいいバイクだぞ。林道はガチで楽しい。", "…荷物さえ積めればな"]
wrong:
  cub50: "カブ50…積載はいいけど高速乗れないからキャンプ場まで辿り着けないんだわ"
  pcx: "スクーターでキャンプ？ メットインに焚き火台は入らないだろ"
  gb350: "クラシックか…見た目はいいが積載が不安だな"
  address: "アドレスでキャンプ…？ さすがに荷物が乗らないだろ"
  _default: "うーん、それだとキャンプ道具が積めないなぁ…"
afterExchange: ["ハンターカブ、ホムセン箱つけたら積載量が倍になったぞ！", "来週のキャンプが楽しみだ！"]
```

### バイク女子
```
idle: "ハンターカブ可愛すぎて気になる…"
greeting: ["あ、ちょっとそのバイク見せてもらっていい？", "私、GB350に乗ってるんだけど、", "最近カブ系の丸っこいフォルムにハマっちゃって…", "乗り換えようか迷ってるの"]
correct(CT125): ["やっぱりハンターカブ最高に可愛い！！", "サムネ映えするし、女子ウケもいいし…", "GB350より動画のネタにもなりそう！"]
correctAfter: ["GB350大事にしてね。鼓動感、最高だから。", "…私の動画のコメント欄が荒れなきゃいいけど"]
wrong:
  cub50: "カブ50！ かわいい！ …けど50ccだと高速乗れないからツーリング動画が撮れない…"
  pcx: "PCXは便利だけど、動画映えしないんだよね…"
  crf: "オフ車はね…汚れるの… 洗車動画ばっかりになっちゃう"
  address: "アドレスは…さすがにYouTuber的に厳しいかな…"
  _default: "うーん…私のチャンネル的にはちょっと違うかな"
afterExchange: ["ハンターカブの納車動画、もう10万回再生いったよ！", "交換してくれてありがとう！"]
```

## デザイン方針

### テーマ: ダークRPG風（ミミクリーマン準拠）
- 背景: ダーク (#1a1a2e)
- カード: ダークネイビー (#16213e)
- テキスト: ライトグレー (#e0e0e0)
- アクセント: ピンクレッド (#e94560)
- 成功: グリーン (#4caf50)
- フォント: 'Noto Sans JP'

### 画面レイアウト
- max-width: 480px（モバイルファースト）
- タイトル画面にtitle_screen.pngを使用
- NPCは◀▶で切替（1人ずつ表示）
- NPCの画像はnpc_xxx.pngをそのまま使用
- インベントリのバイク表示にbike_xxx.pngアイコンを使用
- バイク選択画面でもbike_xxx.pngを表示

## 参考実装

BikeWarashibeDemo.jsx（このプロジェクト内にある）を参考にしてください。
動作するプロトタイプです。以下の機能がすべて実装済み：
- タイトル画面
- 順次NPC出会い方式
- セリフ送り
- バイク選択→正解/ハズレ判定
- 交換実行（バイク消費）
- 蕎麦屋のおやじ（カブ無限供給）
- エリア開放（CT125入手で郊外開放）
- 交換済みNPC再訪問
- インベントリ
- エリアマップ
- トーストメッセージ

デモではemoji avatarを使用していますが、本実装ではnpc_xxx.png画像に差し替えてください。
バイク表示もテキストからbike_xxx.pngアイコンに差し替えてください。

## デプロイ手順
1. npm run build
2. git add -A && git commit -m "feat: バイクわらしべ長者ゲーム Phase 1"
3. VPS: git pull origin main
4. php artisan config:cache && php artisan view:cache && php artisan route:cache
5. Cloudflareキャッシュパージ
