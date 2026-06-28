# ブログ内部リンク A-2 チェックリスト（本文内 文脈リンク・管理画面で手動追加）

トピッククラスター施策（`config/blog_clusters.php` ＋ `<x-blog-related>`）の **A-2 分**。
関連記事ブロック（B）は自動表示済み。本リストは **本文中の文脈内リンク**を、管理画面の
記事編集（Markdown 本文）で手動追加するためのもの。

## 追加ルール
- 形式: `[アンカーテキスト](/blog/対象slug)`
- アンカーは「こちら」等の汎用語にせず、**リンク先の内容を表す語**にする（SEO効果）。
- 各記事 **1〜2本まで**。本文中で初めてその話題に触れる箇所に自然に差し込む。
- 文意を壊さない。過剰なリンク詰め込みはしない。

---

## 親記事（pillar）→ 主要な子記事

- [ ] `125cc-all-models-comparison-2026` → `/blog/cygnus-x-price-surge-2026-05`
      アンカー: **シグナスXが125ccスクーター市場で逆行高となった理由**
- [ ] `250cc-all-models-comparison-2026` → `/blog/rebel250-vs-gb350-2026`
      アンカー: **レブル250とGB350の徹底比較**
- [ ] `250cc-all-models-comparison-2026` → `/blog/best-bikes-for-beginners-2026`
      アンカー: **13万台のデータで選ぶ初心者おすすめ20選**
- [ ] `400cc-all-models-comparison-2026` → `/blog/middleweight-price-surge-2026-05`
      アンカー: **401〜750ccクラスで起きた相場の異変**
- [ ] `bike-market-forecast-summer-2026` → `/blog/market-report-2026-05`
      アンカー: **直近（5月）の中古バイク相場レポート**
- [ ] `bike-market-forecast-summer-2026` → `/blog/best-deals-bikes-2026-05`
      アンカー: **値下がりランキングTOP10**
- [ ] `used-bike-buying-guide-2026` → `/blog/fastest-selling-bikes-2026`
      アンカー: **掲載30日で完売する売れ筋バイク**
- [ ] `rare-discontinued-used-bikes-2026` → `/blog/z900rs-premium-price-2026`
      アンカー: **Z900RSが150万円超えとなった理由**
- [ ] `dmrn95nkr5ru0g`（排気量別維持費） → `/blog/big-bike-annual-cost-comparison-2026`
      アンカー: **大型バイクの維持費の実コスト**

## 子記事 → 親記事（pillar）

- [ ] `rebel250-vs-gb350-2026` → `/blog/250cc-all-models-comparison-2026`
      アンカー: **250ccバイク全車種の実売データ比較**
- [ ] `rebel250-vs-gb350-2026` → `/blog/bike-market-forecast-summer-2026`
      アンカー: **2026年夏の中古バイク相場予測**
- [ ] `cygnus-x-price-surge-2026-05` → `/blog/125cc-all-models-comparison-2026`
      アンカー: **125ccバイク全車種の実売データ比較**
- [ ] `super-cub-50-after-discontinuation-2026` → `/blog/125cc-all-models-comparison-2026`
      アンカー: **125ccクラス全車種の比較**
- [ ] `middleweight-price-surge-2026-05` → `/blog/400cc-all-models-comparison-2026`
      アンカー: **400ccバイク全車種の実売データ比較**
- [ ] `z900rs-premium-price-2026` → `/blog/rare-discontinued-used-bikes-2026`
      アンカー: **絶版・希少バイクの中古相場まとめ**
- [ ] `best-deals-bikes-2026-05` → `/blog/used-bike-buying-guide-2026`
      アンカー: **データで分かる損しない買い方**
- [ ] `fastest-selling-bikes-2026` → `/blog/used-bike-buying-guide-2026`
      アンカー: **損しない買い方の5つのポイント**
- [ ] `best-bikes-for-beginners-2026` → `/blog/250cc-all-models-comparison-2026`
      アンカー: **250ccバイク全車種比較**
- [ ] `big-bike-annual-cost-comparison-2026` → `/blog/dmrn95nkr5ru0g`
      アンカー: **排気量別の維持費シミュレーション**
- [ ] `harley-vintage-discontinued-used-guide-2026` → `/blog/rare-discontinued-used-bikes-2026`
      アンカー: **絶版・希少バイクの中古まとめ10選**
- [ ] `market-report-2026-03` → `/blog/bike-market-forecast-summer-2026`
      アンカー: **2026年夏の相場予測**
- [ ] `market-report-2026-04` → `/blog/bike-market-forecast-summer-2026`
      アンカー: **2026年夏の相場予測**
- [ ] `market-report-2026-05` → `/blog/bike-market-forecast-summer-2026`
      アンカー: **2026年夏の相場予測**
- [ ] `gwdiu2f187niqv`（特攻の拓） → `/blog/rare-discontinued-used-bikes-2026`
      アンカー: **絶版・旧車の中古相場まとめ**
- [ ] `ehvgrc2cjs8vrh`（東京リベンジャーズ） → `/blog/rare-discontinued-used-bikes-2026`
      アンカー: **絶版・旧車の中古相場まとめ**
- [ ] `irzp9vam18uvxc`（ばくおん!!） → `/blog/rare-discontinued-used-bikes-2026`
      アンカー: **絶版・旧車の中古相場まとめ**

---

参考: クラスタ定義の正本は `config/blog_clusters.php`。関連記事ブロックの表示ロジックは
`resources/views/components/blog-related.blade.php`。
