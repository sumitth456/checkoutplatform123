<?php

namespace App\Modules\Campaign;

use App\Models\Campaign;
use App\Modules\Campaign\Actions\ArchiveCampaignAction;
use App\Modules\Campaign\Actions\CreateCampaignAction;
use App\Modules\Campaign\Actions\DuplicateCampaignAction;
use App\Modules\Campaign\Actions\GenerateCampaignPublicIdAction;
use App\Modules\Campaign\Actions\GenerateCampaignFunnelApiKeyAction;
use App\Modules\Campaign\Services\CampaignFunnelMappingService;
use App\Modules\Campaign\Services\CampaignMerchandisingService;
use App\Modules\Campaign\Services\CampaignProductBillingCycleService;
use App\Modules\Campaign\Services\CampaignProductBulkService;
use App\Modules\Campaign\Services\CampaignCheckoutCalculateService;
use App\Modules\Campaign\Services\CampaignCheckoutContextService;
use App\Modules\Campaign\Services\CampaignCheckoutPricingService;
use App\Modules\Campaign\Services\CampaignVariantMatrixService;
use App\Modules\Campaign\Actions\UpdateCampaignAction;
use App\Modules\Campaign\Contracts\CampaignRepositoryInterface;
use App\Modules\Campaign\Observers\CampaignSubscriptionPlanObserver;
use App\Modules\Campaign\Repositories\CampaignRepository;
use App\Modules\Campaign\Services\CampaignService;
use Illuminate\Support\ServiceProvider;

class CampaignServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CampaignRepositoryInterface::class, CampaignRepository::class);

        $this->app->singleton(GenerateCampaignPublicIdAction::class);
        $this->app->singleton(CreateCampaignAction::class);
        $this->app->singleton(UpdateCampaignAction::class);
        $this->app->singleton(DuplicateCampaignAction::class);
        $this->app->singleton(ArchiveCampaignAction::class);
        $this->app->singleton(CampaignService::class);
        $this->app->singleton(CampaignProductBulkService::class);
        $this->app->singleton(CampaignProductBillingCycleService::class);
        $this->app->singleton(CampaignFunnelMappingService::class);
        $this->app->singleton(GenerateCampaignFunnelApiKeyAction::class);
        $this->app->singleton(CampaignMerchandisingService::class);
        $this->app->singleton(SubscriptionPlanCampaignBillingService::class);
        $this->app->singleton(CampaignVariantMatrixService::class);
        $this->app->singleton(CampaignCheckoutPricingService::class);
        $this->app->singleton(CampaignCheckoutContextService::class);
        $this->app->singleton(CampaignCheckoutCalculateService::class);
    }

    public function boot(): void
    {
        Campaign::observe(CampaignSubscriptionPlanObserver::class);

        $this->loadRoutesFrom(__DIR__.'/routes/web.php');
    }
}
