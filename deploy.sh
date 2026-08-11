#!/bin/bash
#
# MotoHub 汎用デプロイスクリプト（本番サーバの /var/www/motohub で実行する）
#
# ■ このスクリプトが存在する理由
#   git pull だけでは Web 側にコードが反映されない。app コンテナは php-fpm で動いており、
#   fpm のワーカープロセスが OPcache に古いバイトコードを保持し続けるため。
#   2026-08-11、新ルート（rental-garage.area.*）を pull した直後に
#     - CLI（php artisan route:list）には新ルートが出る
#     - Web は該当URLが全部404、さらにフッターの route() が
#       「Route [rental-garage.area.index] not defined」を投げて laravel.log を埋めた
#   という事故が起きた（ルートキャッシュは存在しない＝原因は fpm 側の OPcache）。
#   docker compose restart app で解消した。同じ理由で、その前の画像抑止デプロイでも
#   古い出力が1件だけしばらく残っていた。
#   → 「PHP の再読み込み」を必ず踏むために、手順を実行可能な形にした。
#
# ■ やらないこと（意図的に人間に委ねる）
#   - マイグレーションの自動実行（要否を表示するだけ）
#   - composer install の自動実行（必要かどうかを表示するだけ）
#   スキーマとパッケージを無人で変更しない。壊れたときの切り戻しが難しくなるため。
#
# 使い方:
#   ./deploy.sh          … 実行内容を表示して確認を求める（対話）
#   ./deploy.sh --yes    … 確認せずに実行する（自動化用）
#   SMOKE_BASE=https://example.jp ./deploy.sh --yes  … スモークテスト先を差し替える
#
set -euo pipefail

# ── 設定 ────────────────────────────────────────────────
# スモークテストの宛先。既定は公開ドメイン。
# app コンテナから http://web/... を叩くと nginx が 301 を返して fpm に届かないため、
# 公開ドメイン経由で確認する（docs/DEPLOY.md の OPcache の項と同じ理由）。
SMOKE_BASE="${SMOKE_BASE:-https://motohub.jp}"

# スモークテスト対象。今日404になった /rental-garages/area を必ず含める
# （ルート追加が Web に反映されたかを、事故と同じ経路で確かめる）。
SMOKE_PATHS=(
    "/"
    "/bikes/search"
    "/rental-garages/area"
    "/parking/area"
    "/gs"
    "/blog"
)

ASSUME_YES=0
for arg in "$@"; do
    case "$arg" in
        --yes|-y) ASSUME_YES=1 ;;
        *) echo "不明な引数: $arg（使えるのは --yes のみ）" >&2; exit 2 ;;
    esac
done

cd "$(dirname "$0")"

step() { echo ""; echo "── $* ─────────────────────────"; }
ok()   { echo "  [OK] $*"; }
warn() { echo "  [!!] $*"; }

# ── 0. 実行内容の提示と確認 ──────────────────────────────
cat <<'EOS'
=== MotoHub デプロイ ===

このスクリプトが行うこと:
  1. 現在のコミットを記録（切り戻し用に表示）
  2. git pull --ff-only
  3. composer.lock の差分を確認（install はしない・必要なら表示のみ）
  4. 未実行マイグレーションの有無を確認（migrate はしない・表示のみ）
  5. ビューキャッシュの削除
  6. php-fpm の graceful reload（OPcache を確実に捨てる／404事故の再発防止）
  7. 主要ページのスモークテスト

破壊的な操作（migrate / composer install）は自動実行しません。
EOS

if [ "$ASSUME_YES" -ne 1 ]; then
    read -r -p "続行しますか? [y/N] " answer
    case "$answer" in
        y|Y|yes|YES) ;;
        *) echo "中止しました。"; exit 0 ;;
    esac
fi

# ── 1. 現在のコミットを記録（切り戻し用） ────────────────
step "[1/7] 現在のコミットを記録"
BEFORE_SHA="$(git rev-parse HEAD)"
echo "  デプロイ前: $BEFORE_SHA"
echo "  $(git log -1 --format='%h %s' "$BEFORE_SHA")"
echo ""
echo "  ▼ 切り戻しが必要になったらこれを実行:"
echo "      git checkout $BEFORE_SHA && docker compose restart app"

# 作業ツリーが汚れていると pull が予期せぬ形で失敗するため、先に止める。
if [ -n "$(git status --porcelain)" ]; then
    warn "作業ツリーに未コミットの変更があります。デプロイを中止します:"
    git status --short
    exit 1
fi
ok "作業ツリーはクリーン"

# ── 2. git pull ─────────────────────────────────────────
step "[2/7] git pull --ff-only"
git pull --ff-only
AFTER_SHA="$(git rev-parse HEAD)"

if [ "$BEFORE_SHA" = "$AFTER_SHA" ]; then
    echo "  取り込む変更はありませんでした（$AFTER_SHA）。"
    echo "  コードが変わっていないので反映処理も不要です。終了します。"
    exit 0
fi

ok "更新: $BEFORE_SHA → $AFTER_SHA"
echo "  変更ファイル:"
git diff --stat "$BEFORE_SHA" "$AFTER_SHA" | sed 's/^/    /'

# 以降の判定で使う差分ファイル一覧
CHANGED_FILES="$(git diff --name-only "$BEFORE_SHA" "$AFTER_SHA")"

# ── 3. composer.lock の差分確認（自動実行はしない） ──────
step "[3/7] composer.lock の差分確認"
if echo "$CHANGED_FILES" | grep -q '^backend/composer\.lock$'; then
    warn "composer.lock に差分があります。依存パッケージの更新が必要です:"
    echo "      docker compose exec app composer install --no-dev --optimize-autoloader"
    echo "    （このスクリプトでは自動実行しません。実行後、手順6の再読み込みを"
    echo "      もう一度行ってください）"
else
    ok "composer.lock に差分なし（composer install は不要）"
fi

# ── 4. マイグレーションの要否（自動実行はしない） ────────
step "[4/7] 未実行マイグレーションの確認"
# migrate:status は読み取りのみ。状態を見るだけで適用はしない。
MIGRATION_STATUS="$(docker compose exec -T app php artisan migrate:status 2>&1 || true)"
if echo "$MIGRATION_STATUS" | grep -qi 'pending'; then
    warn "未実行のマイグレーションがあります:"
    echo "$MIGRATION_STATUS" | grep -i 'pending' | sed 's/^/    /'
    echo ""
    echo "    適用するなら:"
    echo "      docker compose exec app php artisan migrate --force"
    echo "    （このスクリプトでは自動実行しません。スキーマ変更は人が判断すること）"
else
    ok "未実行のマイグレーションなし"
fi

# ── 5. アプリケーションキャッシュの整理 ──────────────────
step "[5/7] アプリケーションキャッシュの整理"
# このプロジェクトが実際に使っているのはビューキャッシュだけ。
# bootstrap/cache に routes.php / config.php は無く、config:cache / route:cache は
# 運用していない。使っていないキャッシュのクリアは足さない。
docker compose exec -T app php artisan view:clear
ok "view:clear 実行"

# ただし、誰かが config:cache / route:cache を打った痕跡があれば知らせる。
# ルートキャッシュが残っていると、この後 fpm を再読み込みしても新ルートは出ない。
for cache_file in bootstrap/cache/routes-v7.php bootstrap/cache/config.php; do
    if docker compose exec -T app test -f "$cache_file" 2>/dev/null; then
        warn "$cache_file が存在します。運用外のキャッシュです。"
        echo "    ルート/設定が古いまま固定される原因になります。要確認:"
        echo "      docker compose exec app php artisan optimize:clear"
    fi
done

# ── 6. php-fpm の再読み込み（今日の事故の再発防止） ──────
step "[6/7] php-fpm を graceful reload（OPcache を捨てる）"
# ここが本スクリプトの主目的。
# app コンテナは CMD ["php-fpm"] なので php-fpm master が PID 1。
# master に SIGUSR2 を送ると設定を読み直してワーカーを入れ替える（graceful reload）。
# 処理中のリクエストを落とさずに OPcache を捨てられるため、restart より安全。
if docker compose exec -T app sh -c 'kill -USR2 1' 2>/dev/null; then
    ok "php-fpm master (PID 1) に SIGUSR2 を送信（graceful reload）"
    # ワーカーの入れ替えを待つ。待たずにスモークすると古いワーカーに当たり得る。
    sleep 3
else
    warn "SIGUSR2 の送信に失敗しました。フォールバックに移ります。"

    # フォールバック1: 公開ドメイン経由の opcache_reset.php。
    # app コンテナ内から http://web/ を叩くと nginx が 301 を返して fpm に届かないため、
    # 必ず公開ドメインで叩き、かつ HTTP 200 を明示確認する
    # （curl -f は 301 を成功扱いにするので -f の戻り値に頼らない）。
    OPCACHE_CODE="$(curl -s --max-time 15 -o /dev/null -w '%{http_code}' https://motohub.jp/opcache_reset.php || true)"
    if [ "$OPCACHE_CODE" = "200" ]; then
        ok "opcache_reset.php でリセット (HTTP 200)"
        sleep 1
    else
        warn "opcache_reset.php も不成立 (HTTP ${OPCACHE_CODE:-無応答})。app コンテナを再起動します。"
        # フォールバック2: プロセスごと入れ替える。確実だが一瞬 502 になり得る。
        docker compose restart app
        ok "app コンテナを再起動"
        sleep 5
    fi
fi

# ── 7. スモークテスト ───────────────────────────────────
step "[7/7] スモークテスト（${SMOKE_BASE}）"
smoke_failed=0
for path in "${SMOKE_PATHS[@]}"; do
    code="$(curl -s --max-time 20 -o /dev/null -w '%{http_code}' "${SMOKE_BASE}${path}" || true)"
    if [ "$code" = "200" ]; then
        ok "${path} => ${code}"
    else
        echo "  [NG] ${path} => ${code:-無応答}（200以外）"
        smoke_failed=1
    fi
done

if [ "$smoke_failed" -ne 0 ]; then
    echo ""
    echo "!!! スモークテストで 200 以外がありました。反映が不完全な可能性があります。"
    echo "    ログ:       docker compose exec app tail -50 storage/logs/laravel.log"
    echo "    再読み込み: docker compose restart app"
    echo "    切り戻し:   git checkout $BEFORE_SHA && docker compose restart app"
    exit 1
fi

echo ""
echo "=== デプロイ完了 ==="
echo "  $BEFORE_SHA -> $AFTER_SHA"
echo "  切り戻し: git checkout $BEFORE_SHA && docker compose restart app"
