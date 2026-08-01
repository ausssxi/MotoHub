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
        $middleware->prependToGroup('web', [
            \App\Http\Middleware\RedirectOldStationSlugs::class,
        ]);
        $middleware->alias([
            'blog.cache' => \App\Http\Middleware\BlogCacheHeaders::class,
            'api.key' => \App\Http\Middleware\VerifyApiKey::class,
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

        // 在庫画像の差分をR2へ転送。image_syncer(04:00)の後。直近30時間に更新された
        // ファイルだけを見るので日次でも軽い。
        $schedule->command('listings:migrate-images-to-r2 --since-hours=30')
                 ->dailyAt('06:00')
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

        // 成約判定フラグ(is_capped_sold)の日次事前計算 (04:00) — 出品収集後・各コンシューマの前に実行。
        // 重い ROW_NUMBER() ウィンドウを1日1回ここで処理し、リクエスト時はフラグ参照のみにする。
        $schedule->command('listings:compute-capped-sold')
                 ->dailyAt('04:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/capped_sold.log'));

        // 一括sold_out除外IDの事前計算 (04:50) — ランキングウォーマーの前に実行
        $schedule->command('ranking:compute-bulk-exclusions')
                 ->dailyAt('04:50')
                 ->withoutOverlapping();

        // タグ抽出処理 (05:00)
        // 収集したばかりの最新データから「ETC」「ワンオーナー」などのタグを生成
        $schedule->command('tags:extract')
                 ->dailyAt('05:00')
                 ->withoutOverlapping()
                 ->appendOutputTo($tagsLog);

        // 地域別中古相場（中央値）の事前計算 (05:20) — bulk除外(04:50)の後に実行
        // active在庫×8地方ブロックの中央値をテーブル化。モデルページが読む（render pathで集計しない）
        $schedule->command('stats:regional-prices')
                 ->dailyAt('05:20')
                 ->withoutOverlapping();

        // Meilisearch差分同期 (05:30)
        // PythonスクレイパーがセットしたFlaggedレコードのみ同期（フルインポート不要）
        // 手動フルインポート: php artisan scout:import 'App\Models\Listing'
        $schedule->command('scout:sync-flagged')
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

        // ランキングページのキャッシュウォーマー
        // 毎日05:10: 主要4P + 前日変動分 / 土曜14:00: 全件
        $schedule->command('cache:warm-ranking --daily')
                 ->dailyAt('05:10')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/ranking_cache_warmer.log'));

        $schedule->command('cache:warm-ranking --full')
                 ->weeklyOn(6, '14:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/ranking_cache_warmer.log'));

        // モデルページのキャッシュウォーマー (07:30 = market-stats更新後)
        // 毎日 --all で全車種をv2キーで温め直す。差分判定は対象が全体の86%≈全件で意味が薄く脆いため廃止。
        // sleep(1)で全件 約1.4時間。withoutOverlappingで翌日へのはみ出しを防止。
        $schedule->command('cache:warm-models --all')
                 ->dailyAt('07:30')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/cache_warmer.log'));

        // 値下げアラート送信 (10:00) - 1ユーザー最大3通/日
        $schedule->command('bikes:send-price-alerts')
                 ->dailyAt('10:00')
                 ->withoutOverlapping()
                 ->appendOutputTo($alertLog);

        /**
         * --- 4. Twitter Bot (自動投稿) ---
         * リンク付き投稿を停止（2026-05 Xポリシー対応）
         * 画像のみ投稿は routes/console.php の x:post-ranking-image で継続
         */

        // // お買い得車両 (1日3回)
        // $schedule->command('bikes:tweet-bargains')
        //          ->dailyAt('08:00')
        //          ->withoutOverlapping()
        //          ->appendOutputTo($bargainLog);
        // $schedule->command('bikes:tweet-bargains')
        //          ->dailyAt('12:00')
        //          ->withoutOverlapping()
        //          ->appendOutputTo($bargainLog);
        // $schedule->command('bikes:tweet-bargains')
        //          ->dailyAt('20:00')
        //          ->withoutOverlapping()
        //          ->appendOutputTo($bargainLog);

        // // 新着入荷まとめ (10:00)
        // $schedule->command('bikes:tweet-new-stock')
        //          ->dailyAt('10:00')
        //          ->withoutOverlapping()
        //          ->appendOutputTo(storage_path('logs/new_stock_tweets.log'));

        // // 新着レビュー紹介 (14:00・1日1回)
        // $schedule->command('bikes:tweet-reviews')
        //          ->dailyAt('14:00')
        //          ->withoutOverlapping()
        //          ->appendOutputTo(storage_path('logs/review_tweets.log'));

        // // 週間トレンド (日曜 11:00)
        // $schedule->command('bikes:tweet-trending')
        //          ->weeklyOn(0, '11:00')
        //          ->withoutOverlapping()
        //          ->appendOutputTo(storage_path('logs/trending_tweets.log'));

        /**
         * --- 5. 月次レポート自動生成 ---
         */

        // 毎月1日 09:00 に前月の相場レポートを自動生成・公開
        $schedule->command('blog:generate-market-report --publish --user-id=2')
                 ->monthlyOn(1, '09:00')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/market_report.log'));

        /**
         * --- 6. Push通知 ---
         */

        // 新着入荷プッシュ通知 (08:30 - データ更新後)
        $schedule->command('push:new-stock')
                 ->dailyAt('08:30')
                 ->withoutOverlapping()
                 ->appendOutputTo(storage_path('logs/push_new_stock.log'));
    })
    ->create();