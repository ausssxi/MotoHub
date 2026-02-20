<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    /**
     * MotoHub 全自動タスクスケジュール設定
     */
    ->withSchedule(function (Schedule $schedule): void {
        
        // パスとログの定義
        $basePath = '/var/scraper';
        $crawlingLog = storage_path('logs/crawling.log');
        $bargainLog = storage_path('logs/bargain_tweets.log');
        $statsLog = storage_path('logs/stats.log');

        $sites = ['goobike', 'bds', 'webike'];

        /**
         * --- 1. 月次タスク (毎月1日) ---
         * 車種マスタのメンテナンス：実行順序が重要
         */
        
        // ① 車種マスタ収集 (1:00)
        foreach ($sites as $site) {
            $schedule->exec("python3 {$basePath}/{$site}/model_collector.py")
                     ->monthlyOn(1, '01:00')
                     ->withoutOverlapping()
                     ->appendOutputTo($crawlingLog);
        }

        // ② カテゴリー・スペック補完 (1:30) - 車種ができてから実行
        foreach ($sites as $site) {
            $schedule->exec("python3 {$basePath}/{$site}/category_collector.py")
                     ->monthlyOn(1, '01:30')
                     ->withoutOverlapping()
                     ->appendOutputTo($crawlingLog);
        }

        // ③ 排気量データの補完 (1:45)
        $schedule->exec("python3 {$basePath}/bds/displacement_collector.py")
                 ->monthlyOn(1, '01:45')
                 ->withoutOverlapping()
                 ->appendOutputTo($crawlingLog);

        /**
         * --- 2. 週次タスク (毎週月曜日) ---
         */
        
        // 店舗情報の更新 (2:00)
        foreach ($sites as $site) {
            $schedule->exec("python3 {$basePath}/{$site}/shop_collector.py")
                     ->weeklyOn(1, '02:00')
                     ->withoutOverlapping()
                     ->appendOutputTo($crawlingLog);
        }

        /**
         * --- 3. 日次タスク (毎日実行) ---
         */

        // 出品情報の収集 (深夜 03:00)
        foreach ($sites as $site) {
            $schedule->exec("python3 {$basePath}/{$site}/listing_collector.py")
                     ->dailyAt('03:00')
                     ->withoutOverlapping()
                     ->appendOutputTo($crawlingLog);
        }

        // 画像のローカル同期 (深夜 04:30)
        $schedule->exec("python3 {$basePath}/common/image_syncer.py")
                 ->dailyAt('04:30')
                 ->withoutOverlapping()
                 ->appendOutputTo($crawlingLog);

        // 【最重要】市場価格の再計算・グラフキャッシュ更新 (早朝 05:00)
        // クローリング完了直後に実行し、詳細ページの爆速表示データを生成
        $schedule->command('bikes:update-market-stats')
                 ->dailyAt('05:00')
                 ->withoutOverlapping()
                 ->appendOutputTo($statsLog);

        // 検索高速化用データの最適化 (毎時)
        $schedule->command('bikes:optimize-search-data')
                 ->hourly()
                 ->withoutOverlapping();

        // サイトマップ生成 (毎日 朝 06:00)
        // すべてのデータ更新が終わった後にGoogleへ通知
        $schedule->command('sitemap:generate')
                 ->dailyAt('06:00')
                 ->withoutOverlapping();

        /*
         * --- 4. Twitter Bot (自動投稿) ---
         */
        
        // お買い得車両 (9:00 〜 23:00 の間、1時間に1回チェック)
        // hourly() は毎時0分に実行されます。
        // between('9:00', '23:00') で時間帯を制限します。
        $schedule->command('bikes:tweet-bargains')
                 ->hourly()
                 ->between('9:00', '23:00')
                 ->withoutOverlapping()
                 ->appendOutputTo($bargainLog);

        // 新着レビューの紹介 (毎日 12:15 と 20:00)
        $schedule->command('bikes:tweet-reviews')
                 ->twiceDaily(12, 20)
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/review_tweets.log'));
    })
    ->create();