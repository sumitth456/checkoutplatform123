<?php

namespace App\Modules\Affiliate;

use App\Modules\Affiliate\Listeners\RecordAffiliateConversionListener;
use App\Modules\Affiliate\Services\AffiliateAttributionResolver;
use App\Modules\Affiliate\Services\AffiliateClickTrackingService;
use App\Modules\Affiliate\Services\AffiliateCommissionEngine;
use App\Modules\Affiliate\Services\AffiliateConversionService;
use App\Modules\Order\Events\OrderPaid;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AffiliateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AffiliateCommissionEngine::class);
        $this->app->singleton(AffiliateClickTrackingService::class);
        $this->app->singleton(AffiliateConversionService::class);
        $this->app->singleton(AffiliateAttributionResolver::class);
    }

    public function boot(): void
    {
        Route::middleware('web')->group(base_path('app/Modules/Affiliate/routes.php'));

        Event::listen(OrderPaid::class, RecordAffiliateConversionListener::class);
    }
}
