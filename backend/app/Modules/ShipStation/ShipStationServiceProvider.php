<?php

namespace App\Modules\ShipStation;

use App\Modules\Order\Events\OrderPaid;
use App\Modules\Order\Services\OrderTestDetector;
use App\Modules\ShipStation\Contracts\ShipStationServiceInterface;
use App\Modules\ShipStation\Enums\ShipStationMode;
use App\Modules\ShipStation\Listeners\DispatchShipStationOrderSyncListener;
use App\Modules\ShipStation\Services\MockShipStationService;
use App\Modules\ShipStation\Services\RealShipStationV2Service;
use App\Modules\ShipStation\Services\ShipStationAutoLabelEligibilityService;
use App\Modules\ShipStation\Services\ShipStationAutomaticFulfillmentGateService;
use App\Modules\ShipStation\Services\ShipStationAutomaticProcessingHoldService;
use App\Modules\ShipStation\Services\ShipStationConfigResolver;
use App\Modules\ShipStation\Services\ShipStationConnectionService;
use App\Modules\ShipStation\Services\ShipStationDueFulfillmentService;
use App\Modules\ShipStation\Services\ShipStationFamTeesDeferAutomaticShippingService;
use App\Modules\ShipStation\Services\ShipStationIntegrationRecordService;
use App\Modules\ShipStation\Services\ShipStationLabelService;
use App\Modules\ShipStation\Services\ShipStationOrderEligibilityService;
use App\Modules\ShipStation\Services\ShipStationOrderPaymentEnvironmentReader;
use App\Modules\ShipStation\Services\ShipStationOrderSyncExclusionService;
use App\Modules\ShipStation\Services\ShipStationOrderSyncService;
use App\Modules\ShipStation\Services\ShipStationRebillGuard;
use App\Modules\ShipStation\Services\ShipStationTrackingService;
use App\Modules\ShipStation\Services\ShipStationV2ApiClient;
use App\Modules\ShipStation\Services\ShipStationSyncCoordinator;
use App\Modules\ShipStation\Services\ShipStationTrackingJobDispatcher;
use App\Modules\ShipStation\Services\ShipStationWebhookReceiptRecoveryService;
use App\Modules\ShipStation\Services\ShipStationTrackingSyncEligibilityService;
use App\Modules\ShipStation\Services\ShipStationTrackingSyncService;
use App\Modules\ShipStation\Services\ShipStationV2ReadOnlyClient;
use App\Modules\ShipStation\Services\ShipStationV2RemoteShipmentSyncApplier;
use App\Modules\ShipStation\Services\ShipStationWebhookAuthenticator;
use App\Modules\ShipStation\Services\ShipStationWebhookDeliveryDedupService;
use App\Modules\ShipStation\Services\ShipStationWebhookIngress;
use App\Modules\ShipStation\Services\ShipStationWebhookOrderResolver;
use App\Modules\ShipStation\Services\ShipStationWebhookProcessor;
use App\Modules\ShipStation\Support\ShipStationTrackingSyncGate;
use App\Modules\ShipStation\Support\ShipStationApiLogger;
use App\Modules\ShipStation\Support\ShipStationFaceShieldLineItemClassifier;
use App\Modules\ShipStation\Support\ShipStationFulfillmentContext;
use App\Modules\ShipStation\Support\ShipStationPayloadBuilder;
use App\Modules\ShipStation\Support\ShipStationShipmentCarrierResolver;
use App\Modules\ShipStation\Support\ShipStationStatusMapper;
use App\Modules\ShipStation\Support\ShipStationV2PackageWeightCalculator;
use App\Modules\ShipStation\Support\ShipStationV2PayloadBuilder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class ShipStationServiceProvider extends ServiceProvider
{
    public function register(): void
    {

        $this->mergeConfigFrom(config_path('shipstation.php'), 'shipstation');

        $this->app->singleton(ShipStationApiLogger::class);

        $this->app->singleton(ShipStationConfigResolver::class);

        $this->app->singleton(ShipStationPayloadBuilder::class);

        $this->app->singleton(ShipStationStatusMapper::class);

        $this->app->singleton(ShipStationShipmentCarrierResolver::class);

        $this->app->singleton(ShipStationIntegrationRecordService::class);

        $this->app->singleton(ShipStationServiceInterface::class, function ($app): ShipStationServiceInterface {
            return ShipStationMode::fromConfig()->isMock()
                ? $app->make(MockShipStationService::class)
                : $app->make(RealShipStationV2Service::class);
        });

        $this->app->singleton(RealShipStationService::class);
        $this->app->singleton(RealShipStationV2Service::class);

        $this->app->singleton(MockShipStationService::class);

        $this->app->singleton(ShipStationV2ApiClient::class);
        $this->app->singleton(ShipStationFaceShieldLineItemClassifier::class);

        $this->app->singleton(ShipStationV2PackageWeightCalculator::class);
        $this->app->singleton(ShipStationV2PayloadBuilder::class);
        $this->app->singleton(ShipStationMockFulfillmentService::class);
        $this->app->singleton(OrderTestDetector::class);
        $this->app->singleton(ShipStationFamTeesDeferAutomaticShippingService::class);
        $this->app->singleton(ShipStationAutomaticFulfillmentGateService::class);
        $this->app->singleton(ShipStationAutomaticProcessingHoldService::class);
        $this->app->singleton(ShipStationOrderSyncExclusionService::class);
        $this->app->singleton(ShipStationAutoLabelEligibilityService::class);
        $this->app->singleton(ShipStationRebillGuard::class);
        $this->app->singleton(ShipStationOrderPaymentEnvironmentReader::class);
        $this->app->singleton(ShipStationOrderEligibilityService::class);
        $this->app->singleton(ShipStationFulfillmentContext::class);
        $this->app->singleton(ShipStationDueFulfillmentService::class);

        $this->app->singleton(ShipStationOrderSyncService::class);

        $this->app->singleton(ShipStationLabelService::class);

        $this->app->singleton(ShipStationTrackingService::class);

        $this->app->singleton(ShipStationCarrierSyncService::class);

        $this->app->singleton(ShipStationWebhookAuthenticator::class);
        $this->app->singleton(ShipStationWebhookProcessor::class);
        $this->app->singleton(ShipStationWebhookIngress::class);
        $this->app->singleton(ShipStationWebhookDeliveryDedupService::class);
        $this->app->singleton(ShipStationWebhookOrderResolver::class);
        $this->app->singleton(ShipStationV2ReadOnlyClient::class);
        $this->app->singleton(ShipStationV2RemoteShipmentSyncApplier::class);
        $this->app->singleton(ShipStationTrackingSyncService::class);
        $this->app->singleton(ShipStationTrackingSyncEligibilityService::class);
        $this->app->singleton(ShipStationTrackingSyncGate::class);
        $this->app->singleton(ShipStationSyncCoordinator::class);
        $this->app->singleton(ShipStationTrackingJobDispatcher::class);
        $this->app->singleton(ShipStationWebhookReceiptRecoveryService::class);
        $this->app->singleton(ShipStationConnectionService::class);

    }

    public function boot(): void
    {

        Event::listen(OrderPaid::class, DispatchShipStationOrderSyncListener::class);

        Route::prefix('api/v1')

            ->middleware('api')

            ->group(base_path('app/Modules/ShipStation/routes.php'));

    }
}
