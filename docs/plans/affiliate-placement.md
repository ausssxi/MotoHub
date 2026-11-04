# アフィリエイト配置計画（レントラックス5案件）

**ステータス: 調査・提案のみ（コード未変更）。** 本ドキュメントは配置の提案書。実装は別PRで、マージ判断は内田。

調査日: 2026-11-04 / 対象: motohub.jp（backend = Laravel 12, Blade + Alpine + Tailwind）

---

## 0. 対象5案件（レントラックス経由）

| # | 案件 | 報酬 | 成果条件 | ジャンル | 刺さる人 |
|---|---|---|---|---|---|
| 1 | KATIX（バイク画像査定） | 4,400円 | 新規売却完了 | 買取（画像査定・非対面） | 売りたい人 |
| 2 | ZuttoRide Club（ロードサービス・盗難保険） | 1,276円 | 加入＋入金完了 | 保険/ロードサービス | 乗っている人・買ったばかりの人・ツーリング |
| 3 | バイクランド（買取査定） | 3,565円 | 査定（成約不問） | 買取（出張・大手） | 売りたい人 |
| 4 | インズウェブ（バイク保険一括見積もり） | 427円 | 見積もり完了 | 任意保険（見積もり） | これから乗る人・乗っている人 |
| 5 | JAF（新規入会） | 800円 | 新規入会登録完了 | ロードサービス | ツーリング・道の駅・レンタル |

**成果条件のハードル（低い＝置けば決まりやすい）**: バイクランド（査定のみ）＜ インズウェブ（見積もりのみ）＜ JAF（入会）＜ ZuttoRide（加入＋入金）＜ KATIX（売却完了）。
報酬の高さと成果ハードルは逆相関。**KATIXは報酬が高いが成約まで必要**、**バイクランド/インズウェブは軽い行動で成果**。この違いを置き方に反映する。

---

## 1. MotoHub の公開ページ種類（アフィリ観点でのクラスタ）

全54種類の公開ページを、来訪者の目的（＝案件が解決する困りごと）で5クラスタに整理した。網羅リストは本節末尾。

| クラスタ | 代表ページ（route名 / 主Blade） | 来訪者の目的 | 相性の良い案件 |
|---|---|---|---|
| **A. 売りたい** | `/sell`（sell.index / `pages/sell.blade.php`）＝**買取相場シミュレーション**、`/market`（相場ハブ）、車種詳細のリセール欄 | 自分のバイクがいくらで売れるか | **① KATIX・③ バイクランド** |
| **B. これから買う** | `/bikes/search`・車種詳細・`/bikes/catalog/*`・`/shinkijun-gentsuki`・`/license`・`/shindan`・`/ai-search` | 買う車種を決める／免許を取る | （保険は購入直後へ：②④） |
| **C. 乗っている（維持・保険・トラブル）** | `/hoken`（保険・維持費）、`/theft`（盗難データ）、`/trouble`、`/bikes/{model}`（維持費/メンテ欄）、`/garage/public`、`/parts/*` | 維持費・保険・盗難・故障の不安を解く | **② ZuttoRide・④ インズウェブ** |
| **D. 停める（駐車・盗難）** | `/parking`・`/parking/{id}`・`/parking/area/*`・`/parking/station/*` | 停め場所を探す／盗難が心配 | **② ZuttoRide（盗難保険）** |
| **E. 走る（ツーリング）** | `/touring/*`、`/michinoeki`（道の駅）、`/rental-bikes`、`/rental-garages`、`/riders-map`、`/gs`・`/konbini`・`/senshajo` | 出先の安心・レンタル | **⑤ JAF・② ZuttoRide（ロードサービス）** |
| F. 回遊・情報 | トップ、`/ranking`、`/news`、`/blog/*`、`/features`、ゲーム類 | 情報収集・暇つぶし | （直接の送客は弱い。AdSense領域） |

<details>
<summary>公開ページ全54種（route名／主Blade／目的）</summary>

- バイク検索トップ `bikes.index` / `bikes/index.blade.php` — これから買う
- 検索結果 `bikes.search` / `bikes/search.blade.php` — これから買う
- 車種詳細 `bikes.model_detail` / `bikes/model_detail.blade.php` — 乗ってる/これから買う
- 中古車詳細 `bikes.show` / `bikes/show.blade.php` — これから買う
- 車種Q&A/スレッド `bikes.model_questions`,`bikes.thread` / `bikes/questions_index.blade.php` 他 — 乗ってる
- 新着入荷 `bikes.new_arrivals` / `bikes/new_arrivals.blade.php` — これから買う
- 値下げ `bikes.price_drops` — これから買う
- お買い得 `bikes.bargains` — これから買う
- 絶版バイク `bikes.discontinued` — バイク好き
- カタログ/SEO着地 `bikes.catalog`,`bikes.landing`,`bikes.city_landing` — これから買う
- 輸入バイクLP `bikes.overseas` — これから買う
- 排気量別LP `bikes.category_cc` — これから買う
- タイプ別LP `bikes.category_type` — これから買う
- 車種比較 `bikes.model_compare` — これから買う
- 地域差相場 `bikes.region_price` — これから買う
- メーカーハブ `bikes.manufacturer_hub` — これから買う
- メーカー×排気量 `bikes.category_displacement` — これから買う
- レビュー一覧 `bikes.reviews_index` — 乗ってる/これから買う
- トレンド `bikes.trends` — バイク好き
- タイヤサイズ `bikes.tire_size.index/show` — 乗ってる
- 車種判定AI `bikes.identify` — バイク好き
- ウィッシュリスト/比較 `wishlist`,`bikes.compare` — これから買う
- ショップ検索/詳細 `shops.search`,`shops.show`,`shops.chain` — 売りたい/買う
- ショップエリア `shops.area.*` — 売りたい
- 修理ショップ `shops.repair.*` — 乗ってる
- ショップ投稿 `shops.submit.*` — 店舗運営者
- ライダーズマップ `riders.map` — ツーリング
- 駐車場マップ/詳細 `parking.index`,`parking.show` — 停める/ツーリング
- 駐車場エリア `parking.area.*` — 停める
- 駅別駐車場 `parking.station.*` — 停める
- レンタルガレージ `rental-garage.show`,`.area.*` — 乗ってる/ツーリング
- レンタルバイク `rental-bike.index/show/prefecture/price` — ツーリング
- 道の駅 `michinoeki.index/area/show` — ツーリング
- ガソリンスタンド `gs.*` — ツーリング
- コンビニ `konbini.*` — ツーリング
- 洗車場 `senshajo.*` — 乗ってる/ツーリング
- ARファインダー `ar.index` — ツーリング
- パーツ検索/比較 `parts.index/search/compare/category` — 乗ってる
- ニュース `news.index/show/model` — バイク好き
- ブログ `blog.index/show/tag/series` — 乗ってる/バイク好き
- ツーリングガイド/スポット `touring.*` — ツーリング
- ランキング `ranking.*` — これから買う/バイク好き
- 特集 `features.index/show` — これから買う
- 保険ハブ `hoken` — 乗ってる
- 盗難データ `theft` — 乗ってる/これから買う
- 相場ハブ `market` — 買う/乗ってる
- 新基準原付 `shinkijun_gentsuki` — これから買う
- 免許ガイド/教習所 `license.*` — これから買う
- 公開ガレージ/プロフィール `garage.public.*`,`riders.profile` — 乗ってる
- メンテ情報 `fitments.show` — 乗ってる
- 診断ツール `shindan.*` — これから買う
- トラブル診断 `trouble.index` — 乗ってる
- 買取査定LP `sell.index` — 売りたい
- AIスマート検索 `ai-search` — これから買う
- ゲーム類 `quiz`,`puzzle`,`warashibe`,`games.*` — バイク好き
- 固定ページ `pages.*`,`data` — 全員

</details>

---

## 2. 既存アフィリエイト・広告の現状（棚卸し）

**PR表示・rel・計測・config ゲートの有無**を全件確認した。

| 掲載 | ASP | 置き場所（ファイル） | ページ種類 | PR/広告表示 | rel | GA4計測 | configゲート |
|---|---|---|---|---|---|---|---|
| バイクワン | A8 | `pages/sell.blade.php:215` | 買取相場(/sell) | ❌ なし（"提携:"のみ） | `nofollow`（**sponsored無し**） | ❌ なし | ❌ ハードコード |
| バイクBOON | A8 | `pages/sell.blade.php:228` | 買取相場(/sell) | ❌ なし | `nofollow`（**sponsored無し**） | ❌ なし | ❌ ハードコード |
| akippa（予約駐車） | A8 | `parking/partials/akippa-cta.blade.php` | 駐車場詳細 | ✅ PR・広告 | `nofollow sponsored noopener` | ✅ `outbound_parking_click`(partner,position,parking_id,prefecture) | ✅ `config/parking.php` `AKIPPA_A8MAT` |
| ZuttoRide（盗難保険） | 自社直/moshimo | `components/theft-insurance-cta.blade.php`, `theft.blade.php` | 盗難(/theft) | ✅ PR・広告 | `nofollow sponsored noopener` | ❌ なし | ✅ `config/theft.php` `ZUTTORIDE_AFFILIATE_URL`（**現在URL未設定＝非表示**） |
| 任意保険一括見積 | 未定 | `hoken.blade.php` | 保険(/hoken) | ✅ PR | `nofollow sponsored noopener` | ❌ なし | ✅ `config/insurance.php` `INSURANCE_AFFILIATE_URL`（**承認待ち＝非表示**） |
| バッテリー救援 | A8 | `trouble/index.blade.php:184` | トラブル診断 | ✅ PR | `nofollow sponsored noopener` | △ `trackCta('battery_rescue')` | ✅ `config/diagnosis.php` `BATTERY_RESCUE_AFFILIATE_URL` |
| Google AdSense | Google | `components/ads/unit.blade.php`, `blog/show.blade.php:232` | ブログ（parking/bike_model枠は未使用） | ✅ 広告 | — | — | ✅ `config/adsense.php`（enabled+client+slot の三重ゲート） |
| 楽天/Yahoo/Amazon | 各API | `parts/compare.blade.php` 他 | パーツ比較 | ✅ PR・広告 | `noopener noreferrer`（**nofollow/sponsored無し**） | ❌ なし | ❌ DB駆動 |
| オイル/タイヤ用品 | 用品アフィリ | `bikes/partials/maintenance-oil.blade.php`,`-tire.blade.php` | 車種詳細 | ✅ PR・広告 | `nofollow sponsored noopener` | ❌ なし | ❌ DB駆動 |
| 中古車送客（自社） | 自社 | `bikes/show.blade.php:136` | 中古車詳細 | — | — | ✅ `outbound_listing_click`(source,position,listing_id,bike_model_id,bike_model_name) | — |
| レンタルガレージ送客 | 自社 | `/go/rental-garage/{id}` → `RentalGarageClickController` | レンタルガレージ | — | — | ✅ **サーバ側302＋DB記録**（IP非保存・bot除外） | — |

### 現状からの重要な発見

1. **② ZuttoRide と ④ インズウェブ は、置き場所の土台がすでにある。**
   `config/theft.php`（ZuttoRide）と `config/insurance.php`（インズウェブ想定）は「URL未設定なら枠ごと非表示、URLを入れればコード変更なしで表示」という設計で既に実装済み。既存は別ASP想定（moshimo/直）だが、**レントラックスの発行URLを env に入れるだけで表示できる**（＋rel/PR表示/非表示ロジックは流用）。
2. **① KATIX・③ バイクランド の置き場所 `/sell` には既にバイクワン＋バイクBOONの2枠がある。** 4社並べると「1枠3つまで」を超える → **入れ替え or 比較枠の整理**が必要（§3,§5）。
3. **GA4計測が全アフィリで統一されていない。** akippa だけ `outbound_parking_click`。中古車は `outbound_listing_click`。保険系・買取系は無計測。→ **`outbound_affiliate_click(partner, position, page_type)` に統一**すべき（§6）。

---

## 3. ページ × 案件 配置表（提案）

◎=第一候補（まず置く）／○=第二候補／△=余力があれば。

| 案件＼ページ | /sell 買取相場 | /market 相場 | 車種詳細 | /theft 盗難 | /hoken 保険 | /parking 駐車場 | /license 免許 | /touring ツーリング | /michinoeki 道の駅 | /rental-bikes |
|---|---|---|---|---|---|---|---|---|---|---|
| **① KATIX（買取）** | ◎ | ○ | △ | | | | | | | |
| **③ バイクランド（買取）** | ◎ | ○ | △ | | | | | | | |
| **② ZuttoRide（盗難/ロード）** | | | ○(購入検討) | ◎(既存) | ○ | ◎(盗難) | | ○(ロード) | ○(ロード) | ○ |
| **④ インズウェブ（任意保険）** | | | ○(維持費) | | ◎(既存枠) | | ○(免許→保険) | | | △ |
| **⑤ JAF（ロードサービス）** | | | | | | | | ◎ | ◎ | ○ |

### 各案件の第一候補と理由（1〜2行）

- **① KATIX → `/sell`（◎）**: 「自分のバイクがいくらで売れるか」を試算し終えた直後の、最も売却意欲が高い瞬間。KATIXは**画像査定＝非対面**で、出張査定のバイクワン/BOON/バイクランドと差別化軸が立つ（「電話や訪問が面倒な人向け」）。報酬4,400円＝売却完了まで必要なので、意欲最大のこの位置が最適。
- **③ バイクランド → `/sell`（◎）**: 同じく売却意欲の頂点。**成果条件が「査定のみ（成約不問）」**でハードルが最も低く、置けば成果化しやすい。大手・出張の王道枠として KATIX（非対面）と役割が被らない。
- **② ZuttoRide → `/parking`詳細（◎・新規）と `/theft`（◎・既存）**: 盗難保険は「停める場所を気にしている人」と直結。駐車場詳細は akippa 1枠のみなので余白があり、盗難保険を並べても自然（§5）。ロードサービス面はツーリング系にも展開可。
- **④ インズウェブ → `/hoken`（◎・既存枠）**: 保険料相場表の直後に「自分の条件だといくら？」の既存CTA設計があり、**インズウェブ想定の config がそのまま使える**。見積もりのみで成果＝ハードル低。
- **⑤ JAF → `/touring`・`/michinoeki`（◎）**: 出先での安心（ロードサービス・会員優待）がツーリング文脈に直結。これらのページは現状アフィリ空白地帯でCTA過多の心配がない。

---

## 4. ジャンル重複ページの扱い（追加／入れ替え／並べて比べる）

| ページ | 既存 | 新規候補 | 判断 | 根拠 |
|---|---|---|---|---|
| **/sell 買取相場** | バイクワン・バイクBOON（2枠・A8） | ① KATIX・③ バイクランド | **並べて比べる（比較枠に整理）** | 買取は相見積もりが定石で複数社提示が自然。ただし4社＋既存2=多すぎ。**「買取比較」1枠に最大3社まで**に絞る（§5の案）。軸を分ける: KATIX=画像/非対面, バイクランド=大手出張(査定のみで成果), バイクワン=カスタム/改造, BOON=旧車/大型。報酬×成果ハードルで3社を選定。 |
| **/theft 盗難** | ZuttoRide（既存・URL未設定で非表示） | ② ZuttoRide（レントラックス） | **入れ替え（URL差し替え）** | 同一ブランド。既存の自社/moshimo想定URLをレントラックス発行URLに差し替えるだけ。二重掲載は不可。 |
| **/hoken 保険** | 任意保険一括見積（config枠・承認待ち） | ④ インズウェブ | **入れ替え（URLを確定）** | 既存枠はまさに一括見積もり用。インズウェブのURL/provider名を入れて枠を有効化。 |
| **/parking 駐車場詳細** | akippa（予約） | ② ZuttoRide（盗難保険） | **追加** | ジャンルが違う（予約 vs 盗難保険）。akippa+ZuttoRideで2枠＝3以内。停める×盗難で文脈一致。 |
| **/touring・/michinoeki** | なし | ⑤ JAF（＋② ZuttoRideロード） | **追加** | 空白地帯。JAFを主、ZuttoRideロードを従で最大2枠。 |
| **車種詳細** | オイル/タイヤ用品・akippa・中古車送客 | ①③（リセール）・②④（維持費/購入直後）補助 | **原則追加しない（△）** | 既にCTAが多い（§5で過密ページに分類）。置くなら既存を削ってから。 |

---

## 5. 1ページあたりのCTA数チェック（同一枠 最大3）

| ページ | 現状CTA | 提案後 | 判定 |
|---|---|---|---|
| **/sell** | バイクワン, BOON（買取2） | 買取比較枠を**最大3社**に整理（例: KATIX / バイクランド / バイクワン）。BOONは落とすか軸が被らなければ別枠検討 | ⚠️ **要整理**（4社並列は不可） |
| **/theft** | ZuttoRide（0=非表示中） | ZuttoRide（1） | ✅ |
| **/hoken** | 一括見積（0=承認待ち） | インズウェブ（1） | ✅ |
| **/parking 詳細** | akippa（1）＋AdSense parking枠（未使用） | akippa＋ZuttoRide（2）。AdSense parking枠を使うなら合計3で上限 | ✅（AdSenseと同時なら上限ちょうど） |
| **/touring, /michinoeki** | 0 | JAF（1）＋ZuttoRideロード（1） | ✅ |
| **車種詳細** | オイル・タイヤ・akippa・中古車送客（既に3〜4相当） | 追加しない | ⚠️ **過密**。新規は原則置かない |

**/sell の整理案（推奨）**: 買取は「比較」が価値なので、**1つの"買取査定を比べる"ブロックに最大3カード**。報酬×成果ハードルで選ぶと **③ バイクランド（査定のみ・3,565円）→ ① KATIX（画像/非対面・4,400円）→ バイクワン（カスタム対応）** の3枚が有力。バイクBOON（旧車/大型）は車種詳細が旧車/大型のときだけ出す等の出し分けも検討（ただし薄い出し分けは避ける）。

---

## 6. 直したほうがいい点（既存の不備）

1. **`/sell` のバイクワン/BOON に PR・広告表示がない。** 「提携:」表記のみでは広告表示として弱い。→ **「PR・広告」ラベルを明示**（他ページの `theft-insurance-cta` と表記を揃える）。
2. **`/sell` のバイクワン/BOON の rel に `sponsored` がない**（`nofollow` のみ）。→ **`rel="nofollow sponsored noopener"` に統一**。
3. **パーツ比較（楽天/Yahoo/Amazon）の rel が `noopener noreferrer` のみ**（`nofollow`/`sponsored` 無し）。→ アフィリリンクである以上 **`nofollow sponsored` を付与**。
4. **GA4計測がアフィリ全体で不統一。** akippaのみ `outbound_parking_click`、買取・保険系は無計測。→ **全アフィリCTAを `outbound_affiliate_click(partner, position, page_type)` に統一**（下記）。既存 `outbound_parking_click`/`outbound_listing_click` は当面併存でよいが、新規は統一イベントに寄せる。
5. **ハードコードURL（/sell のA8リンク）が config 管理外。** → 新規5案件は **`config/affiliate.php`（新設）で案件ごとに ON/OFF** できるようにし、将来は /sell も寄せる。

---

## 7. 実装するときの前提（本PRでは書かない。実装PR用メモ）

既存の `theft-insurance-cta` / `akippa-cta` / `config/theft.php` のパターンを踏襲する。**新しい仕組みはほぼ要らない**（土台がある）。

- **広告開示**: 各CTAに「PR」または「広告」を明示（既存の `PR・広告` 表記に合わせる）。
- **rel**: 外部アフィリリンクは `rel="nofollow sponsored noopener"`。
- **config でON/OFF（案件ごと）**: `config/affiliate.php` を新設し、案件キー（`katix`,`bikeland`,`zuttoride`,`insweb`,`jaf`）ごとに `url`（env）・`provider`・`headline`・`enabled`。**url 未設定なら枠ごと非表示**（偽ボタンを置かない。既存 `theft.affiliate` と同型）。既存の ZuttoRide/インズウェブ config は新 config に寄せるか、当面そのまま URL 差し替えでも可。
- **GA4**: クリックで `gtag('event', 'outbound_affiliate_click', { partner, position, page_type })` を送る。値は**必ず `<a>` の `data-*` 属性から読み、`dataset` で取得**（`data-partner` / `data-position` / `data-page-type`）。サーバ側DB記録は不要（レンタルガレージの `/go/` 方式は使わない。ASPが成果計測する）。
- **inline JS に `{{ }}` を書かない**（Bladeが空echo→e()で500になる既知事故。`parking/show.blade.php` の計測scriptにも同じ注意書きあり）。値は data-* 属性へ出して dataset で読む。
- **Webikeの画像は使わない**（掲載停止要請済み）。そもそも本件は外部画像を扱わない。
- **薄い出し分けページを量産しない**（1〜2件しか出ないCTA出し分けは避ける）。

### 統一イベント `outbound_affiliate_click` のパラメータ例

| param | 値の例 | 意味 |
|---|---|---|
| `partner` | `katix` / `bikeland` / `zuttoride` / `insweb` / `jaf` | 案件識別 |
| `position` | `sell_compare` / `parking_detail` / `hoken_after_table` / `touring_body` | 枠の位置 |
| `page_type` | `sell` / `parking` / `hoken` / `touring` / `michinoeki` | ページ種類 |

---

## 8. 次アクション（提案）

1. 本提案のレビュー → 配置とCTA整理方針（特に /sell の3社選定）を確定。
2. レントラックスの各案件 発行URL（計測パラメータ付き）を受領 → env へ。
3. 実装PR: `config/affiliate.php` 新設 → 共通CTAコンポーネント（`theft-insurance-cta` を一般化）→ /sell 整理・/parking・/hoken・/touring・/michinoeki へ差し込み → `outbound_affiliate_click` 計測。
4. GA4 で `outbound_affiliate_click` を partner×page_type で見られるよう探索レポート作成。
