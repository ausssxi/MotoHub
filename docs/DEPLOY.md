# デプロイ運用メモ

## ⚠️ 最初に: `git pull` だけでは Web 側に反映されない

app コンテナは **php-fpm** で動いており、fpm のワーカーが OPcache に古いバイトコードを
保持し続ける。`git pull` した直後は、

- CLI（`php artisan route:list` 等）は**新しいコードを見る**
- Web（nginx → fpm）は**古いコードのまま動く**

という食い違いが起きる。**pull の後に必ず php-fpm を再読み込みすること。**

実例（2026-08-11）: 新ルート `rental-garage.area.*` を pull した直後、該当URLが全部 404。
さらにフッターが `route('rental-garage.area.index')` を呼ぶため、404ページの描画で
`Route [rental-garage.area.index] not defined` が laravel.log に大量に記録された。
ルートキャッシュは存在せず、原因は fpm の OPcache。`docker compose restart app` で解消。

## 通常のデプロイ手順

リポジトリ直下の **`./deploy.sh`** を本番サーバで実行する。上記の再読み込みを含む一連の手順を
まとめてある。

```bash
cd /var/www/motohub
./deploy.sh          # 実行内容を表示して確認を求める
./deploy.sh --yes    # 確認なし（自動化用）
```

`deploy.sh` がやること:

1. デプロイ前のコミットを記録・表示（切り戻し用）／作業ツリーが汚れていれば中止
2. `git pull --ff-only`（差分が無ければ何もせず終了）
3. `composer.lock` の差分確認 — **install はしない。必要ならコマンドを表示するだけ**
4. 未実行マイグレーションの確認 — **migrate はしない。必要ならコマンドを表示するだけ**
5. `php artisan view:clear`（このプロジェクトが使っているのはビューキャッシュのみ。
   `config:cache` / `route:cache` は運用していない）
6. **php-fpm の graceful reload**（`kill -USR2 1`。失敗時は opcache_reset.php → コンテナ再起動）
7. 主要ページのスモークテスト（200以外があれば異常終了）

3と4を自動化していないのは意図的。スキーマとパッケージは無人で変更しない。

### 手で行う場合の最小手順

```bash
cd /var/www/motohub
git rev-parse HEAD                                   # 切り戻し用に控える
git pull --ff-only
docker compose exec app php artisan view:clear
docker compose exec app sh -c 'kill -USR2 1'         # php-fpm を graceful reload
sleep 3
curl -s -o /dev/null -w '%{http_code}\n' https://motohub.jp/   # 200 を確認
```

`kill -USR2 1` は app コンテナの PID 1（= php-fpm master, `CMD ["php-fpm"]`）に
graceful reload を指示する。処理中のリクエストを落とさずにワーカーを入れ替えられるため、
`docker compose restart app` より安全。master の PID は次で確認できる
（このイメージには `ps` が入っていないので `/proc` を見る）。

```bash
docker compose exec app sh -c "tr '\0' ' ' < /proc/1/cmdline; echo"
# => php-fpm: master process (/usr/local/etc/php-fpm.conf)
```

---

本番: ubuntu@160.16.62.193 / /var/www/motohub / Docker構成（app=php-fpm:9000, web=nginx:80,443）。
APP_ENV=production / APP_URL=https://motohub.jp。ローカル開発機（WSL, APP_ENV=local, localhost:8080）と混同しないこと。

## OPcache（コード反映では必須・データのみの投入では不要）

- 本番の有効な OPcache リセット経路は https://motohub.jp/opcache_reset.php（HTTP 200）のみ。
- app コンテナ内から http://web/opcache_reset.php を叩くと nginx が 301 を返し、fpm に届かない（リセットされない）。
- curl -f / -fsS は 301 を成功(exit 0)扱いにする。OPcache リセットの成否は HTTP 200 を明示チェックして判定する。-f の成否に頼らない。
- 本番 fpm は opcache.validate_timestamps=On / revalidate_freq=2。pull 後 約2秒で自動再読される安全網はあるが、コード反映では上記200経路で明示リセットを行う。届かなければ docker compose restart app（プロセス再起動で確実にクリア）。

## 最小反映（教習所データ等・migration/import を伴わない）

1. push 済みを確認
2. 本番 `git status --short` 単独実行（dirty なら停止）
3. `git pull --ff-only` → `git rev-parse HEAD` が想定コミットと一致
4. （コード変更を含むときのみ）`php artisan view:clear` → 200経路で OPcache リセット
5. 対象ページを curl して HTTP 200 を確認（コード反映は、落ちるときは全ページ落ちる）
6. URL の増減があるときだけ sitemap 再生成 + IndexNow
7. nginx には触れない / composer install は走らせない

## deploy-blog.sh について

- migrate / npm build / composer を含む「ブログ用フルデプロイ」スクリプト。
- 教習所データのような最小反映には使わない（migration無・import無の原則に反する）。

## 積み残し

- opcache_reset.php は無認証で誰でも叩ける。キャッシュを繰り返しクリアされる余地があり、将来的に要塞化（IP制限・トークン等）。

## IndexNow（keyLocation の実体ファイルが必須）

- IndexNow は `keyLocation` の実体ファイル `public/<key>.txt` が無いと、POST が 200（受理）でも後段のキー検証で 404 となり、送信URLが無効化される（＝反映されない）。
- キーは `env('INDEXNOW_KEY')` = `config('services.indexnow.key')`。送信は `GenerateSitemap::submitIndexNow`（`keyLocation=https://motohub.jp/<key>.txt`）。
- キーファイルは `backend/public/<key>.txt`（git管理下・全環境共通・公開前提で非秘匿・中身はキー文字列のみ/末尾改行なし）。2026-07-29 commit `d445049d` で設置済み。
- キーを環境変数で更新するときは、同名の `public/<key>.txt` も差し替えること（ファイル名＝キー値のため）。static配信なので view:clear / OPcache リセットは不要・本番は pull のみ。
