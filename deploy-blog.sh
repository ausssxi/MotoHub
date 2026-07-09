#!/bin/bash
# MotoHub Blog Deploy Script
# Phase 2-4 のセットアップを完了するために Docker コンテナ内で実行
set -e

echo "=== MotoHub Blog Deploy ==="

# 1. Composer パッケージインストール
echo "[1/8] league/commonmark をインストール..."
docker compose exec app composer require league/commonmark

# 2. ストレージリンク
echo "[2/8] ストレージリンクを作成..."
docker compose exec app php artisan storage:link 2>/dev/null || echo "  (既に存在します)"

# 3. マイグレーション（Phase 1 で未実行の場合）
echo "[3/8] マイグレーション実行..."
docker compose exec app php artisan migrate --force

# 4. ルート確認
echo "[4/8] ブログルート一覧:"
docker compose exec app php artisan route:list --path=blog

# 5. ビューキャッシュ
echo "[5/8] ビューコンパイルチェック..."
docker compose exec app php artisan view:cache
docker compose exec app php artisan view:clear

# 6. 設定キャッシュ
echo "[6/8] 設定キャッシュチェック..."
docker compose exec app php artisan config:cache
docker compose exec app php artisan config:clear

# 7. php-fpm の OPcache をリセット
# 注意: php artisan(CLI) の OPcache と fpm の OPcache は別物。
# コンパイル済みビュー/PHPの更新を即時反映させるため、必ず fpm 側をリセットする。
# opcache_reset.php は fpm 経由(HTTP)で叩いて初めて fpm の OPcache をクリアできる。
# 注意: app コンテナは fpm 専用で HTTP を持たない(localhost を叩くと 000 で空振り)。
# 必ず nginx(web) 経由で叩く。主: docker ネットワークの web サービス、
# 副: 公開ドメイン。どちらも失敗したら app 再起動でフォールバック(保険)。
echo "[7/8] php-fpm OPcache をリセット..."
if docker compose exec -T app sh -c 'curl -fsS http://web/opcache_reset.php >/dev/null 2>&1' \
   || curl -fsS https://motohub.jp/opcache_reset.php >/dev/null 2>&1; then
    echo "  OPcache をリセットしました (opcache_reset.php)"
else
    echo "  HTTP 経由のリセットに失敗。app コンテナを再起動して fpm OPcache をクリアします..."
    docker compose restart app
    echo "  app コンテナを再起動しました"
fi

# 8. スモークテスト（主要ページが 5xx を返せばデプロイ失敗として中断）
#    今日の「全店ページ500」がデプロイ完了扱いで本番に抜けた事故の再発防止。
echo "[8/8] スモークテスト（主要ページの応答確認）..."

# OPcacheリセットと同じ web(nginx) 経由で叩く（dev/prod両対応）。外部確認したい時は
# SMOKE_BASE=https://motohub.jp ./deploy-blog.sh で上書き可。
SMOKE_BASE="${SMOKE_BASE:-http://web}"

smoke_paths=("/" "/blog")

# 実在する店舗IDを1件取得し、店舗詳細（getApprovedSummary が走る全店共通経路）も対象に。
shop_id="$(docker compose exec -T app php artisan tinker --execute='echo \App\Models\Shop::value("id");' 2>/dev/null | tr -cd '0-9')"
if [ -n "$shop_id" ]; then
    smoke_paths+=("/shops/$shop_id")
else
    echo "  注意: 店舗データが無いため /shops/{id} はスキップ"
fi

smoke_failed=0
for path in "${smoke_paths[@]}"; do
    code="$(docker compose exec -T app sh -c "curl -s -o /dev/null -w '%{http_code}' '${SMOKE_BASE}${path}'" 2>/dev/null || true)"
    if ! [[ "$code" =~ ^[0-9]+$ ]] || [ "$code" -ge 500 ] || [ "$code" -eq 0 ]; then
        echo "  ✗ ${path} => ${code:-NO_RESPONSE}（異常）"
        smoke_failed=1
    else
        echo "  ✓ ${path} => ${code}"
    fi
done

if [ "$smoke_failed" -ne 0 ]; then
    echo ""
    echo "!!! スモークテスト失敗: 主要ページが 5xx を返しました。デプロイを中止します。"
    echo "    ログ:       docker compose logs --tail=120 app ; docker compose exec app tail -50 storage/logs/laravel.log"
    echo "    ロールバック: git checkout <前のSHA> && docker compose restart app"
    exit 1
fi
echo "  スモークテスト OK（主要ページは 5xx なし）"

echo ""
echo "=== Deploy Complete ==="
echo ""
echo "管理画面: http://localhost:8080/admin/blog/posts"
echo "公開画面: http://localhost:8080/blog"
echo ""
echo "注意: league/commonmark の Extension もインストールしてください:"
echo "  docker compose exec app composer require league/commonmark"
echo "  (Table, Autolink, HeadingPermalink は CommonMark 2.x に同梱)"
