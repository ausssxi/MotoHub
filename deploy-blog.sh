#!/bin/bash
# MotoHub Blog Deploy Script
# Phase 2-4 のセットアップを完了するために Docker コンテナ内で実行
set -e

echo "=== MotoHub Blog Deploy ==="

# 1. Composer パッケージインストール
echo "[1/6] league/commonmark をインストール..."
docker compose exec app composer require league/commonmark

# 2. ストレージリンク
echo "[2/6] ストレージリンクを作成..."
docker compose exec app php artisan storage:link 2>/dev/null || echo "  (既に存在します)"

# 3. マイグレーション（Phase 1 で未実行の場合）
echo "[3/6] マイグレーション実行..."
docker compose exec app php artisan migrate --force

# 4. ルート確認
echo "[4/6] ブログルート一覧:"
docker compose exec app php artisan route:list --path=blog

# 5. ビューキャッシュ
echo "[5/6] ビューコンパイルチェック..."
docker compose exec app php artisan view:cache
docker compose exec app php artisan view:clear

# 6. 設定キャッシュ
echo "[6/6] 設定キャッシュチェック..."
docker compose exec app php artisan config:cache
docker compose exec app php artisan config:clear

echo ""
echo "=== Deploy Complete ==="
echo ""
echo "管理画面: http://localhost:8080/admin/blog/posts"
echo "公開画面: http://localhost:8080/blog"
echo ""
echo "注意: league/commonmark の Extension もインストールしてください:"
echo "  docker compose exec app composer require league/commonmark"
echo "  (Table, Autolink, HeadingPermalink は CommonMark 2.x に同梱)"
