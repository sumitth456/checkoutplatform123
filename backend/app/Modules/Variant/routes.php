<?php

use App\Modules\Variant\Http\Controllers\VariantController;
use Illuminate\Support\Facades\Route;

Route::scopeBindings()->prefix('products/{product}')->group(function (): void {
    Route::get('variants', [VariantController::class, 'index']);
    Route::post('variants', [VariantController::class, 'store']);
    Route::get('variants/{variant}', [VariantController::class, 'show']);
    Route::match(['put', 'patch'], 'variants/{variant}', [VariantController::class, 'update']);
    Route::delete('variants/{variant}', [VariantController::class, 'destroy']);
    Route::post('variants/{variant}/restore', [VariantController::class, 'restore']);
});
