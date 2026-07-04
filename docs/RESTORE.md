# バックアップ復元手順（RESTORE）

MotoHub 本番バックアップ（spatie/laravel-backup → Cloudflare R2）の復元手順。

- バックアップは毎日 03:10 に生成され、R2 バケットに保存される。
- 中身は **AES-256 で暗号化された zip**。パスワードは `BACKUP_ARCHIVE_PASSWORD`
  （本番 .env に設定・**サーバ外＝内田のパスワードマネージャに控える**）。
- zip の中身：
  - `db-dumps/mysql-motohub.sql` … DB ダンプ（プレーンSQL。sessions/cache/cache_locks/jobs は除外）
  - `storage/app/public/blog/…`、`storage/app/public/shop-user/…`、
    `storage/app/private/shop-submissions/…`、`storage/app/private/garage/…` … UGCファイル
  - `.env` … APIキー群（暗号化zip内。取り扱い注意）

> ⚠️ **本番への復元は破壊的操作**。まず必ずローカル or 別スキーマで検証してから本番に適用すること。

---

## 1. R2 から対象 zip を取得

**方法A：Cloudflare ダッシュボード**
R2 → 対象バケット（例 `motohub-backups`）→ `MotoHub/` 配下 → 目的の日付の
`YYYY-MM-DD-HH-mm-ss.zip` をダウンロード。

**方法B：rclone（S3互換で設定）**
```bash
# ~/.config/rclone/rclone.conf に r2 リモートを設定済みとして
rclone lsf r2:motohub-backups/MotoHub/
rclone copy r2:motohub-backups/MotoHub/2026-07-05-03-10-00.zip ./
```

**方法C：aws-cli**
```bash
aws s3 cp s3://motohub-backups/MotoHub/2026-07-05-03-10-00.zip ./ \
  --endpoint-url "$R2_ENDPOINT"
```

## 2. パスワードで解凍

AES-256 のため、AES対応ツール（7-Zip 等）を使う。標準 `unzip` は AES 非対応。
```bash
7z x -p"$BACKUP_ARCHIVE_PASSWORD" 2026-07-05-03-10-00.zip -o./restore
# → ./restore/db-dumps/mysql-motohub.sql と storage/... /.env が展開される
```
（7z が無い場合は PHP でも可：`ZipArchive::open()` → `setPassword()` → `extractTo()`。）

## 3. DB 復元

**必ず別スキーマ or ローカルで先に検証**。ダンプは単一DBの `CREATE TABLE` + `INSERT`
（`CREATE DATABASE` は含まない）。

```bash
# 検証用スキーマへ（推奨）
docker compose exec -T db mysql -uroot -p"$DB_PASSWORD" \
  -e "CREATE DATABASE motohub_restore CHARACTER SET utf8mb4;"
docker compose exec -T db mysql -uroot -p"$DB_PASSWORD" motohub_restore \
  < ./restore/db-dumps/mysql-motohub.sql

# 本番DBへ上書き復元する場合（破壊的・確証を得てから）
docker compose exec -T db mysql -uroot -p"$DB_PASSWORD" motohub \
  < ./restore/db-dumps/mysql-motohub.sql
```
※ ダンプは非圧縮の `.sql`。もし `.sql.gz` の場合は `gunzip -c dump.sql.gz | mysql ...` で流す。

## 4. ファイル（UGC）復元

zip 内の `storage/…` 配下を、本番の同じ相対パスへ展開する。
```bash
cp -a ./restore/storage/app/public/blog/.          /var/www/storage/app/public/blog/
cp -a ./restore/storage/app/public/shop-user/.     /var/www/storage/app/public/shop-user/
cp -a ./restore/storage/app/private/shop-submissions/. /var/www/storage/app/private/shop-submissions/
cp -a ./restore/storage/app/private/garage/.       /var/www/storage/app/private/garage/
# 権限（php-fpm 実行ユーザー）
chown -R www-data:www-data /var/www/storage/app
```
`public` 配下を配信するには `php artisan storage:link`（未リンク時）が必要。

## 5. 復元後の確認

```bash
# 主要テーブルの件数
docker compose exec -T db mysql -uroot -p"$DB_PASSWORD" motohub -e "
  SELECT 'shops', COUNT(*) FROM shops
  UNION ALL SELECT 'blog_posts', COUNT(*) FROM blog_posts
  UNION ALL SELECT 'shop_submissions', COUNT(*) FROM shop_submissions
  UNION ALL SELECT 'shop_acceptance_reports', COUNT(*) FROM shop_acceptance_reports;"
```
- サイト表示（トップ・ブログ記事・店舗詳細・投稿画像）が出るか目視。
- キャッシュ整合のため `php artisan config:cache && php artisan view:cache`、必要なら
  Meilisearch 再インデックス（`scout:import`）。

---

## 検証済みの復元ドリル（参考）

ローカルで実施済み：`backup:run`（暗号化zip）→ ZipArchive で解凍 →
別スキーマ `motohub_restore` に復元 → 主要テーブル件数一致を確認。
（shops=11043 / listings=317313 / blog_posts=68 / trouble_events=6 等、全て一致）
