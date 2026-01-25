<?php

declare(strict_types=1);

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
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
     * MotoHub クローリングタスクのスケジュール設定
     */
    ->withSchedule(function (Schedule $schedule): void {
        // 実行スクリプトのベースパス
        $basePath = '/var/scraper';
        // ログ出力先
        $logPath = storage_path('logs/crawling.log');

        /**
         * --- 1. 日次タスク (毎日実行) ---
         */
        $sites = ['goobike', 'bds', 'webike'];

        // 出品情報の収集 (毎日 深夜3:00)
        foreach ($sites as $site) {
            $schedule->exec("python3 {$basePath}/{$site}/listing_collector.py")
                     ->dailyAt('03:00')
                     ->withoutOverlapping()
                     ->appendOutputTo($logPath);
        }

        // 画像のローカル同期 (毎日 深夜4:30)
        $schedule->exec("python3 {$basePath}/common/image_syncer.py")
                 ->dailyAt('04:30')
                 ->withoutOverlapping()
                 ->appendOutputTo($logPath);

        /**
         * --- 2. 週次タスク (毎週月曜日 2:00) ---
         */
        foreach ($sites as $site) {
            $schedule->exec("python3 {$basePath}/{$site}/shop_collector.py")
                     ->weeklyOn(1, '02:00')
                     ->withoutOverlapping()
                     ->appendOutputTo($logPath);
        }

        /**
         * --- 3. 月次タスク (毎月1日) ---
         */
        
        // 車種マスタ収集 (毎月1日 1:00)
        foreach ($sites as $site) {
            $schedule->exec("python3 {$basePath}/{$site}/model_collector.py")
                     ->monthlyOn(1, '01:00')
                     ->withoutOverlapping()
                     ->appendOutputTo($logPath);
        }

        // カテゴリ・スペック補完 (毎月1日 1:30〜)
        $schedule->exec("python3 {$basePath}/goobike/category_collector.py")
                 ->monthlyOn(1, '01:30')
                 ->withoutOverlapping()
                 ->appendOutputTo($logPath);

        $schedule->exec("python3 {$basePath}/bds/displacement_collector.py")
                 ->monthlyOn(1, '01:45')
                 ->withoutOverlapping()
                 ->appendOutputTo($logPath);

        // サイトマップ生成 (毎日 03:00)
        // ※スクレイピング(03:00開始)と被りますが、Laravelの処理とPythonは別プロセスで動くので並行稼働します。
        // もしスクレイピング完了後に更新したい場合は時間をずらす(例: 06:00)のがおすすめです。
        $schedule->command('sitemap:generate')->dailyAt('03:00');
    })
    ->create();