<?php

use App\Support\ScheduledTaskFailureLog;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

// ↓ ->runInBackground() を付けた3件だけ ->onFailure() を明示している。Laravel は
//   ScheduleRunCommand で `$event->exitCode != 0 && ! $event->runInBackground` のときしか
//   ScheduledTaskFailed を投げないため、バックグラウンド実行の失敗は RecordScheduledTaskFailure
//   （通常のスケジュール用リスナー）では捕捉できず、日次サマリの死角になるため。
//   onFailure は schedule:finish から Event::finish() 経由で呼ばれ、終了コードが入った状態で走る。
//   ※ このとき例外オブジェクトは存在しない（単に終了コードが非0なだけ）ので output は null。

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Shop市区町村バックフィル（毎日4:00 — 新規shop対応）
Schedule::command('shops:backfill-city')->dailyAt('04:00');

// 店名正規化バックフィル（毎日4:20 — スクレイパー投入分の name_normalized を埋める。
// backfill-city(04:00) の後・bulk-sold(04:50)/classify(月05:00) と非衝突）
Schedule::command('shops:normalize-names')->dailyAt('04:20');

// Shop種別分類（毎週月曜5:00 — Webike店舗クロール後にservice_tagsからshop_typeを導出）
Schedule::command('shops:classify')->weeklyOn(1, '05:00');

// 症状診断ファネルの計測イベントを180日で削除（毎日1:10 — 早朝の空き枠・他ジョブと非衝突）
Schedule::command('trouble:prune')->dailyAt('01:10');

// 本番バックアップ（spatie/laravel-backup → Cloudflare R2 / 非本番は local）。
// 03:10実行 → 03:50世代クリーンアップ → 08:00健全性チェック（失敗時のみメール通知）。
// 他ジョブと非衝突: 01:10 prune / 04:00 backfill-city / 04:20 normalize-names / 05:10 warmer の前の空き枠。
Schedule::command('backup:run')->dailyAt('03:10')->withoutOverlapping();
Schedule::command('backup:clean')->dailyAt('03:50');
Schedule::command('backup:monitor')->dailyAt('08:00');

// ブログ予約投稿チェック（毎分）
Schedule::command('blog:publish-scheduled')->everyMinute();

// ブログサイトマップ生成（毎日3:00）
Schedule::command('blog:generate-sitemap')->dailyAt('03:00');

// YouTube動画取得（毎日3:00・render pathから分離）
// DB動画が無い在庫車種を人気順に最大80件/日 = 8,000 units でquota厳守。
// 旧 youtube:fetch-videos --chunk=50 を置換（render pathがAPIを叩かなくなったため一本化）。
$youtubeRefresh = Schedule::command('youtube:refresh')->dailyAt('03:00')->withoutOverlapping()->runInBackground();
$youtubeRefresh->onFailure(fn () => ScheduledTaskFailureLog::recordEvent($youtubeRefresh));

// YouTube動画リフレッシュ（毎週月曜3:30）
Schedule::command('youtube:refresh-videos --days=30')->weeklyOn(1, '03:30');

// バイクニュース取得（毎時）
Schedule::command('news:fetch')->hourly();

// 楽天パーツ事前取得（在庫車種を日次ローテーション・render pathから分離）
// 失効分のみ約800件/日 → 7日TTLで全在庫車種(~4300)をカバー。A案のためwarmとの順序不問。
$partsRefresh = Schedule::command('parts:refresh')->dailyAt('02:00')->withoutOverlapping()->runInBackground();
$partsRefresh->onFailure(fn () => ScheduledTaskFailureLog::recordEvent($partsRefresh));

// 車種別ニュース事前取得（Google News RSS・render pathから分離）
// RSSは1コール~2sと軽いので上限大きめ。7日TTLで在庫全車種を日次カバー。
$newsRefresh = Schedule::command('news:refresh')->dailyAt('02:30')->withoutOverlapping()->runInBackground();
$newsRefresh->onFailure(fn () => ScheduledTaskFailureLog::recordEvent($newsRefresh));

// 一括sold_out除外IDの事前計算は bootstrap/app.php の 04:50 に一本化（cache:warm-ranking 05:10 の前）

// 売却済み在庫のローカル画像をR2転送後に掃除（毎日6:30）。
// listings:migrate-images-to-r2（bootstrap/app.php で毎日6:00・--since-hours=30）が
// R2へ転送し終えてから走る必要があるため、その30分後の6:30に配置する。R2未確認は削除しない
// ガードも併存するが、転送完了後に回すことで取りこぼしを防ぐ。対象は売却済み在庫のみ（稼働在庫は
// 消すと次回クロールで再取得が走るため絶対に対象にしない — 2026-08-07 の実測に基づく設計）。
Schedule::command('listings:prune-local-images --execute')->dailyAt('06:30');

// ランキングニュース自動生成
Schedule::command('news:generate-ranking --type=daily')->dailyAt('06:00');
Schedule::command('news:generate-ranking --type=weekly')->weeklyOn(1, '06:30');
Schedule::command('news:generate-ranking --type=monthly')->monthlyOn(1, '07:00');

// ランキングニュース X(Twitter)自動投稿 → 停止（Xシェアボタンに移行）
// Schedule::command('twitter:post-ranking --type=daily')->dailyAt('06:05');
// Schedule::command('twitter:post-ranking --type=weekly')->weeklyOn(1, '06:35');
// Schedule::command('twitter:post-ranking --type=monthly')->monthlyOn(1, '07:05');

// 週間相場速報生成（X投稿は停止）
Schedule::command('news:generate-weekly-report --publish')->weeklyOn(1, '08:30');

// 新車発表→中古影響分析記事（X投稿は停止）
Schedule::command('news:generate-new-model-impact --publish')->dailyAt('09:00');

// 月次市場レポート生成（X投稿は停止）
Schedule::command('news:generate-monthly-report --publish')->monthlyOn(1, '08:00');

// お買い得BOT（1日1回のみ残す）
// Schedule::command('bikes:tweet-bargains')->dailyAt('12:00');

// ランキング画像付きX投稿
// 月曜8:00 - 売れ筋ランキング
Schedule::command('x:generate-ranking-image --type=weekly-sales')->weeklyOn(1, '07:55');
Schedule::command('x:post-ranking-image --type=weekly-sales')->weeklyOn(1, '08:00');

// 水曜12:00 - お買い得ランキング
Schedule::command('x:generate-ranking-image --type=bargains')->weeklyOn(3, '11:55');
Schedule::command('x:post-ranking-image --type=bargains')->weeklyOn(3, '12:00');

// 以下は週2本（月曜・水曜）に削減のため停止
// 金曜18:00 - 都道府県ランキング
// Schedule::command('x:post-ranking-image --type=prefecture')->weeklyOn(5, '18:00');

// 火曜12:00 - 10万円以下ランキング
// Schedule::command('x:post-ranking-image --type=budget')->weeklyOn(2, '12:00');

// 木曜18:00 - 即売れランキング
// Schedule::command('x:post-ranking-image --type=fast-selling')->weeklyOn(4, '18:00');

// 土曜10:00 - 値上がりランキング
// Schedule::command('x:post-ranking-image --type=price-up')->weeklyOn(6, '10:00');

// 日曜10:00 - 排気量別ランキング
// Schedule::command('x:post-ranking-image --type=displacement')->weeklyOn(0, '10:00');

// POIデータ取得（毎日3:30 — Overpass APIからGS・コンビニ・道の駅）
// 標準出力を捨てると、どの地方が 504 で失敗したのか事後に追えないため poi.log へ残す。
Schedule::command('poi:fetch')->dailyAt('03:30')->appendOutputTo(storage_path('logs/poi.log'));

// POI市区町村割り当て（毎日4:10 — poi:fetch(3:30)で増えたPOIに座標→N03ポリゴンから市区町村を付与）。
// 未割当(municipality_code=NULL)のままだと市区町村まとめページにもサイトマップにも出ない。poi:geocode(4:30)より前に実行すること。
Schedule::command('pois:assign-municipality --execute')->dailyAt('04:10')->appendOutputTo(storage_path('logs/poi.log'));

// POI住所逆ジオコーディング（毎日4:30 — 5000件ずつ段階処理）
Schedule::command('poi:geocode')->dailyAt('04:30')->appendOutputTo(storage_path('logs/poi.log'));

// OGP画像キャッシュの掃除（毎日4:40 — 30日より古い ogp/ 配下のキャッシュを削除）。
// 遅延生成キャッシュなので消えても次アクセスで再生成される。掃除が無く月2GB増だった対策。
// 保持日数を30日にする理由: 本番実測で ogp は月2.4万件・約2GBのペースで増える。90日保持だと
// 定常状態が約7万件・5.6GBになるため、上限を約2GBに抑える30日とする（コマンド既定の90日は据え置き）。
// 直前の poi:geocode(4:30) と重ならないよう 4:40 に配置（他ジョブとも非衝突の空き枠）。
Schedule::command('ogp:prune --execute --days=30')->dailyAt('04:40')->appendOutputTo(storage_path('logs/poi.log'));

// 日次異常サマリ通知（毎日8:00 — 前日のスケジュール失敗・ERROR/WARNING・ディスク使用率を1通に）。
// 「laravel.log に記録はあるが誰も見ない」状態の解消が目的なので、異常が無い日も必ず送る
// （届かないこと自体を「通知の仕組みが壊れた」サインとして使うため）。
Schedule::command('ops:daily-report')->dailyAt('08:00')->appendOutputTo(storage_path('logs/ops.log'));

// ディスク使用率チェック（毎日7:00 — backup:monitor 08:00 の前）
// ディスク100%→全ページ500の再発防止。しきい値超過時のみメール通知。
// appendOutputTo は付けない（無制限ログ増加を新規に増やさないため）。
Schedule::command('system:check-disk')->dailyAt('07:00');
