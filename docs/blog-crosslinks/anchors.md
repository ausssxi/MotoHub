# 記事セクション直行アンカー 挿入リスト（内田の管理画面作業用）

診断結果→記事リンクを「記事トップ」ではなく該当セクションへ直行させるための
アンカーを、各記事本文に1行ずつ挿す作業リストです。クロスリンク反映と同じ運用
（管理画面で該当見出しの**直前の行**に貼って保存・DB即反映）。まとめて実施可。

## 前提（確認済み）

- ブログのMarkdownレンダラ（league/commonmark）は**インラインHTMLを許可**。
  `<a id="fix"></a>` はそのまま `<a id="fix">` として出力される（検証済み）。
  → 見出しテキスト由来の自動ID（日本語だとレンダラ依存で壊れやすい）は使わず、
    **短い英語の安定ID**を明示アンカーで付与する方針。
- アンカーは同一記事内で一意。記事をまたいで同じ `fix` を使ってよい（各記事に別々の `#fix`）。
- 着地がスクロール追従ヘッダーに隠れない対策として、`blog/show.blade.php` に
  `scroll-margin-top: 84px`（h2/h3/[id]）を追加済み。記事側の作業は下記アンカー挿入のみ。

## 挿入リスト（この見出しの「直前の行」に1行貼る）

| 記事ID | slug | 挿入する1行 | 挿入位置（この見出しの直前） | 対応card |
|---|---|---|---|---|
| 66 | gentsuki-battery | `<a id="fix"></a>` | `## 自分で交換する手順（原付は比較的かんたん）` | battery |
| 67 | gentsuki-puncture | `<a id="fix"></a>` | `## 自分で直す（チューブレスなら応急はかんたん）` | tire |
| 63 | gyro-canopy-idle-stall | `<a id="additive"></a>` | `## 軽い詰まりなら「フューエルワン」を試す` | fuel_carb |
| 68 | gentsuki-acceleration | `<a id="drivetrain"></a>` | `## 原因①：駆動系の摩耗（スクーターで一番多い）` | drivetrain |
| 68 | gentsuki-acceleration | `<a id="air-filter"></a>` | `## 原因②：エアクリーナーの詰まり（良い空気）` | air_filter |
| 69 | gentsuki-winter-wont-start | `<a id="care"></a>` | `## 乗る前にできる冬対策（予防がいちばん効く）` | cold |
| 64 | gentsuki-oil-change | `<a id="fix"></a>` | `## 自分でやる場合の手順（4スト・ざっくり）` | oil |
| 70 | gentsuki-headlight | `<a id="fix"></a>` | `## 交換手順（ライブDIOを例に）` | headlight |

※ 貼り方例（該当見出しの直前に空行を挟んでアンカー行を置く）:
```
<a id="fix"></a>

## 自分で交換する手順（原付は比較的かんたん）
```

## アンカーを付けなかったカード（意図的）

- plug / switch / gas_empty / starter（→ 65 は診断ハブで単一の該当セクションが無い）
- seizure / roadside / unknown（該当セクションが無い・曖昧）
→ これらは従来どおり記事トップに着地（`config/diagnosis.php` に `article_anchor` を付けていない）。

## 反映後の確認

- 診断でバッテリー判定→記事リンクが `/blog/gentsuki-battery#fix` を開き、
  「自分で交換する手順」に直行着地する（ヘッダーに隠れない）。
- アンカー未設定カード（例: プラグ）は従来どおり記事トップに着地。
