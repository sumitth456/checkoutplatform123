<?php

namespace App\Modules\Fulfillment;

use App\Modules\Fulfillment\Http\Controllers\FulfillmentApiController;
use App\Modules\Fulfillment\Providers\CsvExportProvider;
use App\Modules\Fulfillment\Providers\InternalWarehouseProvider;
use App\Modules\Fulfillment\Providers\ShipStationProvider;
use App\Modules\Fulfillment\Services\FulfillmentAssignmentEngine;
use App\Modules\Fulfillment\Services\FulfillmentOrchestratorService;
use App\Modules\Fulfillment\Services\FulfillmentTimelineService;
use App\Modules\Fulfillment\Services\FulfillmentTrackingImportService;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class FulfillmentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FulfillmentTimelineService::class);
        $this->app->singleton(FulfillmentAssignmentEngine::class);
        $this->app->singleton(FulfillmentOrchestratorService::class);
        $this->app->singleton(FulfillmentTrackingImportService::class);
        $this->app->singleton(InternalWarehouseProvider::class);
        $this->app->singleton(ShipStationProvider::class);
        $this->app->singleton(CsvExportProvider::class);
    }

    public function boot(): void
    {
        Route::prefix('api/v1/fulfillment')
            ->middleware('api')
            ->group(function (): void {
                Route::get('/{uuid}', [FulfillmentApiController::class, 'show']);
                Route::post('/{uuid}/tracking', [FulfillmentApiController::class, 'updateTracking']);
                Route::post('/{uuid}/cancel', [FulfillmentApiController::class, 'cancel']);
            });
    }
}
