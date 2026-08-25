<?php

use App\Modules\Campaign\Http\Controllers\CampaignController;
use App\Modules\Campaign\Http\Controllers\CampaignDownsellController;
use App\Modules\Campaign\Http\Controllers\CampaignFunnelMappingController;
use App\Modules\Campaign\Http\Controllers\CampaignMerchandisingController;
use App\Modules\Campaign\Http\Controllers\CampaignOrderBumpController;
use App\Modules\Campaign\Http\Controllers\CampaignProductBillingCycleController;
use App\Modules\Campaign\Http\Controllers\CampaignProductController;
use App\Modules\Campaign\Http\Controllers\CampaignUpsellController;
use Illuminate\Support\Facades\Route;

Route::get('campaigns', [CampaignController::class, 'index']);
Route::post('campaigns', [CampaignController::class, 'store']);
Route::get('campaigns/{campaign}', [CampaignController::class, 'show']);
Route::match(['put', 'patch'], 'campaigns/{campaign}', [CampaignController::class, 'update']);
Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy']);
Route::post('campaigns/{campaign}/duplicate', [CampaignController::class, 'duplicate']);
Route::post('campaigns/{campaign}/archive', [CampaignController::class, 'archive']);

Route::prefix('campaigns/{campaign}/funnel-mappings')->group(function (): void {
    Route::get('/', [CampaignFunnelMappingController::class, 'index']);
    Route::post('/', [CampaignFunnelMappingController::class, 'store']);
    Route::match(['put', 'patch'], '{mapping}', [CampaignFunnelMappingController::class, 'update']);
    Route::delete('{mapping}', [CampaignFunnelMappingController::class, 'destroy']);
    Route::post('{mapping}/regenerate-api-key', [CampaignFunnelMappingController::class, 'regenerateApiKey']);
});

Route::prefix('campaigns/{campaign}/products')->group(function (): void {
    Route::get('/', [CampaignProductController::class, 'index']);
    Route::post('bulk-add', [CampaignProductController::class, 'bulkAdd']);
    Route::post('bulk-remove', [CampaignProductController::class, 'bulkRemove']);
    Route::post('bulk-replace', [CampaignProductController::class, 'bulkReplace']);
    Route::post('sort-order', [CampaignProductController::class, 'updateSortOrder']);
    Route::post('priority', [CampaignProductController::class, 'updatePriority']);
    Route::post('rules', [CampaignProductController::class, 'updateRules']);
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('campaigns/{campaign}/billing-cycles/export', [CampaignProductBillingCycleController::class, 'exportCampaign']);

    Route::prefix('campaigns/{campaign}/product-lines/{campaignProduct}/billing-cycles')->group(function (): void {
        Route::get('/', [CampaignProductBillingCycleController::class, 'index']);
        Route::post('/', [CampaignProductBillingCycleController::class, 'store']);
        Route::post('sync', [CampaignProductBillingCycleController::class, 'sync']);
        Route::post('inherit-from-product', [CampaignProductBillingCycleController::class, 'inherit']);
        Route::get('export', [CampaignProductBillingCycleController::class, 'exportLine']);
        Route::post('import', [CampaignProductBillingCycleController::class, 'importLine']);
        Route::get('{billingCycle}', [CampaignProductBillingCycleController::class, 'show']);
        Route::match(['put', 'patch'], '{billingCycle}', [CampaignProductBillingCycleController::class, 'update']);
        Route::delete('{billingCycle}', [CampaignProductBillingCycleController::class, 'destroy']);
    });
});

Route::get('campaigns/{campaign}/merchandising', [CampaignMerchandisingController::class, 'show']);

Route::middleware('throttle:120,1')->group(function (): void {
    Route::get('campaigns/{campaign}/checkout', [\App\Modules\Campaign\Http\Controllers\CampaignCheckoutController::class, 'show']);
    Route::post('campaigns/{campaign}/checkout/calculate', [\App\Modules\Campaign\Http\Controllers\CampaignCheckoutController::class, 'calculate']);
});

Route::prefix('campaigns/{campaign}/upsells')->group(function (): void {
    Route::get('/', [CampaignUpsellController::class, 'index']);
    Route::post('/', [CampaignUpsellController::class, 'store']);
    Route::match(['put', 'patch'], '{upsell}', [CampaignUpsellController::class, 'update']);
    Route::delete('{upsell}', [CampaignUpsellController::class, 'destroy']);
});

Route::prefix('campaigns/{campaign}/downsells')->group(function (): void {
    Route::get('/', [CampaignDownsellController::class, 'index']);
    Route::post('/', [CampaignDownsellController::class, 'store']);
    Route::match(['put', 'patch'], '{downsell}', [CampaignDownsellController::class, 'update']);
    Route::delete('{downsell}', [CampaignDownsellController::class, 'destroy']);
});

Route::prefix('campaigns/{campaign}/order-bumps')->group(function (): void {
    Route::get('/', [CampaignOrderBumpController::class, 'index']);
    Route::post('/', [CampaignOrderBumpController::class, 'store']);
    Route::match(['put', 'patch'], '{orderBump}', [CampaignOrderBumpController::class, 'update']);
    Route::delete('{orderBump}', [CampaignOrderBumpController::class, 'destroy']);
});
