# 運用手順書: webike 画像の残存確認と削除（webike:purge-images）

権利者（株式会社リバークレイン＝ウェビック）より **2026-08-10 付で「取得済みの画像も含めた掲載の停止」** を
要請され承諾済み。表示側は既に抑止されている（`Listing::IMAGE_SUPPRESSED_SITE_IDS=[3]` /
`Shop::SUPPRESSED_IMAGE_HOST_KEYWORDS=['webike-cdn']`）が、**画像の実体**がローカル（public）や R2 に
残っていることがある。本手順はその残存を確認し、残っていれば安全に消すためのもの。

- 影響範囲: 画像ファイルの実体のみ。DB は書き換えない（`webike:purge-images` は純粋な削除）。
- migration: なし。
- 新規取得は既に停止済み（スクレイパーの `is_image_url_allowed` が `img.webike-cdn.net` を拒否）。
- R2 移行（`listings:migrate-images-to-r2`）は `listings/webike` を列挙から除外し、`--site=webike` を
  受け付けない（PR #58）。つまり**新たに webike 画像が R2 へ運ばれることはない**。本手順は過去分の掃除。

> ⚠️ `cache:clear` / `optimize:clear` は打たないこと（楽天パーツキャッシュ巻き込み事故）。
> この作業にキャッシュ削除は不要。

---

## 1. 残存確認（調査のみ・削除しない）

### 1-1. ローカル（public ディスク）
```bash
# ディレクトリの有無とファイル数（ディレクトリ無し or 0 ならクリーン）
docker exec motohub-app sh -c \
  'ls -d storage/app/public/listings/webike 2>/dev/null && find storage/app/public/listings/webike -type f | wc -l'
```

### 1-2. R2（r2_images ディスク）
```bash
# listings/webike 配下のオブジェクト数（0 が正常）
docker exec -e HOME=/tmp motohub-app php artisan tinker \
  --execute="echo count(Storage::disk('r2_images')->allFiles('listings/webike')).PHP_EOL;"
```

### 1-3. 店舗画像（webike-cdn 由来）
```bash
# image_url が webike-cdn を指す shops 件数（参考・purge の (B) 対象）
docker exec -e HOME=/tmp motohub-app php artisan tinker \
  --execute="echo \App\Models\Shop::where('image_url','like','%webike-cdn%')->count().PHP_EOL;"
```

いずれも 0 / ディレクトリ無しなら **クリーン。以降の作業は不要**。

---

## 2. 残っていた場合 — まず dry-run で対象を確認

**このコマンドは既定が dry-run（`--execute` を付けない限り削除しない）。** `--dry-run` という
フラグは無いので注意。素で実行すると「何を・何件消すか」だけ表示される。

```bash
# 既定 = dry-run。target の既定は all（listings + shops）
docker exec -e HOME=/tmp motohub-app php artisan webike:purge-images
```

出力で次を確認する:
- (A) listings: `listings/webike` を public / R2 からディレクトリ単位で削除する件数
- (B) shops: `image_url LIKE %webike-cdn%` の店舗のローカル画像削除件数

対象を絞りたい場合は `--target=listings` / `--target=shops`（`ogp` / `verify` は明示時のみ）。
`webike:purge-images` は削除直前に対象パスが `listings/webike` と完全一致することを検証する
（定数書き換え事故の防止）。想定外のパスが出たら**中断して原因を確認**。

---

## 3. 本実行（`--execute` で初めて削除される）

```bash
docker exec -e HOME=/tmp motohub-app php artisan webike:purge-images --execute
```

---

## 4. 事後確認

手順 1-1 / 1-2 / 1-3 を再実行し、すべて 0 / ディレクトリ無しになったことを確認する。

- 表示側は元々抑止済みなので、この作業後に画面の見た目は変わらない（実体が消えるだけ）。
- OPcache リセットやキャッシュ削除は不要。
