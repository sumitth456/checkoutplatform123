<?php

namespace App\Modules\Ach;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

final class AchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(config_path('ach.php'), 'ach');

        $this->app->singleton(\App\Modules\Ach\Factory\AchGatewayFactory::class);
        $this->app->singleton(\App\Modules\Ach\Services\AchPaymentLogger::class);
        $this->app->singleton(\App\Modules\Ach\Services\AchTokenizationService::class);
        $this->app->singleton(\App\Modules\Ach\Services\AchCheckoutPaymentService::class);
        $this->app->singleton(\App\Modules\Ach\Services\AchAnalyticsService::class);
    }

    public function boot(): void
    {
        RateLimiter::for('ach-tokenize', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
