<?php

use App\Modules\Order\Http\Controllers\CheckoutController;
use App\Modules\Order\Http\Controllers\OrderController;
use Illuminate\Support\Facades\Route;

Route::prefix('checkout')->group(function (): void {
    Route::post('/', [CheckoutController::class, 'submit']);
    Route::post('payment-intent', [CheckoutController::class, 'createPaymentIntent']);
});

Route::post('coupons/validate', \App\Modules\Order\Http\Controllers\CouponValidationController::class);

Route::prefix('orders')->group(function (): void {
    Route::get('/', [OrderController::class, 'index']);
    Route::post('/', [OrderController::class, 'store']);
    Route::get('{order:uuid}', [OrderController::class, 'show']);
    Route::post('{order:uuid}/checkout', [OrderController::class, 'checkout']);
    Route::post('{order:uuid}/cancel', [OrderController::class, 'cancel']);
});
