<?php

namespace App\Modules\LandingPage;

use App\Modules\LandingPage\Services\LandingPageAnalyticsService;
use App\Modules\LandingPage\Services\LandingPageCampaignContextService;
use App\Modules\LandingPage\Services\LandingPageService;
use App\Modules\LandingPage\Services\LandingPageVersionService;
use App\Modules\LandingPage\Services\TshirtLandingPageSettingsService;
use Illuminate\Support\Facades\Route;
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
        $this->app->singleton(TshirtLandingPageSettingsService::class);
        $this->app->singleton(\App\Modules\LandingPage\Services\TshirtLandingPageUrlGenerator::class);
        $this->app->singleton(\App\Modules\LandingPage\Services\TshirtLandingPageDomainVerificationService::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
        $this->registerTshirtHostnameRoutes();

        Event::listen(
            \App\Modules\Order\Events\OrderPaid::class,
            \App\Modules\LandingPage\Listeners\RecordLandingPageConversionListener::class,
        );
    }

    private function registerTshirtHostnameRoutes(): void
    {
        try {
            $domain = $this->app->make(TshirtLandingPageSettingsService::class)->configuredDomain();
        } catch (\Throwable) {
            $domain = null;
        }

        if (blank($domain)) {
            return;
        }

        Route::middleware(['web', 'throttle:120,1'])
            ->domain('{tshirtSlug}.'.$domain)
            ->where([
                'tshirtSlug' => '[a-z0-9]+(?:-[a-z0-9]+)*',
            ])
            ->group(function (): void {
                Route::get('/', [\App\Modules\LandingPage\Http\Controllers\LandingPagePublicController::class, 'showByHostname'])
                    ->name('landing-pages.tshirt.show');

                Route::get('/checkout-context.json', [\App\Modules\LandingPage\Http\Controllers\LandingPagePublicController::class, 'checkoutContextByHostname'])
                    ->name('landing-pages.tshirt.checkout-context');
            });
    }
}
