<?php

namespace App\Providers;

use App\Models\BlogPost;
use App\Models\User;
use App\Observers\BlogPostObserver;
use App\Policies\BlogPostPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class BlogServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Gate定義
        Gate::define('manage-blog', function (User $user) {
            return in_array($user->role, ['admin', 'writer']);
        });

        Gate::define('admin-only', function (User $user) {
            return $user->role === 'admin';
        });

        // Policy登録
        Gate::policy(BlogPost::class, BlogPostPolicy::class);

        // Observer登録
        BlogPost::observe(BlogPostObserver::class);
    }
}
