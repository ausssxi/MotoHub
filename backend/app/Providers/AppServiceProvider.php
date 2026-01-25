<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;
use App\Services\Bike\ListingSearchService;
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
        // ナビゲーションが含まれるビュー（または全ビュー）に自動で totalListingsCount を渡す
        View::composer('*', function ($view) {
            // サービスを解決してカウントを取得
            $service = app(ListingSearchService::class);
            $view->with('totalListingsCount', $service->getActiveCount());
        });
    }
}
