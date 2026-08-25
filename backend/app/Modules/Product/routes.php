<?php

use App\Modules\Product\Http\Controllers\ProductController;
use App\Modules\Product\Http\Controllers\ProductBillingCycleController;
use App\Modules\Product\Http\Controllers\ProductImportController;
use App\Modules\Product\Http\Controllers\FunnelProductBulkController;
use Illuminate\Support\Facades\Route;

Route::get('products', [ProductController::class, 'index']);
Route::post('products', [ProductController::class, 'store']);
Route::get('products/export', [ProductImportController::class, 'exportCatalog']);
Route::get('products/{product}', [ProductController::class, 'show']);
Route::get('products/{product}/variants/checkout', \App\Modules\Product\Http\Controllers\ProductCheckoutVariantsController::class);
Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update']);
Route::delete('products/{product}', [ProductController::class, 'destroy']);
Route::post('products/{product}/restore', [ProductController::class, 'restore']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('products/{product}/billing-cycles')->group(function (): void {
        Route::get('/', [ProductBillingCycleController::class, 'index']);
        Route::post('/', [ProductBillingCycleController::class, 'store']);
        Route::post('sync', [ProductBillingCycleController::class, 'sync']);
        Route::post('seed-defaults', [ProductBillingCycleController::class, 'seedDefaults']);
        Route::get('export', [ProductBillingCycleController::class, 'export']);
        Route::post('import', [ProductBillingCycleController::class, 'import']);
        Route::get('{billingCycle}', [ProductBillingCycleController::class, 'show']);
        Route::match(['put', 'patch'], '{billingCycle}', [ProductBillingCycleController::class, 'update']);
        Route::delete('{billingCycle}', [ProductBillingCycleController::class, 'destroy']);
    });
});

Route::prefix('funnels/{funnel}/products')->group(function (): void {
    Route::post('bulk-add', [FunnelProductBulkController::class, 'bulkAdd']);
    Route::post('bulk-remove', [FunnelProductBulkController::class, 'bulkRemove']);
    Route::post('bulk-replace', [FunnelProductBulkController::class, 'bulkReplace']);
    Route::post('bulk-price', [FunnelProductBulkController::class, 'bulkUpdatePrice']);
    Route::post('bulk-subscription', [FunnelProductBulkController::class, 'bulkUpdateSubscription']);
    Route::post('bulk-inventory', [FunnelProductBulkController::class, 'bulkUpdateInventory']);
});

Route::prefix('imports')->group(function (): void {
    Route::post('/', [ProductImportController::class, 'store']);
    Route::get('{batch}', [ProductImportController::class, 'show']);
    Route::put('{batch}/mapping', [ProductImportController::class, 'updateMapping']);
    Route::post('{batch}/validate', [ProductImportController::class, 'validateBatch']);
    Route::post('{batch}/queue', [ProductImportController::class, 'queue']);
    Route::get('{batch}/error-report', [ProductImportController::class, 'downloadErrorReport']);
});
