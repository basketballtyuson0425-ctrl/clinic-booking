<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        // 公開 API のアクセス回数の制限：同じ IP アドレスから1分あたり60回まで
        RateLimiter::for('public-api', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });

        // 予約の登録：同じ IP アドレスから1分あたり10回まで
        RateLimiter::for('reservation-create', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });

        // 予約管理ページの API：同じ IP アドレスから1分あたり30回まで
        RateLimiter::for('manage', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });
    }
}
