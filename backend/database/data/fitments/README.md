# 適合表マスタCSV（fitments）

`php artisan fitments:import <path>` で取り込む、人手キュレーション済みの一次成果物。
**適合検索サイト（GSユアサ・NGK等）へのスクレイピングは禁止**。データは人間が公開適合表を
確認して手作業で作成し、出典を必ず記録する。

## 運用

- 修正は「CSVを直してコミット → 再インポート」で行う（管理UIは無い）。
- 取り込みは**モデル×task単位の全置換**＝CSVが常にsource of truth・完全冪等。
- **verified_at が入った行のみ公開**される（未検証行は取り込むが非公開）。
- 反映前に必ず `--dry-run` で delete/insert 件数・skip・警告・slug設定予定を確認する。

## 列（16列・順序固定・UTF-8・ヘッダ行あり）

| 列 | 説明 |
|---|---|
| bike_model_id | 対象車種のID |
| model_name_check | `bike_models.name` と完全一致必須（ID誤記による誤適合の防止） |
| model_slug | 公開URL用スラッグ `^[a-z0-9]+(-[a-z0-9]+)*$`。空slugのモデルにのみ設定 |
| task | `battery` 等（config/fitments.php の tasks キー） |
| frame_code | 型式 `AF62`。区別なしは空 |
| year_range | 年式表記そのまま `04.2〜07` |
| oem_part_no | 新車搭載品番（任意） |
| recommended_part_no | 市販推奨品番（**必須**） |
| compatibles | `ブランド:品番\|ブランド:品番`（任意） |
| spec | `voltage=12V;capacity=4Ah;type=VRLA`（任意） |
| source_1_name / source_1_url | 出典1（推奨） |
| source_2_name / source_2_url | 出典2（任意） |
| verified_at | `YYYY-MM-DD`。**空なら非公開** |
| note | 備考 `傾斜搭載のため液入充電済必須` 等 |

`TEMPLATE.csv` はヘッダのみのひな形。実データCSVは `fitments_YYYYMMDD.csv` の名前で置く。
