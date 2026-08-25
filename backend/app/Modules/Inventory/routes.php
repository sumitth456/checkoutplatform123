<?php

use App\Modules\Inventory\Http\Controllers\InventoryController;
use Illuminate\Support\Facades\Route;

Route::scopeBindings()->prefix('products/{product}/variants/{variant}')->group(function (): void {
    Route::get('inventory', [InventoryController::class, 'show']);
    Route::post('inventory/adjust', [InventoryController::class, 'adjust']);
    Route::post('inventory/restock', [InventoryController::class, 'restock']);
    Route::get('inventory/movements', [InventoryController::class, 'movements']);
    Route::post('inventory/reservations', [InventoryController::class, 'reserve']);
    Route::get('inventory/reservations', [InventoryController::class, 'reservations']);
    Route::post('inventory/reservations/{reservation}/release', [InventoryController::class, 'releaseReservation']);
    Route::post('inventory/reservations/{reservation}/commit', [InventoryController::class, 'commitReservation']);
});
