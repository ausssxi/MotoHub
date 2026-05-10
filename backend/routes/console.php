<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Shop市区町村バックフィル（毎日4:00 — 新規shop対応）
Schedule::command('shops:backfill-city')->dailyAt('04:00');

// ブログ予約投稿チェック（毎分）
Schedule::command('blog:publish-scheduled')->everyMinute();

// ブログサイトマップ生成（毎日3:00）
Schedule::command('blog:generate-sitemap')->dailyAt('03:00');

// YouTube動画バッチ取得（毎日3:00）
Schedule::command('youtube:fetch-videos --chunk=50')->dailyAt('03:00');

// YouTube動画リフレッシュ（毎週月曜3:30）
Schedule::command('youtube:refresh-videos --days=30')->weeklyOn(1, '03:30');

// バイクニュース取得（毎時）
Schedule::command('news:fetch')->hourly();

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

// 月次相場レポート（相場速報）生成（X投稿は停止）
Schedule::command('news:generate-market-report --publish')->monthlyOn(1, '07:30');

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

// 金曜18:00 - 都道府県ランキング（47都道府県を週番号で自動ローテーション）
Schedule::command('x:post-ranking-image --type=prefecture')->weeklyOn(5, '18:00');

// 火曜12:00 - 10万円以下ランキング
Schedule::command('x:post-ranking-image --type=budget')->weeklyOn(2, '12:00');

// 木曜18:00 - 即売れランキング
Schedule::command('x:post-ranking-image --type=fast-selling')->weeklyOn(4, '18:00');

// 土曜10:00 - 値上がりランキング
Schedule::command('x:post-ranking-image --type=price-up')->weeklyOn(6, '10:00');

// 日曜10:00 - 排気量別ランキング（125/250/400/大型を週番号でローテーション）
Schedule::command('x:post-ranking-image --type=displacement')->weeklyOn(0, '10:00');

// POIデータ取得（毎日3:30 — Overpass APIからGS・コンビニ・道の駅）
Schedule::command('poi:fetch')->dailyAt('03:30');

// POI住所逆ジオコーディング（毎日4:30 — 5000件ずつ段階処理）
Schedule::command('poi:geocode')->dailyAt('04:30');
