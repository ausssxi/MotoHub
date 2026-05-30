#!/bin/bash
# MotoHub Blog Deploy Script
# Phase 2-4 のセットアップを完了するために Docker コンテナ内で実行
set -e

echo "=== MotoHub Blog Deploy ==="

# 1. Composer パッケージインストール
echo "[1/7] league/commonmark をインストール..."
docker compose exec app composer require league/commonmark

# 2. ストレージリンク
echo "[2/7] ストレージリンクを作成..."
docker compose exec app php artisan storage:link 2>/dev/null || echo "  (既に存在します)"

# 3. マイグレーション（Phase 1 で未実行の場合）
echo "[3/7] マイグレーション実行..."
docker compose exec app php artisan migrate --force

# 4. ルート確認
echo "[4/7] ブログルート一覧:"
docker compose exec app php artisan route:list --path=blog

# 5. ビューキャッシュ
echo "[5/7] ビューコンパイルチェック..."
docker compose exec app php artisan view:cache
docker compose exec app php artisan view:clear

# 6. 設定キャッシュ
echo "[6/7] 設定キャッシュチェック..."
docker compose exec app php artisan config:cache
docker compose exec app php artisan config:clear

# 7. php-fpm の OPcache をリセット
# 注意: php artisan(CLI) の OPcache と fpm の OPcache は別物。
# コンパイル済みビュー/PHPの更新を即時反映させるため、必ず fpm 側をリセットする。
# opcache_reset.php は fpm 経由(HTTP)で叩いて初めて fpm の OPcache をクリアできる。
echo "[7/7] php-fpm OPcache をリセット..."
if docker compose exec -T app sh -c 'curl -fsS -k https://localhost/opcache_reset.php >/dev/null 2>&1 || curl -fsS http://localhost/opcache_reset.php >/dev/null 2>&1'; then
    echo "  OPcache をリセットしました (opcache_reset.php)"
else
    echo "  HTTP 経由のリセットに失敗。app コンテナを再起動して fpm OPcache をクリアします..."
    docker compose restart app
    echo "  app コンテナを再起動しました"
fi

echo ""
echo "=== Deploy Complete ==="
echo ""
echo "管理画面: http://localhost:8080/admin/blog/posts"
echo "公開画面: http://localhost:8080/blog"
echo ""
echo "注意: league/commonmark の Extension もインストールしてください:"
echo "  docker compose exec app composer require league/commonmark"
echo "  (Table, Autolink, HeadingPermalink は CommonMark 2.x に同梱)"
