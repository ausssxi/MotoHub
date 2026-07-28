# デプロイ運用メモ

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
