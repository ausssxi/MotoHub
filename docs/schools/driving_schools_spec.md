# 実装仕様書：`/license/schools`（二輪教習が受けられる指定自動車教習所一覧）

宛先：Claude Code（ローカル WSL の `~/dev/motohub` で作業する想定）
作成：Claude（Cowork 側。調査・データ・原稿担当）
日付：2026-07-26

---

## 0. この文書の使い方

この仕様書のとおりに実装してください。**仕様に書かれていないデータ（教習所名・URL・料金・二輪対応の有無）を、あなたが推測して追加してはいけません。** データは同梱の CSV だけが正です。

作業は本番サーバーではなく、**ローカルの `~/dev/motohub` でのみ**行ってください。`git push` はしないでください。実装が終わったら内容を報告し、人間（内田）が確認してから本番に反映します。

---

## 1. 絶対に守るルール

1. **捏造禁止。** 教習所名・所在地・公式URL・普通二輪/大型二輪の対応可否は、同梱 CSV の値以外を絶対に書かない。「たぶんこの学校も二輪やってるだろう」で行を足さない。サンプルデータ・ダミーデータも作らない。
2. **スクレイピング禁止。** 教習所検索サイト等を巡回してデータを集めない。データ収集は Cowork 側（人間＋Claude）の担当。
3. **`verified_at` が NULL の行は絶対に画面に出さない。** これが人手確認ゲート。実装のあらゆる取得箇所で `whereNotNull('verified_at')` を通す。
4. **料金は v1 では一切扱わない。** カラムも作らない、画面にも出さない。教習料金は各校が個別に設定しており全国統計が存在しないため、後から確認できた校だけ別途載せる。
5. **DB を書き換える前に必ずドライラン。** `php artisan migrate --pretend` を先に実行して SQL を確認する。CSV 取り込みは `--dry-run` を先に実行し、新規/更新/変更なしの件数を出してから本実行する。
6. **既存の `/trouble`（症状診断）と `/license/{class}` の中身は変更しない。** 今回は追加のみ。

---

## 2. スコープ

つくるもの：

- `/license/schools` … 都道府県の選択ページ
- `/license/schools/{prefecture_slug}` … 例 `/license/schools/kanagawa`

載せるもの：**普通二輪または大型二輪の教習を行っている指定自動車教習所だけ**。四輪のみの教習所は載せない（バイクサイトなので）。

つくらないもの：料金、合宿、口コミ、予約導線、地図。

---

## 3. マイグレーション

ファイル：`backend/database/migrations/2026_07_26_000001_create_driving_schools_table.php`

以下をそのまま作成してください（検証済み。改変不要）。

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 二輪教習に対応した指定自動車教習所の一覧。
 *
 * 方針:
 *  - 一次情報は各都道府県の指定自動車教習所協会が公表する会員校リスト（普自二/大自二の○列付き）。
 *  - source_url に必ずその出典URLを入れる。
 *  - verified_at が入っている行だけを公開対象とする（人手確認ゲート）。NULL の行は表示しない。
 *  - 料金は各校サイトで個別公表のためこのテーブルには持たない（後段で別途）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('driving_schools', function (Blueprint $table) {
            $table->id();
            $table->string('prefecture', 20);               // 神奈川県
            $table->string('prefecture_slug', 20);          // kanagawa
            $table->string('city', 60);                     // 横浜市鶴見区
            $table->string('name', 120);                    // 新鶴見ドライビングスクール
            $table->string('official_url', 255)->nullable();
            $table->boolean('futsuu_nirin')->default(false); // 普通二輪
            $table->boolean('oogata_nirin')->default(false); // 大型二輪
            $table->string('source_url', 255);               // 出典（協会公式リスト等）
            $table->date('verified_at')->nullable();         // 非NULL = 人手確認済み = 公開
            $table->timestamps();

            $table->unique(['prefecture_slug', 'name'], 'driving_schools_pref_name_unique');
            $table->index(['prefecture_slug', 'city'], 'driving_schools_pref_city_idx');
            $table->index('verified_at', 'driving_schools_verified_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('driving_schools');
    }
};
```

実行前に `php artisan migrate --pretend` の出力を必ず見せてください。

---

## 4. モデル

ファイル：`backend/app/Models/DrivingSchool.php`

- `$fillable` に全カラム（`id`, timestamps 以外）
- `$casts`：`futsuu_nirin` => `boolean`, `oogata_nirin` => `boolean`, `verified_at` => `date`
- スコープ `scopePublished($q)` … `$q->whereNotNull('verified_at')`
- スコープ `scopeNirin($q)` … 普通二輪 or 大型二輪 のどちらかが true
  （`$q->where(fn ($w) => $w->where('futsuu_nirin', true)->orWhere('oogata_nirin', true))`）
- アクセサ `getLicenseLabelsAttribute()` … `['普通二輪', '大型二輪']` のうち true のものだけ返す（Blade を簡潔にするため。任意）

---

## 5. CSV 取り込みコマンド

ファイル：`backend/app/Console/Commands/ImportDrivingSchools.php`

シグネチャ：

```
schools:import {path : CSVファイルのパス} {--dry-run : 書き込まずに差分だけ表示}
```

CSV の列（1行目はヘッダ、この順で固定）：

```
prefecture,prefecture_slug,city,name,official_url,futsuu_nirin,oogata_nirin,source_url,verified_at
```

要件：

- 文字コードは UTF-8。BOM があれば除去する。
- `verified_at` は空文字なら `null` として扱う（＝未確認＝非公開）。
- **バリデーション（1つでも違反したら、その行をスキップして理由を出力）**
  - `prefecture` / `prefecture_slug` / `city` / `name` / `source_url` は必須
  - `prefecture_slug` は小文字英字のみ
  - `futsuu_nirin` / `oogata_nirin` は `0` または `1`
  - **両方 `0` の行はエラーにする**（二輪非対応校をこのテーブルに入れない）
  - `verified_at` は空、または `Y-m-d` 形式
- **キーは `(prefecture_slug, name)`。** 既存行があれば更新、なければ新規。**全削除→再投入は絶対にしない**（既存の確認済みデータを飛ばさないため）。
- `--dry-run` のときは DB を一切変更せず、次を出力して終了：
  - `新規: N件` / `更新: N件` / `変更なし: N件` / `スキップ: N件`
  - 更新になる行は「どのカラムが何から何に変わるか」を出す
- 本実行時も同じサマリを最後に出す。

---

## 6. ルート

`backend/routes/web.php` に追加。

```
GET /license/schools             → LicenseSchoolController@index  (name: license.schools.index)
GET /license/schools/{pref}      → LicenseSchoolController@show   (name: license.schools.show)
```

**重要な落とし穴：** 既存に `/license/{class}` のようなワイルドカードルートがあるはずです。**`/license/schools` はその手前に定義しないと、`schools` が `{class}` として食われます。** 既存の `routes/web.php` を必ず読んでから、ワイルドカードより前に置いてください。置いたあと `php artisan route:list --path=license` で両方が正しく出ることを確認してください。

`{pref}` は `->where('pref', '[a-z]+')` で制約してください。

---

## 7. コントローラ

ファイル：`backend/app/Http/Controllers/License/LicenseSchoolController.php`

### index()

- 公開対象（`published()` + `nirin()`）を `prefecture_slug` でグループ化し、`prefecture` 名と件数を出す
- **1件も無い都道府県は出さない**（空ページを作らない）
- 表示順は件数の多い順ではなく、`prefecture_slug` の昇順で十分（v1）

### show(string $pref)

- `published()` + `nirin()` + `where('prefecture_slug', $pref)` で取得
- **0件なら `abort(404)`**（中身のないページを生やさない）
- 並び順：`city` 昇順 → `name` 昇順
- ビューには「都道府県名」「学校コレクション」「出典URL（重複排除したもの）」「最終確認日（`verified_at` の最大値）」を渡す

---

## 8. ビュー

`backend/resources/views/license/schools/index.blade.php`
`backend/resources/views/license/schools/show.blade.php`

**既存の `backend/resources/views/license/show.blade.php` を必ず先に読んで、同じレイアウト・同じ Tailwind の書き味（slate 系、`rounded-2xl`、`font-black`、`tabular-nums` など）に揃えてください。** 新しいデザインを発明しないこと。

### index の文言（このまま使ってください）

- h1：`二輪免許が取れる指定自動車教習所`
- リード文：
  > 普通二輪・大型二輪の教習を行っている指定自動車教習所を、都道府県別にまとめています。免許区分ごとに乗れるバイクと中古相場を知りたい方は、[バイク免許ガイド](/license)もあわせてご覧ください。
- 都道府県カードには `{都道府県名}（N校）` と出す

### show の文言

- h1：`{都道府県名}で二輪免許が取れる指定自動車教習所`
- 一覧は表形式。列は `市区町村` / `教習所名` / `普通二輪` / `大型二輪`
- 教習所名は `official_url` があればリンクにする。**必ず `target="_blank" rel="nofollow noopener"`**
- 対応している区分には `○`、していなければ `—`（バツ記号は避ける）
- ページ下部に `/license/futsuu` と `/license/oogata` への導線を置く（文言：`普通二輪でどんなバイクに乗れるか見る` / `大型二輪でどんなバイクに乗れるか見る`）

### 免責文（両ページ必須。このまま一字一句使ってください）

> この一覧は各都道府県の指定自動車教習所協会が公表している会員校リストをもとに作成しています。協会に加盟していない教習所や、掲載後に取扱いが変わった教習所は反映されていない場合があります。教習料金・入校条件・二輪教習の実施状況は各教習所が個別に定めているため、お申し込み前に必ず各校の公式サイトでご確認ください。

その下に `出典：{source_url}（最終確認：{verified_at}）` を小さく表示。

### meta

- index：
  - title `二輪免許が取れる指定自動車教習所一覧｜MotoHub`
  - description `普通二輪・大型二輪の教習を行っている指定自動車教習所を都道府県別にまとめました。出典は各都道府県の指定自動車教習所協会の公表リストです。`
  - canonical `https://motohub.jp/license/schools`（絶対URL）
- show：
  - title `{都道府県名}で二輪免許が取れる指定自動車教習所一覧｜MotoHub`
  - description `{都道府県名}で普通二輪・大型二輪の教習を行っている指定自動車教習所の一覧です。市区町村・対応免許区分・公式サイトへのリンクをまとめています。`
  - canonical `https://motohub.jp/license/schools/{pref}`（絶対URL）

---

## 9. 実行順（この順でお願いします）

1. `routes/web.php` と `resources/views/license/show.blade.php` を読む（既存の作法を把握するため）
2. マイグレーション作成 → `php artisan migrate --pretend` の出力を見せる → 問題なければ `php artisan migrate`
3. モデル作成
4. 取り込みコマンド作成 → `php artisan schools:import <csv> --dry-run` の出力を見せる → 問題なければ本実行
5. ルート追加 → `php artisan route:list --path=license` で確認
6. コントローラ作成
7. ビュー作成
8. ローカルで `/license/schools` と `/license/schools/kanagawa` を開いて表示確認（スクリーンショットがあると助かります）
9. `/license/schools/tokyo` が **404 になること**を確認（データが無い県はページを生やさない、の検証）

sitemap への追加は今回はやらないでください（別途対応します）。

---

## 10. 同梱データについて

`driving_schools_kanagawa.csv` は 5 行あります。

- 4 行は `verified_at=2026-07-26` … 出典ページを 2 回別々に読み直して一致した、確認済みの行
- 1 行（都南自動車教習所）は `verified_at` が**空** … 読み取り結果が割れたため未確認。公式サイトが応答せず裏取りできていない

したがって **取り込み後に画面に出るのは 4 校だけ**が正しい挙動です。5 校出たら `published()` が効いていないので直してください。件数の少なさはバグではなく、確認できた分しか出さないという設計どおりです。
