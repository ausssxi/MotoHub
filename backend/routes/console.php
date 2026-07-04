<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

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

// ブログ予約投稿チェック（毎分）
Schedule::command('blog:publish-scheduled')->everyMinute();

// ブログサイトマップ生成（毎日3:00）
Schedule::command('blog:generate-sitemap')->dailyAt('03:00');

// YouTube動画取得（毎日3:00・render pathから分離）
// DB動画が無い在庫車種を人気順に最大80件/日 = 8,000 units でquota厳守。
// 旧 youtube:fetch-videos --chunk=50 を置換（render pathがAPIを叩かなくなったため一本化）。
Schedule::command('youtube:refresh')->dailyAt('03:00')->withoutOverlapping()->runInBackground();

// YouTube動画リフレッシュ（毎週月曜3:30）
Schedule::command('youtube:refresh-videos --days=30')->weeklyOn(1, '03:30');

// バイクニュース取得（毎時）
Schedule::command('news:fetch')->hourly();

// 楽天パーツ事前取得（在庫車種を日次ローテーション・render pathから分離）
// 失効分のみ約800件/日 → 7日TTLで全在庫車種(~4300)をカバー。A案のためwarmとの順序不問。
Schedule::command('parts:refresh')->dailyAt('02:00')->withoutOverlapping()->runInBackground();

// 車種別ニュース事前取得（Google News RSS・render pathから分離）
// RSSは1コール~2sと軽いので上限大きめ。7日TTLで在庫全車種を日次カバー。
Schedule::command('news:refresh')->dailyAt('02:30')->withoutOverlapping()->runInBackground();

// 一括sold_out除外IDの事前計算は bootstrap/app.php の 04:50 に一本化（cache:warm-ranking 05:10 の前）

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
Schedule::command('poi:fetch')->dailyAt('03:30');

// POI住所逆ジオコーディング（毎日4:30 — 5000件ずつ段階処理）
Schedule::command('poi:geocode')->dailyAt('04:30');
