<?php

namespace App\Providers;

use App\Models\BikeModel;
use App\Models\Category;
use App\Models\Listing;
use App\Models\MyBike;
use App\Models\Review; // ★作成したComposerをインポート
use App\Models\Shop;
use App\Listeners\RecordScheduledTaskFailure;
use App\Observers\ListingObserver;
use App\Observers\MyBikeObserver;
use App\Observers\ReviewObserver;
use App\Observers\ShopObserver;
use App\Services\Bike\ListingSearchService;
use App\View\Composers\WishlistComposer;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Line\Provider as LineProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 1. 掲載台数のデータ渡し
        // (ロジックが単純、かつサービス呼び出しのみなのでクロージャのままでも許容範囲です)
        View::composer('*', function ($view) {
            $service = app(ListingSearchService::class);
            $view->with('totalListingsCount', $service->getActiveCount());
        });

        // 2. お気に入り件数のデータ渡し (★ここを修正)
        // ロジックを WishlistComposer クラスへ移動したので、クラス名を指定するだけでOK
        // View::composer('*', WishlistComposer::class);

        // メモ: もしナビゲーションバー以外でお気に入り数を使わないのであれば、
        // '*' (全ビュー) ではなく 'components.navigation' と指定すると
        // 無駄な計算が減り、パフォーマンスが向上します。
        // View::composer('components.navigation', WishlistComposer::class);

        // 3. フッター用SEOリンクデータ（人気車種8件＋全カテゴリ）
        View::composer('components.footer', function ($view) {
            $footerPopularBikes = Cache::remember('footer_popular_bikes', 3600, function () {
                return BikeModel::with('manufacturer')
                    ->withCount(['listings' => fn ($q) => $q->active()])
                    ->orderByDesc('listings_count')
                    ->limit(8)
                    ->get();
            });

            $footerCategories = Cache::remember('footer_categories', 3600, function () {
                return Category::orderBy('sort_order')->get();
            });

            $view->with('footerPopularBikes', $footerPopularBikes);
            $view->with('footerCategories', $footerCategories);
        });

        RateLimiter::for('ai-search', fn (Request $request) => Limit::perDay(10)->by($request->ip()));

        // 給油OCR入力補完（auth+owner）。1ユーザー1日あたりの上限（config化）。
        RateLimiter::for('ocr-extract', fn (Request $request) => Limit::perDay((int) config('garage.ocr_max_per_day', 20))
            ->by(optional($request->user())->id ?: $request->ip()));

        // 給油 音声入力補完（auth+owner）。パース(Haiku)叩きの1ユーザー1日上限（config化）。
        RateLimiter::for('voice-extract', fn (Request $request) => Limit::perDay((int) config('garage.voice_max_per_day', 40))
            ->by(optional($request->user())->id ?: $request->ip()));

        // 外部データAPI（APIキー単位）。VerifyApiKey が api_key_id を request 属性へ入れる。
        // 分・日の二段制限で乱用と負荷を防ぐ（キー未解決時は IP にフォールバック）。
        RateLimiter::for('rankings-api', function (Request $request) {
            $id = $request->attributes->get('api_key_id') ?: $request->ip();

            return [
                Limit::perMinute(10)->by('rankapi_min_'.$id),
                Limit::perDay(100)->by('rankapi_day_'.$id),
            ];
        });

        Shop::observe(ShopObserver::class);
        Listing::observe(ListingObserver::class);
        Review::observe(ReviewObserver::class);
        MyBike::observe(MyBikeObserver::class);
        \App\Models\ShopAcceptanceReport::observe(\App\Observers\ShopAcceptanceReportObserver::class);
        \App\Models\ModelAnswer::observe(\App\Observers\ModelAnswerObserver::class);
        \App\Models\DiscussionThread::observe(\App\Observers\DiscussionThreadObserver::class);
        \App\Models\DiscussionReply::observe(\App\Observers\DiscussionReplyObserver::class);

        // スケジュール失敗をDBへ記録する（ops:daily-report が日次で集計・通知する）。
        // ログの grep ではなくイベント購読なので、スケジュール登録を1箇所で拾える。
        // ※ ->runInBackground() の3件にはこのイベントが飛ばないため、routes/console.php 側で
        //   ->onFailure() を付けて同じ ScheduledTaskFailureLog へ記録している。
        Event::listen(ScheduledTaskFailed::class, RecordScheduledTaskFailure::class);

        Socialite::extend('line', function ($app) {
            $config = $app['config']['services.line'];

            return Socialite::buildProvider(LineProvider::class, $config);
        });
    }
}
