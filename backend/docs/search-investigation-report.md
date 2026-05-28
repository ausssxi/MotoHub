# MotoHub 検索機能（/bikes/search）現状調査レポート

> 調査日: 2026-05-28

---

## 1. 検索UI構成

### 1.1 ページ構造

**ファイル:** `resources/views/bikes/search.blade.php`（約600行）

2カラムレイアウト:
- **左サイドバー**（w-full lg:w-72）: フィルターパネル（デスクトップ固定、モバイルはボトムシート）
- **メインコンテンツ**（flex-1）: 検索結果グリッド + ページネーション

```
┌──────────────────────────────────────────────────┐
│ Navigation（キーワード検索バー内蔵）              │
├─────────┬────────────────────────────────────────┤
│ Filter  │ Results Header（件数 / ソート / AI検索）│
│ Sidebar │ ────────────────────────────────────── │
│         │ Market Report Panel（平均/最安/最高）   │
│ 地域    │ ────────────────────────────────────── │
│ 状態    │ Results Grid（1→2→3列レスポンシブ）    │
│ メーカー│  [Card][Card][Card]                    │
│ 車種    │  [Card][Card][Card]                    │
│ 価格    │  ...                                   │
│ 走行距離│ ────────────────────────────────────── │
│ 年式    │ Load More / Pagination                 │
│         │ ────────────────────────────────────── │
│ 通知CTA │ Recommended Models                     │
│         │ SEO Internal Links                     │
└─────────┴────────────────────────────────────────┘
```

### 1.2 検索フォームのフィールド一覧

| フィルター | パラメータ | UIコンポーネント | 備考 |
|-----------|-----------|----------------|------|
| キーワード | `keyword` | ナビバーのテキスト入力 | `/`、`"`、`'` を除去 |
| 地域 | `prefecture` | セレクトドロップダウン | 47都道府県 + ファセットカウント表示 |
| コンディション | `is_new` | ラジオボタン（すべて/中古/新車） | ファセットカウント付き |
| 修復歴 | `has_repair_history` | ラジオボタン（すべて/なし/あり） | ファセットカウント付き |
| メーカー | `manufacturer_id` | セレクトドロップダウン | 変更時に車種リセット |
| 車種 | `bike_model_id` | セレクトドロップダウン | AJAX `/api/manufacturers/{id}/models` で動的取得 |
| 価格 | `min_price` / `max_price` | デュアルレンジスライダー | 0〜300万円、5万円刻み |
| 走行距離 | `min_mileage` / `max_mileage` | デュアルレンジスライダー | 0〜50,000km、1,000km刻み |
| 年式 | `min_year` / `max_year` | デュアルレンジスライダー | 1990〜現在年、1年刻み |
| タグ | `tag` | タグボタン（トグル式） | 人気のこだわり条件（ETC装備等） |
| カテゴリ | `category_id` | （UIにはなし、内部パラメータ） | URL直接指定のみ |
| 排気量 | `min_displacement` / `max_displacement` | （UIにはなし） | バリデーションはあるがUI未実装 |

### 1.3 ソートオプション

| 値 | 表示名 | Meilisearchフィールド |
|----|--------|---------------------|
| `bargain_desc` | お得度順（デフォルト） | `bargain_score DESC` |
| `latest` | 新着順 | `created_at DESC` |
| `price_asc` | 安い順 | `total_price ASC` |
| `price_desc` | 高い順 | `total_price DESC` |
| `mileage_asc` | 走行距離が少ない順 | `mileage ASC` |
| `year_desc` | 年式が新しい順 | `model_year DESC` |
| `year_asc` | 年式が古い順 | `model_year ASC` |

### 1.4 モバイル対応

| 要素 | デスクトップ | モバイル |
|------|------------|---------|
| サイドバー | 左カラムに常時表示 | ボトムシートモーダル（下からスライド、最大92vh） |
| フィルター操作 | 変更時に自動検索 | 「条件を適用する（〇〇台）」ボタンで一括適用 |
| 結果グリッド | 3列（xl）/2列（sm） | 1列 |
| ソートドロップダウン | w-64 | フル幅 |
| フォーム入力 | 標準サイズ | 16px font-size（iOS ズーム防止） |

**モバイル専用要素:**
- 「絞り込み」ボタン（`lg:hidden`）でサイドバー開閉
- ダークオーバーレイ + アニメーション付きボトムシート
- リアルタイムヒットカウント更新（400msデバウンス、`count_only=1` AJAX）
- 閉じるボタン（×アイコン）

### 1.5 その他のUI要素

- **マーケットレポートパネル**: bike_model_id選択時に平均総額/最安値/最高値を表示（青帯）
- **閲覧履歴ウィジェット**: 最近閲覧したバイクを横スクロール表示
- **愛車ガレージCTA**: 6件目の後にピンクカードで挿入
- **LINE通知CTA**: 15件目の後にゲスト向けグリーンカードで挿入
- **空検索時の救済UI**: 条件緩和ボタン + バイク診断への誘導
- **おすすめモデル**: 検索コンテキストに基づく関連車種グリッド（2→3→6列）
- **比較機能**: カード左上のレイヤーアイコンでlocalStorageに保存（最大4台）
- **お気に入り**: カード右上のハートアイコン
- **お得バッジ**: bargain_score > 5 の場合「相場より約〇%お得！」赤バッジ

---

## 2. バックエンド

### 2.1 アーキテクチャ概要

```
HTTP GET /bikes/search?keyword=TW225&prefecture=東京&sort=bargain_desc
    │
    ▼
BikeSearchRequest（バリデーション・サニタイズ）
    │  app/Http/Requests/Bike/BikeSearchRequest.php
    ▼
BikeController::search()（リクエスト種別の振り分け）
    │  app/Http/Controllers/Bike/BikeController.php:261
    │
    ├─ count_only=1 → JSON { total: N }
    ├─ load_more=1  → JSON { html: "...", next_url: "..." }
    └─ 通常         → view('bikes.search', $data)
    │
    ▼
ListingSearchService::search()（ビジネスロジック統括）
    │  app/Services/Bike/ListingSearchService.php
    │
    ├─ KeywordInferrer::infer()    ─ キーワードから構造化フィルター抽出
    │    app/Services/Bike/Search/KeywordInferrer.php
    │    "TW225 東京 ETC" → { bike_model_id: 123, prefecture: '東京', tag: 'ETC' }
    │
    ├─ SearchMetadataGenerator     ─ スライダー範囲・統計フォーマット
    │    app/Services/Bike/Search/SearchMetadataGenerator.php
    │
    ├─ PaginationFormatter         ─ ページネーションUI生成
    │    app/Services/Bike/Search/PaginationFormatter.php
    │
    ├─ ListingRepository           ─ Meilisearch検索実行
    │    app/Repositories/Bike/ListingRepository.php
    │
    └─ ListingStatsRepository      ─ 価格統計（MySQL）
         app/Repositories/Bike/ListingStatsRepository.php
```

### 2.2 Meilisearch vs MySQL SQL

| 処理 | エンジン | 推定レスポンス | 備考 |
|------|---------|-------------|------|
| キーワード全文検索 | **Meilisearch** | ~10-50ms | title, manufacturer_name, bike_model_name |
| フィルタリング + ソート | **Meilisearch** | ~50-200ms | 全フィルターをMeilisearchフィルター文字列に変換 |
| ファセットカウント | **Meilisearch** | ~50-100ms | prefecture, is_new, has_repair_history |
| 価格統計（平均/最安/最高） | **MySQL** | ~10-50ms | bike_model_id指定時のみ実行 |
| キーワード推論 | **MySQL** | ~10-30ms | DB LIKE検索でモデル・メーカー・都道府県をマッチ |
| リレーション読み込み | **MySQL** | ~50-100ms | MeilisearchがIDを返した後、Eloquentで詳細取得 |

**Meilisearch設定** (`config/scout.php`):

```php
'filterableAttributes' => [
    'prefecture', 'manufacturer_id', 'bike_model_id', 'category_id',
    'is_new', 'is_sold_out', 'tag_slugs', 'total_price', 'mileage', 'model_year'
],
'sortableAttributes' => [
    'total_price', 'mileage', 'model_year', 'bargain_score', 'created_at'
],
```

### 2.3 N+1クエリ対策

**対策済み（問題なし）:**

```php
// ListingRepository::searchByKeyword()
$search->query(function ($query) {
    $query->select(self::LIST_COLUMNS)
        ->with([
            'bikeModel:id,manufacturer_id,category_id,name',
            'bikeModel.manufacturer:id,name',
            'shop:id,name,prefecture',
            'site:id,name',
            'tags:id,name'
        ]);
});
```

- 全リレーションを`with()`で一括ロード
- カラム選択でペイロード削減
- `makeAllSearchableUsing()`で一括インデックス時もN+1防止

**潜在リスク:**
- `scopeWithTag`の`whereHas('tags', ...)`はタグ数が多い場合にパフォーマンス低下の可能性（現状はMeilisearch側でフィルタリングしているため問題なし）

### 2.4 キャッシュ戦略

| キャッシュキー | TTL | 内容 |
|--------------|-----|------|
| `search_agg_{conditionHash}` | 1時間 | メタデータ、統計、ファセット |
| `search_results_p{page}_{hash}` | 30分 | ページネーション済みリスティング |

ハッシュ: `md5(serialize([$keyword, $prefecture, $filters, $sort, $perPage]))`

### 2.5 ページネーション

- **方式:** `simplePaginate`（`Paginator`）— 合計件数不要でCOUNTクエリ回避
- **1ページ:** 30件
- **無限スクロール:** `load_more=1`パラメータでAJAX取得、`insertAdjacentHTML`で追加
- **クエリ文字列保持:** `withQueryString()`で全フィルターをページ遷移時に維持
- **SessionStorage:** `motohub_search_state`に閲覧済みIDリストとURLを保存

---

## 3. URL構造

### 3.1 クエリパラメータ一覧

```
/bikes/search?
  keyword=TW225                   # フリーテキスト検索（max:100）
  prefecture=東京                 # 地域フィルター
  manufacturer_id=45              # メーカーID（exists:manufacturers,id）
  bike_model_id=123               # 車種ID（exists:bike_models,id）
  category_id=10                  # カテゴリID（exists:categories,id）
  min_price=50                    # 最低価格（万円）
  max_price=150                   # 最高価格（万円）
  min_mileage=0                   # 最低走行距離（km）
  max_mileage=50000               # 最高走行距離（km）
  min_year=2015                   # 最低年式
  max_year=2024                   # 最高年式
  min_displacement=125            # 最低排気量（cc）- UI未実装
  max_displacement=400            # 最高排気量（cc）- UI未実装
  is_new=1                        # 新車/中古（0=中古, 1=新車）
  has_repair_history=0            # 修復歴（0=なし, 1=あり）
  tag=ETC                         # こだわりタグ
  sort=bargain_desc               # ソート順
  page=2                          # ページ番号
  load_more=1                     # 無限スクロール用（内部）
  count_only=1                    # 件数取得用（内部）
```

### 3.2 SEO対応状況

| 項目 | 状態 | 詳細 |
|------|------|------|
| robots | `noindex, follow` | 検索結果はインデックスしない（パラメータ組み合わせ爆発防止） |
| canonical | 設定済み | keyword, manufacturer_id, bike_model_id, prefecture, tag のみ含む |
| title | 動的生成 | 都道府県 + カテゴリの組み合わせでタイトル変更 |
| meta description | 動的生成 | フィルター条件に応じた説明文 |
| JSON-LD | BreadcrumbList | HOME → 検索結果ページタイトル |
| サイトマップ | 非掲載 | noindexと整合 |
| /search リダイレクト | 301 | 旧URLから `/bikes/search` への永続リダイレクト |

**プログラマティックSEOランディングページ（インデックス対象）:**

| URL | 用途 | 例 |
|-----|------|-----|
| `/bikes/area/{prefecture}` | 都道府県一覧 | `/bikes/area/東京都` |
| `/bikes/area/{prefecture}/{slug}` | 都道府県×メーカー/カテゴリ | `/bikes/area/東京都/ホンダ` |
| `/bikes/area/{prefecture}/{city}/{slug}` | 市区町村レベル | `/bikes/area/東京都/渋谷区/ホンダ` |
| `/bikes/catalog/{slug}` | 車種カタログ | `/bikes/catalog/honda-pcx` |

→ 検索結果ページは**noindex**で、SEO流入は上記ランディングページが担当する設計。適切。

---

## 4. 現在の課題点

### 4.1 UI改善が必要な箇所

| 課題 | 重要度 | 詳細 |
|------|--------|------|
| **排気量フィルターがUIにない** | 中 | バリデーションルールは存在するがUI未実装。バイク検索で排気量は重要な条件 |
| **カテゴリフィルターがUIにない** | 中 | category_id パラメータは受け付けるが、サイドバーに選択UIなし |
| **タグが1つしか選択できない** | 低 | パラメータ `tag` は単一値のみ。ETC+ABS等の複数条件不可 |
| **色フィルターがない** | 低 | GooBike等では車体色で絞り込み可能 |
| **保存検索のフィードバック不足** | 低 | 保存成功後のUI変化がボタン色変更のみ |
| **マーケットレポートがモデル選択時のみ** | 低 | 全体検索時にも参考価格帯があると便利 |
| **空検索の救済UIがやや長い** | 低 | バイク診断誘導は良いが、代替検索条件提案をもっと目立たせるべき |

### 4.2 技術的負債

| 課題 | 重要度 | 詳細 |
|------|--------|------|
| **JS分離が不完全** | 中 | sidebar.js / infinite-scroll.js / save_condition.js が個別ファイルだがモジュール化されておらず、グローバルスコープ依存 |
| **search.blade.phpが600行** | 中 | テンプレートが大きい。フィルターサイドバー・結果グリッド・空状態をパーシャルに分割すべき |
| **KeywordInferrerのDB依存** | 低 | キーワード推論で毎回DB LIKE検索。キャッシュされているが、推論精度にも限界あり |
| **スライダー範囲が固定値** | 低 | 価格上限300万円、走行距離50,000km固定。実データの分布と乖離する可能性 |
| **CSSがbike-search.cssに276行** | 低 | Tailwindと独自CSSの混在。スライダー部分以外はTailwindに統一可能 |

### 4.3 検索精度の問題

| 課題 | 詳細 |
|------|------|
| **キーワード推論の限界** | 「ネイキッド 大型」のような抽象的なキーワードは構造化できない |
| **表記揺れ対応** | Meilisearchのtypo toleranceに依存。「カワサキ」と「KAWASAKI」等は推論で対応しているが完全ではない |
| **同義語未設定** | Meilisearchのsynonyms未活用（「原付」=「50cc」、「大型」=「401cc以上」等） |
| **都道府県の正規化** | 「東京」→「東京都」の変換はあるが、「関東」「首都圏」等のエリア検索は不可 |
| **ファセットカウントのずれ** | キャッシュTTL（1時間）内はファセットカウントが実態と乖離する可能性 |

---

## 5. 他サイトとの比較ポイント

### 5.1 GooBikeとの比較

| 機能 | MotoHub | GooBike | 差分 |
|------|---------|---------|------|
| キーワード検索 | あり（Meilisearch） | あり | 同等 |
| メーカー×車種連動 | あり | あり | 同等 |
| 排気量フィルター | **なし（UI未実装）** | あり（50cc/125cc/250cc等のクラス別） | **不足** |
| カテゴリフィルター | **なし（UI未実装）** | あり（ネイキッド/スクーター等） | **不足** |
| 車体色フィルター | **なし** | あり | **不足** |
| ミッション種別 | **なし** | あり（AT/MT） | **不足** |
| 装備・特徴タグ | あり（単一選択） | あり（複数選択可） | **不足** |
| 地域（都道府県） | あり | あり（市区町村まで） | やや不足 |
| 価格帯 | デュアルスライダー | 段階選択（〜30万/〜50万等） | MotoHubの方が柔軟 |
| 走行距離 | デュアルスライダー | 段階選択 | MotoHubの方が柔軟 |
| 保証・特典 | **なし** | あり | **不足** |
| 納車整備 | **なし** | あり | **不足** |
| 画像枚数フィルター | **なし** | あり | **不足** |
| お気に入り | あり | あり | 同等 |
| 比較機能 | あり（最大4台） | あり | 同等 |
| お得度表示 | あり（bargain_score） | なし | **MotoHub優位** |
| AI検索 | あり（`/ai-search`） | なし | **MotoHub優位** |
| LINE通知連携 | あり | なし | **MotoHub優位** |
| マーケットレポート | あり | なし | **MotoHub優位** |

### 5.2 Webikeとの比較

| 機能 | MotoHub | Webike | 差分 |
|------|---------|--------|------|
| パーツ検索 | なし | あり | 用途が異なる |
| 車検有無フィルター | **なし** | あり | **不足** |
| 年式の具体的な選択 | スライダー | ドロップダウン（年/月） | 操作性の違い |
| 店舗評価フィルター | **なし** | あり | **不足** |
| ローンシミュレーション | **なし** | あり | **不足**（検索結果内） |

### 5.3 MotoHubの差別化ポイント

1. **お得度スコア（bargain_score）**: 相場比較でお得な車両を可視化 — 他サイトにない独自価値
2. **AI検索（/ai-search）**: 自然言語での検索が可能
3. **LINE連携通知**: 条件保存 + LINE Pushで新着・値下げを通知
4. **マーケットレポート**: 検索結果内で価格相場を表示
5. **デュアルレンジスライダー**: 段階選択より柔軟な絞り込み

### 5.4 優先的に追加すべき機能

1. **排気量クラスフィルター** — バイク選びの最重要条件の一つ。UI実装のみで対応可能（バックエンドは対応済み）
2. **カテゴリフィルター** — ネイキッド/スクーター/オフロード等。同様にバックエンド対応済み
3. **複数タグ選択** — ETC+ABS+セル付き等の複合条件
4. **車体色フィルター** — データがあれば比較的低コストで追加可能
5. **エリア検索（関東/関西等）** — 都道府県グループ化

---

## 6. 主要ファイル一覧

| レイヤー | ファイル | 行数 |
|---------|---------|------|
| View | `resources/views/bikes/search.blade.php` | ~600 |
| View（カード） | `resources/views/bikes/partials/bike_card.blade.php` | ~118 |
| CSS | `public/css/bike-search.css` | ~276 |
| JS（サイドバー） | `public/js/search/sidebar.js` | ~231 |
| JS（無限スクロール） | `public/js/search/infinite-scroll.js` | ~145 |
| JS（条件保存） | `public/js/search/save_condition.js` | ~64 |
| JS（ソート） | `public/js/common/custom-dropdown.js` | ~53 |
| Controller | `app/Http/Controllers/Bike/BikeController.php` | search()メソッド |
| Request | `app/Http/Requests/Bike/BikeSearchRequest.php` | - |
| Service | `app/Services/Bike/ListingSearchService.php` | - |
| Service | `app/Services/Bike/Search/KeywordInferrer.php` | - |
| Service | `app/Services/Bike/Search/SearchMetadataGenerator.php` | - |
| Service | `app/Services/Bike/Search/PaginationFormatter.php` | - |
| Repository | `app/Repositories/Bike/ListingRepository.php` | - |
| Repository | `app/Repositories/Bike/ListingStatsRepository.php` | - |
| Model | `app/Models/Listing.php` | - |
| Config | `config/scout.php` | Meilisearch設定 |

---

## 7. 総合評価

### 良い点
- **Repository + Service パターン**が適切に分離されており、保守性が高い
- **Meilisearch活用**で高速な全文検索 + フィルタリングを実現
- **N+1対策**が徹底されている（eager loading、カラム選択）
- **2段キャッシュ**（集計1時間 + 結果30分）で負荷を軽減
- **simplePaginate**でCOUNTクエリを回避
- **SEO設計**が適切（noindex + プログラマティックランディングページ）
- **モバイルUX**が丁寧（ボトムシート、リアルタイムヒットカウント、iOS ズーム防止）
- **お得度スコア**と**マーケットレポート**が独自の価値を提供

### 改善推奨事項（優先順）
1. **排気量・カテゴリフィルターのUI追加**（バックエンド対応済みなので工数小）
2. **Meilisearch synonyms設定**（「原付」=「50cc」等で検索精度向上）
3. **search.blade.phpのパーシャル分割**（保守性向上）
4. **複数タグ選択対応**（ユーザビリティ向上）
5. **JS のモジュール化**（Viteでのバンドル管理統一）
