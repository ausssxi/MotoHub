---
title: "Claude APIで中古バイク22万台のAI検索を作った話"
emoji: "🏍️"
type: "tech"
topics: ["claude", "laravel", "ai", "php", "個人開発"]
published: true
---

## はじめに

[MotoHub](https://www.motohub.jp) という中古バイクの一括検索サイトを個人で運営しています。GooBike・BDS・Webikeなど複数サイトの在庫データを集約して、**22万台以上のバイク**を横断検索できるサービスです。他にも39,000件の駐車場マップ、車種判定AI、バイク診断など、バイク選びに必要な機能をひたすら作り続けています。

前職は花屋です。プログラミングは完全に独学で、この規模のサービスを一人で開発・運用しています。

この機能を作ったきっかけは、友人からのLINEでした。

> 「ウッチーのサイトで原チャリ買える？娘のバイクが壊れた」

これに対して僕がやったのは、MotoHubを開いて「50cc以下」「神奈川」「5万円以下」とフィルターをポチポチ設定して、スクショを送ること。**この操作、AIにやらせればいいのでは？**

「予算5万円、原付、娘用」と入力したら、AIが条件を読み取って在庫を検索し、おすすめまで生成してくれる。そんな検索体験を作ることにしました。

## 完成したもの

https://www.motohub.jp/ai-search

[スクリーンショット: AIで探すトップ画面]

自然言語で入力するだけで、AIが検索条件を抽出 → DB検索 → おすすめ文生成まで一気にやってくれます。

**検索例1: 在庫検索モード**

入力: `相模原 5万円以下 娘用の原付`

→ AIが「神奈川県」「50cc以下」「総額5万円以下」を抽出して検索。レッツ2やタクト、DIOフィットなどを提案。「娘さん用」という文脈から「軽量で足つき性が良いスクーターが中心です」とアドバイスも。

**検索例2: 相談モード**

入力: `ドラッグスター250とレブル250どっちがいい？`

→ `query_type: "consult"` と判定され、アドバイスがメインの表示に切り替わる。車両スペックの比較、維持費、乗り味の違いを整理して回答。参考車両も横に表示。

[スクリーンショット: consultモードの回答画面]

初回のハードルを下げるため、「予算30万円、125ccの原付二種」「CBR250RRとNinja250、どっちがいい？」など**6つのプリセットボタン**を用意しました。何を聞けばいいかわからない問題の対策です。

## アーキテクチャ: 2段階API呼び出し方式

処理の全体像はこうなっています。

```
ユーザー入力
    ↓
[STEP1] Claude API: 自然言語 → 検索条件JSON抽出
    ↓
[STEP2] DB検索: Eloquent でリスティング検索 (22万台)
    ↓
[STEP3] Claude API: 検索結果 → おすすめ文生成
    ↓
表示 (search / consult で切り替え)
```

**なぜ2段階にしたのか？** 最初は1回のAPI呼び出しで「条件抽出 + おすすめ生成」をまとめようとしました。しかし、LLMに「JSONを返して、かつ自然な文章も書いて」と頼むと、JSONのパースが不安定になったり、検索条件の精度が落ちたりします。

役割を分離することで、STEP1は「正確なJSON生成」に集中し、STEP3は「検索結果を踏まえた文章生成」に集中できます。

## STEP1: 自然言語→検索条件JSON抽出

STEP1のsystem promptがこの機能の核です。

```php
$systemPrompt = <<<'PROMPT'
あなたはバイク検索アシスタントです。ユーザーの自然言語入力から検索条件を抽出してください。
出力はJSON形式のみ。説明文やマークダウンは不要です。
該当しない項目はnullにしてください。価格は円単位（例: 30万円→300000）、排気量はcc単位で出力してください。
都道府県名は「東京都」「神奈川県」のように正式名称で出力してください。

query_typeの判定基準:
- "search": 在庫検索系（予算、排気量、エリア等の条件指定、「○○を探したい」等）
- "consult": 相談・比較系（「○○と△△どっちがいい？」「初心者向けは？」「維持費は？」等）

{
  "query_type": "search" or "consult",
  "max_price": 上限価格（円）or null,
  "min_price": 下限価格（円）or null,
  "max_displacement": 排気量上限（cc）or null,
  "min_displacement": 排気量下限（cc）or null,
  "max_mileage": 走行距離上限（km）or null,
  "min_model_year": 年式下限（西暦）or null,
  "prefecture": 都道府県名 or null,
  "manufacturer": メーカー名 or null,
  "model_name": 車種名 or null,
  "summary": "抽出した条件の日本語要約（1文）"
}
PROMPT;
```

**入出力例:**

| 入力 | 抽出されるJSON |
|------|---------------|
| `予算30万円、125cc` | `max_price: 300000, max_displacement: 125` |
| `東京でホンダのネイキッド` | `prefecture: "東京都", manufacturer: "ホンダ"` |
| `レブル250とドラスタどっちがいい？` | `query_type: "consult", model_name: null` |

Claude Sonnet（`claude-sonnet-4-20250514`）を使っている理由は**コスト効率**です。条件抽出のような構造化タスクではSonnetで十分な精度が出ます。Opusを使うと精度は若干上がりますがコストが5倍以上になり、個人開発では厳しい。

`max_tokens: 300` に絞っているのもポイントです。JSON出力だけなので300トークンあれば十分で、余計な出力を抑えられます。

## STEP2: おすすめ文生成

DB検索の結果をコンテキストとしてClaude APIに渡します。

```php
$userPrompt = <<<PROMPT
ユーザーの質問: {$userQuery}
ヒット件数: {$totalCount}件
上位結果:
{$resultSummary}

上記を踏まえて回答してください。
PROMPT;
```

`query_type` によってsystem promptを切り替えています。

- **searchモード**: 150〜300字で検索結果のポイントを簡潔にアドバイス
- **consultモード**: 300〜500字でMarkdown（太字・箇条書き）を使ったリッチな回答

「娘用の原付」と入力すると、LLMは「娘さん用」というコンテキストを理解して「軽量で足つき性が良い」「シート高が低い」といった観点を自然に含めてくれます。これは条件フィルターだけでは絶対にできない体験です。

## 工夫したUX

### プリセットボタン

「何を聞けばいいかわからない」は自然言語UIの最大の課題です。6つのプリセットを用意して、ワンタップで検索を体験できるようにしました。searchとconsultの両方のパターンを含めています。

### ブラウザバック対応

バイクカードをクリック → 詳細ページ → ブラウザバック で検索結果が消える問題。`sessionStorage` に検索状態を保存し、ページ表示時に復元することで解決しました。

```javascript
init() {
    try {
        var saved = sessionStorage.getItem(STORAGE_KEY);
        if (saved) {
            var state = JSON.parse(saved);
            this.result = state.result || null;
            this.conversationHistory = state.conversationHistory || [];
        }
    } catch (e) {}
},
saveState() {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
            result: this.result,
            conversationHistory: this.conversationHistory
        }));
    } catch (e) {}
}
```

### 追加質問（会話文脈の維持）

検索結果の下にフォローアップ入力欄を設け、「もう少し安いのは？」「維持費の比較は？」と続けて質問できます。`conversationHistory` をClaude APIの `messages` に含めることで、文脈を維持しています。

```javascript
body: JSON.stringify({
    query: currentQuery,
    history: this.conversationHistory.slice(-10) // 最大5往復
})
```

バックエンド側では `history` をバリデーションして、STEP1・STEP3の両方のAPI呼び出しに過去の会話を挿入しています。

## コスト管理

個人開発で最も気になるのがAPI費用です。

| 項目 | 値 |
|------|-----|
| 1検索あたりの呼び出し | 2回（条件抽出 + アドバイス生成） |
| STEP1 | 入力 ~800トークン, 出力 ~200トークン |
| STEP3 | 入力 ~1,200トークン, 出力 ~400トークン |
| **1検索あたりのコスト** | **約$0.006** |
| レート制限 | 10回/日/IP |
| 月間想定（100検索/日） | 約$18/月 |

```php
// AppServiceProvider.php
RateLimiter::for('ai-search', fn (Request $request) => Limit::perDay(10)->by($request->ip()));
```

月$18なら個人開発でも十分回せます。レート制限をIPあたり10回/日に設定しているので、バズっても致命的なコストにはなりません。

## 技術スタック

| レイヤー | 技術 |
|---------|------|
| バックエンド | Laravel 12 / PHP 8.3 |
| DB | MySQL 8 / Redis（キャッシュ） |
| 全文検索 | Meilisearch |
| AI | Claude API (claude-sonnet-4-20250514) |
| フロントエンド | Blade + Alpine.js + Tailwind CSS |
| インフラ | Docker Compose / さくらVPS 8GB / Cloudflare |

フロントエンドはAlpine.jsのみで、Reactは使っていません。`x-data`、`x-show`、`x-for` で十分にインタラクティブなUIが作れます。

## まとめ

Claude APIを使えば、個人開発のサービスでも大手に負けないAI体験が作れます。

- **2段階方式**で精度とコストのバランスを取る
- **query_type判定**で検索と相談を自動で切り替える
- **sessionStorage**でブラウザバック時の状態復元
- **会話履歴**でフォローアップ質問に対応
- 月$18で22万台のAI検索が動く

元花屋でも作れました。LLMのおかげで「自然言語で在庫検索」という、以前なら大きなチームでないと作れなかった機能が、一人で実装できる時代になっています。

ぜひ試してみてください 👉 https://www.motohub.jp/ai-search

質問やフィードバックがあればコメントで気軽にどうぞ。
