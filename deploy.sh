#!/bin/bash
#
# MotoHub 汎用デプロイスクリプト（本番サーバの /var/www/motohub で実行する）
#
# ■ このスクリプトが存在する理由
#   git pull した直後は、Web 側の反映に最大2秒の窓がある。app コンテナは php-fpm で動いており、
#   OPcache がバイトコードを保持しているため。ただし本番は
#     opcache.enable=On / opcache.enable_cli=Off
#     opcache.validate_timestamps=On / opcache.revalidate_freq=2   ← 2026-08-11 実測
#   なので、放っておいても最大2秒で自動的に反映される（永続的に古いままにはならない）。
#
#   問題はこの2秒の窓で、その間は CLI（artisan route:list など）は新しいコードを見るのに
#   Web はまだ古いコードで動く。つまり pull 直後の確認は偽陰性になる。
#   2026-08-11、新ルート（rental-garage.area.*）を pull した直後に Web を確認して該当URLが
#   404 になり、フッターの route() が「Route [rental-garage.area.index] not defined」を投げて
#   laravel.log に記録された。restart で解消したが、これは反映漏れではなく再検証窓を
#   踏んだ偽陰性で、待っていれば自然に解消していた（ルートキャッシュも存在しなかった）。
#
#   → このスクリプトが明示的に再読み込みするのは「反映漏れを防ぐため」ではなく、
#     「2秒の窓を確実に閉じてからスモークテストし、デプロイ失敗と誤判断しないため」。
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

# スモークテスト対象。2026-08-11 に一時的に404が見えた /rental-garages/area を必ず含める
# （ルート追加が Web に反映されたかを、当時と同じ経路で確かめる）。
#
# 車種詳細ページ（/bikes/{maker}/{slug}）を追加する。サイトの中核ページであり、
# かつ 2026-08-13 に maintenance-{battery,plug,oil} partial の Blade 崩れで全滅した経路を
# スモークが1つも通っていなかったため今回の500を検出できなかった。
# ★あえて「適合表(model_fitments)のある車種」を選ぶ: 適合データが無い車種は
#   maintenance partial が mode=none で描画されず、出典表示や適合表まわりの Blade を
#   通らない＝空振りになる。適合表のある zoomer / super-cub-110 なら、その描画経路
#   （partial・出典URL表示）を実際にレンダリングして構文崩れを検出できる。
SMOKE_PATHS=(
    "/"
    "/bikes/search"
    "/bikes/honda/zoomer"
    "/bikes/honda/super-cub-110"
    "/rental-garages/area"
    "/parking/area"
    "/gs"
    "/blog"
    "/michinoeki/19008"
    "/michinoeki/12029"
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
  3. フロントエンドアセットのビルド（npm run build・失敗時は退避したビルドへ即切り戻し）
  4. composer.lock の差分を確認（install はしない・必要なら表示のみ）
  5. 未実行マイグレーションの有無を確認（migrate はしない・表示のみ）
  6. ビューキャッシュの削除
  7. php-fpm の graceful reload（OPcache の2秒の再検証窓を閉じてから確認するため）
  8. 主要ページのスモークテスト

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
step "[1/8] 現在のコミットを記録"
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
step "[2/8] git pull --ff-only"
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

# ── 3. フロントエンドアセットのビルド（自動実行するが、失敗時は退避へ即切り戻し） ──────
# 2026-08-22 発覚: deploy.sh に npm run build が無く、本番 public/build/assets/app-*.css の
# ビルド日時が 2026-07-23 22:29 のまま約1か月固定されていた。7/23 以降に追加された Tailwind クラスが
# CSS に一切含まれず、/license/schools/aichi の合宿ボタン（bg-slate-900 text-white）が白背景に白文字で
# 判読不能に。bg-slate-50（14箇所使用）/ bg-slate-800 も未定義で、対象ビュー20箇所中19箇所の背景色が無効だった。
# 本番で手動ビルド（app コンテナ内 npm run build・約12秒・CSS 138KB→141KB）で解消済み。再発防止のため組み込む。
# ★Blade 等の差分有無で判定しない: Tailwind の purge は全ソースを走査するため、今回のような
#   「Blade 以外の要因でクラスが変わる」ケースを差分判定は取りこぼす。だから毎回ビルドする。
# ★設計思想（migrate / composer install は無人実行しない）の中間: スキーマ変更ほど危険ではないが、
#   失敗すると全ページの見た目が壊れる。よって「失敗しても即座に戻せる状態（旧 public/build の退避）を
#   作ってから」実行し、失敗したら退避へ切り戻して exit 1 する。
step "[3/8] フロントエンドアセットのビルド（npm run build）"

BUILD_DIR="backend/public/build"

# a. 現在の public/build を「最終更新日時」つきで退避（上書きせず・同名があれば連番）。
#    退避名の日時で「いつのビルドだったか」を後から追える（今回の 7/23 固定のような事故の検知に効く）。
if [ -d "$BUILD_DIR" ]; then
    BUILD_MTIME="$(date -r "$BUILD_DIR" '+%Y%m%d-%H%M%S' 2>/dev/null || date '+%Y%m%d-%H%M%S')"
    BACKUP_DIR="${BUILD_DIR}.bak-${BUILD_MTIME}"
    bak_n=1
    while [ -e "$BACKUP_DIR" ]; do
        BACKUP_DIR="${BUILD_DIR}.bak-${BUILD_MTIME}-${bak_n}"
        bak_n=$((bak_n + 1))
    done
    mv "$BUILD_DIR" "$BACKUP_DIR"
    echo "  退避: $BUILD_DIR → $BACKUP_DIR"
else
    BACKUP_DIR=""
    warn "$BUILD_DIR が無いため退避なし（初回ビルド扱いで続行）"
fi

# b/c. app コンテナ内でビルド。set -e 下でも if 条件のコマンド失敗はスクリプトを打ち切らないため、
#      失敗を捕まえて退避を書き戻し、切り戻しコマンドを表示してから exit 1 する。
BUILD_START="$(date +%s)"
if docker compose exec -T app npm run build; then
    BUILD_ELAPSED=$(( $(date +%s) - BUILD_START ))
    # d. 生成 CSS のファイル名・サイズ・所要時間を表示。
    ok "npm run build 成功（${BUILD_ELAPSED}秒）"
    if ls "$BUILD_DIR"/assets/app-*.css >/dev/null 2>&1; then
        for css in "$BUILD_DIR"/assets/app-*.css; do
            echo "  生成CSS: $(basename "$css")（$(du -h "$css" | cut -f1)）"
        done
    else
        warn "app-*.css が見つかりません。ビルド出力を手動で確認してください。"
    fi
else
    warn "npm run build が失敗しました。退避したビルドへ切り戻します。"
    if [ -n "$BACKUP_DIR" ] && [ -d "$BACKUP_DIR" ]; then
        echo "  ▼ 切り戻しコマンド（これを実行します）:"
        echo "      rm -rf $BUILD_DIR && mv $BACKUP_DIR $BUILD_DIR"
        rm -rf "$BUILD_DIR"
        mv "$BACKUP_DIR" "$BUILD_DIR"
        ok "退避したビルドへ切り戻しました（$BUILD_DIR）"
    else
        warn "退避がありません（初回ビルド）。public/build を手動で確認してください。"
    fi
    exit 1
fi

# e. 退避が溜まりすぎたら警告（自動削除はしない・人が判断して消す）。
BAK_COUNT="$(ls -1d ${BUILD_DIR}.bak-* 2>/dev/null | wc -l | tr -d ' ' || true)"
if [ "${BAK_COUNT:-0}" -ge 5 ]; then
    warn "public/build の退避が ${BAK_COUNT} 個あります。古いものは手動で削除してよいです（自動削除はしません）:"
    echo "      ls -1dt ${BUILD_DIR}.bak-*   # 新しい順に確認し、不要分を rm -rf"
fi

# ── 4. composer.lock の差分確認（自動実行はしない） ──────
step "[4/8] composer.lock の差分確認"
if echo "$CHANGED_FILES" | grep -q '^backend/composer\.lock$'; then
    warn "composer.lock に差分があります。依存パッケージの更新が必要です:"
    echo "      docker compose exec app composer install --no-dev --optimize-autoloader"
    echo "    （このスクリプトでは自動実行しません。実行後、手順7の再読み込みを"
    echo "      もう一度行ってください）"
else
    ok "composer.lock に差分なし（composer install は不要）"
fi

# ── 5. マイグレーションの要否（自動実行はしない） ────────
step "[5/8] 未実行マイグレーションの確認"
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

# ── 6. アプリケーションキャッシュの整理 ──────────────────
step "[6/8] アプリケーションキャッシュの整理"
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

# ── 7. php-fpm の再読み込み（偽陰性の防止） ─────────────
step "[7/8] php-fpm を graceful reload（OPcache の再検証窓を閉じる）"
# ここが本スクリプトの主目的。
# 本番は validate_timestamps=On / revalidate_freq=2 なので放置でも最大2秒で反映されるが、
# その窓の中で次のスモークテストを走らせると古いコードに当たって偽陰性になる。
# 先に明示的に捨てておけば、7の結果をそのまま信用できる。
#
# app コンテナは CMD ["php-fpm"] なので php-fpm master が PID 1（2026-08-11 実測）。
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

# ── 8. スモークテスト ───────────────────────────────────
step "[8/8] スモークテスト（${SMOKE_BASE}）"
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
