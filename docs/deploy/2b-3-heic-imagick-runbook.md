# 本番デプロイ手順書: 2b-3 HEIC対応（app イメージ再ビルド）

**通常のコード pull デプロイと違い、`app` イメージの再ビルドが入る**（Dockerfile に imagick + libheif を追加）。
ビルド失敗が最大リスクなので、**旧イメージを退避してからビルドし、web を落とさず、失敗時は即ロールバック**する。

- 影響範囲: `app`（php-fpm）のみ。`web`(nginx) / `db`(mysql) / `redis` / `meilisearch` は無関係＝触らない。
- migration: **なし**（インフラ＋コードのみ）。
- ダウンタイム: app コンテナ再作成の数秒のみ（ビルドは旧コンテナ稼働中に実施）。

---

## 0. 事前確認
```bash
cd /var/www/motohub
git fetch origin && git log --oneline -1 origin/main   # 対象コミットを確認
docker compose ps                                       # app/web 稼働を確認
```

## 1. 旧 app イメージを退避（ロールバック用タグ）
```bash
# 現在 app が使っているイメージIDにロールバック用タグを付ける
docker image tag motohub-app motohub-app:rollback-$(date +%Y%m%d)
docker images motohub-app   # rollback タグが付いたことを確認
```

## 2. コード取得（まだ反映しない・ビルド材料）
```bash
git reset --hard origin/main
```

## 3. 新 app イメージをビルド（旧コンテナは稼働継続＝無停止）
```bash
docker compose build app
```
- 失敗したら **ここで中断**。旧イメージ・旧コンテナは無傷なのでサービスは継続。原因を解消して再ビルド。

## 4. ★ビルド成果物の HEIC デコード検証（切替前に必ず）
```bash
# 新イメージで imagick + HEIC が効くかを使い捨てコンテナで確認
docker compose run --rm --no-deps app php -r '
  echo "imagick: ", extension_loaded("imagick")?"yes":"no", PHP_EOL;
  $h = Imagick::queryFormats("HEIC");
  echo "HEIC: ", in_array("HEIC",$h,true)?"OK":"NG", PHP_EOL;
  $im=new Imagick(); $im->newImage(32,32,new ImagickPixel("red")); $im->setImageFormat("heic");
  $b=$im->getImageBlob(); $im2=new Imagick(); $im2->readImageBlob($b); $im2->setImageFormat("jpeg");
  echo "decode HEIC->JPEG: ", substr($im2->getImageBlob(),0,2)==="\xFF\xD8"?"OK":"NG", PHP_EOL;
'
```
- `imagick: yes` / `HEIC: OK` / `decode HEIC->JPEG: OK` を確認。**NG が出たら 切替せず**ロールバック判断。

## 5. app コンテナを新イメージで再作成（web は据え置き＝無停止）
```bash
docker compose up -d --no-deps app     # app のみ再作成。web/db/redis は触らない
docker compose exec app php artisan config:cache   # 念のため
docker compose exec app php artisan view:cache
docker compose exec app php artisan opcache_reset 2>/dev/null || docker compose restart app
```
> migration はこの回は無し。将来 migration を伴う回は `php artisan migrate:status → migrate` をこの直後に。

## 6. 動作確認（本番スモーク）
```bash
docker compose exec app php -m | grep -i imagick      # imagick ロード確認
curl -sI https://motohub.jp/ | head -1                # 200 を確認
```
- ブラウザで `/garage/{自分のbike}` → 整備/カスタムに **iPhoneのHEIC写真**をアップ → サムネ表示・保存されること。

## 7. ★実機 privacy QA（2b-3 の本丸・必須）
- **GPS 付きの iPhone 写真**（位置情報ON で撮影した HEIC）をアップロード。
- 保存後の画像をダウンロードし、EXIF/GPS が**残っていない**ことを確認:
  ```bash
  # 保存ファイルを取り出して確認（パスは管理画面/DBから）
  docker compose exec app php artisan tinker --execute="
    \$i = App\Models\MyBikeImage::latest('id')->first();
    \$bytes = Storage::disk(config('garage.image_disk'))->get(\$i->path);
    echo 'has Exif: ', str_contains(\$bytes,'Exif')?'YES(NG)':'NO(OK)', PHP_EOL;
    echo 'has GPS: ', stripos(\$bytes,'GPS')!==false?'YES(NG)':'NO(OK)', PHP_EOL;
  "
  ```
  - もしくはローカルへ落として `exiftool` で GPS タグが空であることを確認（推奨）。
- jpg/png/webp の従来アップロードも 1 枚ずつ確認（無回帰）。

## ロールバック手順（4 や 6/7 で異常時）
```bash
cd /var/www/motohub
# 1) app を旧イメージへ戻す
docker image tag motohub-app:rollback-YYYYMMDD motohub-app
docker compose up -d --no-deps app
# 2) コードも前コミットへ戻す場合
git reset --hard <直前のコミット>
docker compose exec app php artisan view:cache && docker compose exec app php artisan config:cache
# 3) 確認
docker compose exec app php -m | grep -i imagick   # 旧イメージなら imagick なし＝戻った
curl -sI https://motohub.jp/ | head -1
```
- web/db/redis は一連の操作で**一切触っていない**ので、app を旧イメージに戻すだけで原状復帰。

## メモ
- `set_mempolicy: Operation not permitted` のような stderr 警告は ImageMagick の OpenMP/NUMA に由来する無害なログ。気になる場合は `ENV OMP_NUM_THREADS=1` を Dockerfile に足すと減る（今回は未設定）。
- HEIC は `app` 内 PHP(Imagick) でのみ使用。`web`(nginx) には影響しない。
