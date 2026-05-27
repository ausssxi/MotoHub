<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use App\Services\Bike\ListingSearchService;
use App\View\Composers\WishlistComposer; // ★作成したComposerをインポート
use App\Models\BikeModel;
use App\Models\Category;
use App\Models\Listing;
use App\Models\Shop;
use App\Observers\ListingObserver;
use App\Observers\ShopObserver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
        //View::composer('*', WishlistComposer::class);
        
        // メモ: もしナビゲーションバー以外でお気に入り数を使わないのであれば、
        // '*' (全ビュー) ではなく 'components.navigation' と指定すると
        // 無駄な計算が減り、パフォーマンスが向上します。
        // View::composer('components.navigation', WishlistComposer::class);

        // 3. フッター用SEOリンクデータ（人気車種8件＋全カテゴリ）
        View::composer('components.footer', function ($view) {
            $footerPopularBikes = Cache::remember('footer_popular_bikes', 3600, function () {
                return BikeModel::with('manufacturer')
                    ->withCount(['listings' => fn($q) => $q->active()])
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

        Shop::observe(ShopObserver::class);
        Listing::observe(ListingObserver::class);

        Socialite::extend('line', function ($app) {
            $config = $app['config']['services.line'];
            return Socialite::buildProvider(LineProvider::class, $config);
        });
    }
}