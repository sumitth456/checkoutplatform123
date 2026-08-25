<?php

namespace App\Modules\LandingPage;

use App\Modules\LandingPage\Services\LandingPageAnalyticsService;
use App\Modules\LandingPage\Services\LandingPageCampaignContextService;
use App\Modules\LandingPage\Services\LandingPageService;
use App\Modules\LandingPage\Services\LandingPageVersionService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Event;

class LandingPageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(LandingPageService::class);
        $this->app->singleton(LandingPageVersionService::class);
        $this->app->singleton(LandingPageAnalyticsService::class);
        $this->app->singleton(LandingPageCampaignContextService::class);
        $this->app->singleton(\App\Modules\LandingPage\Services\LandingPageVariableReplacer::class);
        $this->app->singleton(\App\Modules\LandingPage\Services\LandingPageFormService::class);
        $this->app->singleton(\App\Modules\LandingPage\Services\LandingPageAssetService::class);
        $this->app->singleton(\App\Modules\LandingPage\Services\LandingPageTemplateService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');

        Event::listen(
            \App\Modules\Order\Events\OrderPaid::class,
            \App\Modules\LandingPage\Listeners\RecordLandingPageConversionListener::class,
        );
    }
}
