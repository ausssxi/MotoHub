<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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

// ランキングニュース X(Twitter)自動投稿
Schedule::command('twitter:post-ranking --type=daily')->dailyAt('06:05');
Schedule::command('twitter:post-ranking --type=weekly')->weeklyOn(1, '06:35');
Schedule::command('twitter:post-ranking --type=monthly')->monthlyOn(1, '07:05');

// 月次相場レポート生成 + X自動投稿（毎月1日 7:00）
Schedule::command('news:generate-market-report --publish')->monthlyOn(1, '07:30')
    ->then(function () {
        Artisan::call('news:tweet-latest-report');
    });
