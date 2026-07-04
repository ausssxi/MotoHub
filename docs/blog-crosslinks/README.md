# トラブル記事5本 診断ファネル接続（下書き提案）

66番（gentsuki-battery）で内田が確定した「型」に揃えて、残り5本へ診断ツール・
パーツ比較・整備店検索への内部リンクを挿入した**提案本文**です。

## 方式（重要）

- **本文出力のみ**。DB書き込み・下書きレコード作成・公開記事の上書きは**一切していない**。
- このフォルダの各 `*.md` が「挿入後の完全な本文（Markdown）」。内田が管理画面の記事編集で
  差分を確認しながら反映してください。
- **⚠️ ベーステキストはローカルDBの現行本文**です。66番のローカルDBには内田の
  クロスリンク編集が入っていない（＝本番とローカルで本文が乖離している）ことを確認済み。
  5本についても、貼り付ける前に**本番の現行本文と照合**し、下の「挿入ログ」に列挙した
  **追加分だけを適用**するのが安全です（プレーンな全文差し替えは本番の微修正を上書きし得る）。
- リンクは `config/diagnosis.php` を single source として導出（今回 config は変更していない）。

## 挿入の原則（66の型）

- blockquote（`>`）＋絵文字で「本文でない導線」を視覚分離。地の文を汚さない。
- 新見出しは作らず、既存セクションに溶かす。各リンクは1回ずつ。
- 診断リンクは**リード直後の1箇所のみ**。パーツリンクは「買う気になった箇所」に置く。
- URL形式は診断ツールと一致：`/trouble?symptom=<slug>`／`/parts/compare?keyword=<kw>`
  （キーワードの空白は `%20`）／`/shops/repair`／`/shops/submit`。

## 対象5本と挿入内容

| # | slug | 診断リンク | パーツリンク | 店/投稿リンク |
|---|---|---|---|---|
| 65 | gentsuki-engine-wont-start | 🔑 `/trouble?symptom=engine-wont-start` | **省略**（原因が多岐で単一パーツに絞れない・66へ誘導済み） | `/shops/repair` |
| 63 | gyro-canopy-idle-stall | 🛑 `/trouble?symptom=stalling` | フューエルワン `バイク 燃料添加剤 フューエルワン` | `/shops/repair` ＋ `/shops/submit` |
| 67 | gentsuki-puncture | 🛞 `/trouble?symptom=puncture` | パンク修理キット `バイク パンク修理キット` | `/shops/repair` ＋ `/shops/submit` |
| 68 | gentsuki-acceleration | 🐌 `/trouble?symptom=no-accel` | 駆動系 `バイク ドライブベルト ウェイトローラー` | `/shops/repair` |
| 69 | gentsuki-winter-wont-start | ❄️ `/trouble?symptom=winter` | バッテリー `バイク バッテリー` | `/shops/repair` |

## 記事別 挿入ログ（＝本番へ適用すべき追加分）

### 65 gentsuki-engine-wont-start
1. リード直後に診断blockquote（🔑）。
2. URL化：「自分で試せること」の「（→「信号待ちで止まる」記事もあわせてどうぞ）」を
   `[信号待ちで止まる](/blog/gyro-canopy-idle-stall)` にリンク化。
3. パーツリンク：**省略**。原因がキルスイッチ/ガス欠/プラグ/バッテリー/セルと多岐で、
   単一パーツに絞ると不自然。バッテリー購買導線は66番に集約済みのため二重掲載を避けた。
4. 「ここから先はお店（または上級者）の領域」末尾に `→ [近くのバイク整備・修理店を探す](/shops/repair)`。

### 63 gyro-canopy-idle-stall
1. リード直後に診断blockquote（🛑）。
2. パーツリンク：「軽い詰まりなら『フューエルワン』を試す」導入直後に燃料添加剤の比較blockquote
   （まさに買う気になる箇所）。
3. 「キャブ清掃・オーバーホールの領域」末尾に `/shops/repair` ＋ `/shops/submit`
   （旧車キャブ整備は店を選ぶ＝修理難民導線が効く記事）。

### 67 gentsuki-puncture
1. リード直後に診断blockquote（🛞）。
2. パーツリンク：「自分で直す」のパンク修理キット記述の直後に比較blockquote。
3. 「修理を頼める場所」末尾に `/shops/repair` ＋ `/shops/submit`（出先トラブル＝店探し文脈が強い）。

### 68 gentsuki-acceleration
1. リード直後に診断blockquote（🐌）。
2. URL化：「原因④：燃料系」の「（→「信号待ちで止まる」の記事もあわせて）」の `(#)` を
   `/blog/gyro-canopy-idle-stall` に修正。
3. パーツリンク：「原因①：駆動系の摩耗」の費用目安直後に駆動系パーツの比較blockquote。
4. 「自分でやる？店に任せる？」末尾に `/shops/repair`。

### 69 gentsuki-winter-wont-start
1. リード直後に診断blockquote（❄️）。
2. URL化（`(#)`・未リンク参照の修正）：
   - リード「原付のエンジンがかからない」→ `/blog/gentsuki-engine-wont-start`
   - 始動手順1「エンジンがかからない」記事へ → `/blog/gentsuki-engine-wont-start`
   - 冬のチェック「原付のバッテリー上がり・交換」→ `/blog/gentsuki-battery`
3. パーツリンク：「乗る前にできる冬対策」のバッテリー寿命の記述直後にバッテリー比較blockquote
   （冬の主犯＝バッテリー）。
4. 「ここから先はお店（または上級者）の領域」末尾に `/shops/repair`。

## 本番反映（内田）

- コード変更・migration・新ルートは**無し**（記事本文だけ）。
- 5記事を管理画面（`/admin/blog/posts/{id}/edit`）で開き、上記「挿入ログ」の追加分を反映して保存。
  記事はDB管理のため、保存＝即反映（`./deploy-blog.sh` 等のデプロイは不要）。
- 反映後の確認：各記事で冒頭の診断リンク→`/trouble?symptom=` が該当症状を開く／
  パーツリンク→比較結果が出る／店リンク→`/shops/repair`。

---

# 第2弾：残りトラブル記事の接続（64/70/71/72/77）

feat/trouble-symptoms-expand で追加。新症状 lights/stranded（config/diagnosis.php）に
伴い、孤児カード（headlight/seizure/roadside/oil）の対応記事も診断へ接続する。
**方式は第1弾と同じ：本文出力のみ・DB書き込みなし。** 内田が管理画面で反映。

## ⚠️ ベース乖離（第1弾より重要）

- **64/70 には内田が本番で挿入済みのアンカー行 `<a id="fix"></a>` がある**。ローカルDB本文には無い。
  → このフォルダの 64/70 の提案本文には**アンカー行を含めた状態**で出力済み（全文貼りで
    アンカーが消える事故を防ぐため）。貼る前にアンカー行があることを目視確認。
- **77（修理難民）はローカルDBに存在しない**（本番のみ）。全文出力できないため
  `77-repair-refugees-INSTRUCTIONS.md` に挿入指示のみ出力。本番現行本文に適用のこと。
- 71/72 は未編集想定。貼付前に本番現行本文と照合し、挿入ログの追加分だけ適用も可。

## 記事別 挿入ログ

### 64 gentsuki-oil-change（card: oil）
- 診断リンク：**なし**（oilは単独症状が無く、無理に貼らない方針）。
- アンカー：`<a id="fix"></a>` を `## 自分でやる場合の手順（4スト・ざっくり）` の直前に（本番反映済みの再掲）。
- パーツ：`## 自分でやる場合の手順` の「用意するもの」直後にエンジンオイル比較blockquote。
- 店：`## 結論：原付は「自分」と「店」どっち？` 内に `/shops/repair`。

### 70 gentsuki-headlight（card: headlight）
- 診断リンク：リード直後に 💡 blockquote → `/trouble?symptom=lights`。
- アンカー：`<a id="fix"></a>` を `## 交換手順（ライブDIOを例に）` の直前に（本番反映済みの再掲）。
- パーツ：`## 用意するもの` 直後にヘッドライトバルブ比較blockquote。
- 店：`## こんな時は店・プロへ` に `/shops/repair`。
- 備考：既存の「まとめ」内 `[症状診断（無料）](/trouble)` は著者本文なので残置（冒頭blockquoteが主導線）。

### 71 gentsuki-seizure（card: seizure）
- 診断リンク：リード直後に 🆘 blockquote → `/trouble?symptom=stranded`。
- パーツ：**省略**（防ぎ方はオイル管理だが、既に `[原付のオイル交換ガイド](/blog/gentsuki-oil-change)`
  へ誘導済みで二重にオイルを売らない。迷ったら省略の原則）。
- 店：`## こんな時は店・プロへ` に `/shops/repair` ＋ `/shops/submit`（重整備＝店を選ぶ）。

### 72 gentsuki-roadside（card: roadside）
- 診断リンク：リード直後に 🆘 blockquote → `/trouble?symptom=stranded`。
- パーツ：なし。
- 店：`## 動かなくなったら、確認する順番`（3で「バイク店・レッカー」に言及）の直後に
  `/shops/repair` ＋ `/shops/submit` のblockquote（出先＝知らない土地の店探し＝修理難民文脈）。

### 77 修理難民（slug: 1kd4liw5nbads3）※旗艦
- `77-repair-refugees-INSTRUCTIONS.md` 参照。整備店検索のリンク化＋店名検索導線＋
  受け入れ情報への言及＋末尾に投稿CTA（`/shops/submit`）。診断・パーツリンクは入れない。

## 本番反映（内田）

- 記事本文：上記5本を管理画面で反映（64/70はアンカー行が含まれることを目視確認してから貼る）。
- あわせて config/diagnosis.php の新症状 lights/stranded が入るので **`php artisan config:cache` 必須**
  （このコマンド反映は feat/trouble-symptoms-expand のコード側デプロイに含む）。
