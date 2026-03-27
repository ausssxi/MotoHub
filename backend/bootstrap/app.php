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
        $middleware->trustProxies(at: '*');
        $middleware->alias([
            'blog.cache' => \App\Http\Middleware\BlogCacheHeaders::class,
        ]);
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
        $alertLog = storage_path('logs/price_alerts.log'); // ★追加: 値下げアラート用ログ

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
         * --- 駐車場データ更新（毎週火曜深夜） ---
         * 月曜の日次・週次タスクとの負荷分散のため火曜に分離
         */
        $parkingLog = storage_path('logs/parking.log');

        $schedule->command('parking:import-bikepark')->weeklyOn(2, '01:00')->withoutOverlapping()->appendOutputTo($parkingLog);
        $schedule->command('parking:import-jmpsa')->weeklyOn(2, '01:30')->withoutOverlapping()->appendOutputTo($parkingLog);
        $schedule->command('parking:enrich-bikepark')->weeklyOn(2, '02:00')->withoutOverlapping()->appendOutputTo($parkingLog);
        $schedule->command('parking:enrich-jmpsa')->weeklyOn(2, '02:30')->withoutOverlapping()->appendOutputTo($parkingLog);
        $schedule->command('parking:geocode')->weeklyOn(2, '03:00')->withoutOverlapping()->appendOutputTo($parkingLog);
        $schedule->command('shops:geocode')->weeklyOn(2, '03:30')->withoutOverlapping()->appendOutputTo($parkingLog);

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

        // カタログスペックの自動収集・穴埋め (04:30〜)
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

        // Meilisearch(検索エンジン)の同期 (05:30)
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

        // 新規追加された車種やメーカーにスラッグを自動付与 (06:30)
        $schedule->command('slug:generate')
                 ->dailyAt('06:30')
                 ->withoutOverlapping();


        // サイトマップ生成 (07:00)
        // 全ての更新が完了した後に実行
        $schedule->command('sitemap:generate')
                 ->dailyAt('07:00')
                 ->withoutOverlapping();

        // 値下げアラート送信 (10:00) - 1ユーザー最大3通/日
        $schedule->command('bikes:send-price-alerts')
                 ->dailyAt('10:00')
                 ->withoutOverlapping()
                 ->appendOutputTo($alertLog);

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

        // 新着入荷まとめ (08:00)
        $schedule->command('bikes:tweet-new-stock')
                 ->dailyAt('08:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/new_stock_tweets.log'));

        // 値下げ速報 (10:00)
        $schedule->command('bikes:tweet-price-drop')
                 ->dailyAt('10:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/price_drop_tweets.log'));

        // 週間トレンド (日曜 11:00)
        $schedule->command('bikes:tweet-trending')
                 ->weeklyOn(0, '11:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/trending_tweets.log'));

        /**
         * --- 5. Push通知 ---
         */

        // 新着入荷プッシュ通知 (08:30 - データ更新後)
        $schedule->command('push:new-stock')
                 ->dailyAt('08:30')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/push_new_stock.log'));
    })
    ->create();