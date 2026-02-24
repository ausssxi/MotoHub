<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB; // ★DBファサードの読み込みを追加

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
        $tagsLog = storage_path('logs/tags.log');
        $specsLog = storage_path('logs/specs.log'); // ★追加: スペック収集用ログ
        $meiliLog = storage_path('logs/meilisearch.log'); // ★追加: 検索エンジン用ログ

        /**
         * --- 1. 月次タスク (毎月1日) ---
         * 車種マスタのメンテナンス
         */
        
        // ① 車種マスタ収集 (00:00〜) 順次実行
        $schedule->exec("python3 {$basePath}/goobike/model_collector.py")->monthlyOn(1, '00:00')->withoutOverlapping()->appendOutputTo($crawlingLog);
        $schedule->exec("python3 {$basePath}/bds/model_collector.py")->monthlyOn(1, '00:20')->withoutOverlapping()->appendOutputTo($crawlingLog);
        $schedule->exec("python3 {$basePath}/webike/model_collector.py")->monthlyOn(1, '00:40')->withoutOverlapping()->appendOutputTo($crawlingLog);

        // ② カテゴリー補完 (01:00〜)
        $schedule->exec("python3 {$basePath}/goobike/category_collector.py")->monthlyOn(1, '01:00')->withoutOverlapping()->appendOutputTo($crawlingLog);
        $schedule->exec("python3 {$basePath}/bds/category_collector.py")->monthlyOn(1, '01:20')->withoutOverlapping()->appendOutputTo($crawlingLog);
        $schedule->exec("python3 {$basePath}/webike/category_collector.py")->monthlyOn(1, '01:40')->withoutOverlapping()->appendOutputTo($crawlingLog);

        /**
         * --- 2. 週次タスク (毎週月曜日) ---
         */
        
        // 店舗情報の更新 (02:00〜)
        $schedule->exec("python3 {$basePath}/goobike/shop_collector.py")->weeklyOn(1, '02:00')->withoutOverlapping()->appendOutputTo($crawlingLog);
        $schedule->exec("python3 {$basePath}/bds/shop_collector.py")->weeklyOn(1, '02:20')->withoutOverlapping()->appendOutputTo($crawlingLog);
        $schedule->exec("python3 {$basePath}/webike/shop_collector.py")->weeklyOn(1, '02:40')->withoutOverlapping()->appendOutputTo($crawlingLog);

        /**
         * --- 3. 日次タスク (毎日実行) ---
         */

         // 本日の閲覧数をリセット (毎日深夜00:00に実行)
        $schedule->call(function () {
            // updated_atを動かさず、高速にリセットするためにQuery Builderを使用
            DB::table('listings')->update(['view_count_today' => 0]);
        })->dailyAt('00:00')->name('reset-daily-view-counts');

        // 出品情報の収集を1時間ずつずらして実行 (負荷分散)
        // GooBike (01:00)
        $schedule->exec("python3 {$basePath}/goobike/listing_collector.py")
                 ->dailyAt('01:00')
                 ->withoutOverlapping()
                 ->appendOutputTo($crawlingLog);

        // BDS (02:00)
        $schedule->exec("python3 {$basePath}/bds/listing_collector.py")
                 ->dailyAt('02:00')
                 ->withoutOverlapping()
                 ->appendOutputTo($crawlingLog);

        // Webike (03:00)
        $schedule->exec("python3 {$basePath}/webike/listing_collector.py")
                 ->dailyAt('03:00')
                 ->withoutOverlapping()
                 ->appendOutputTo($crawlingLog);

        // 画像のローカル同期 (04:00)
        $schedule->exec("python3 {$basePath}/common/image_syncer.py")
                 ->dailyAt('04:00')
                 ->withoutOverlapping()
                 ->appendOutputTo($crawlingLog);

        // ★追加: カタログスペックの自動収集・穴埋め (04:30〜)
        // 未取得の車種がない場合は数秒で終了します
        $schedule->exec("python3 {$basePath}/bikebros/spec_collector.py")
                 ->dailyAt('04:30')
                 ->withoutOverlapping()
                 ->appendOutputTo($specsLog);
                 
        $schedule->exec("python3 {$basePath}/goobike/spec_collector.py")
                 ->dailyAt('04:45')
                 ->withoutOverlapping()
                 ->appendOutputTo($specsLog);

        // タグ抽出処理 (05:00)
        // 収集したばかりの最新データから「ETC」「ワンオーナー」などのタグを生成
        $schedule->command('tags:extract')
                 ->dailyAt('05:00')
                 ->withoutOverlapping()
                 ->appendOutputTo($tagsLog);

        // ★追加: Meilisearch(検索エンジン)の同期 (05:30)
        // Pythonスクレイパーとタグ抽出で更新されたMySQLの最新状態を検索エンジンに流し込みます。
        $schedule->command('scout:import', ['App\Models\Listing'])
                 ->dailyAt('05:30')
                 ->withoutOverlapping()
                 ->appendOutputTo($meiliLog);

        // 市場価格の再計算・グラフキャッシュ更新 (06:00)
        // タグ抽出まで完了したデータをもとに統計を生成
        $schedule->command('bikes:update-market-stats')
                 ->dailyAt('06:00')
                 ->withoutOverlapping()
                 ->appendOutputTo($statsLog);

        // 検索高速化用データの最適化 (毎時)
        $schedule->command('bikes:optimize-search-data')
                 ->hourly()
                 ->withoutOverlapping();

        // サイトマップ生成 (07:00)
        // 全ての更新が完了した後に実行
        $schedule->command('sitemap:generate')
                 ->dailyAt('07:00')
                 ->withoutOverlapping();

        /**
         * --- 4. Twitter Bot (自動投稿) ---
         */
        
        // お買い得車両 (9:00 〜 23:00 の間、1時間に1回)
        $schedule->command('bikes:tweet-bargains')
                 ->hourly()
                 ->between('9:00', '23:00')
                 ->withoutOverlapping()
                 ->appendOutputTo($bargainLog);

        // 新着レビューの紹介 (12:15 と 20:00)
        $schedule->command('bikes:tweet-reviews')
                 ->twiceDaily(12, 20)
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/review_tweets.log'));
    })
    ->create();