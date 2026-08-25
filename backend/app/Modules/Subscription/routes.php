<?php

use App\Modules\Subscription\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('subscriptions')->group(function (): void {
    Route::get('{subscription:uuid}', [SubscriptionController::class, 'show']);
    Route::post('{subscription:uuid}/pause', [SubscriptionController::class, 'pause']);
    Route::post('{subscription:uuid}/resume', [SubscriptionController::class, 'resume']);
    Route::post('{subscription:uuid}/cancel', [SubscriptionController::class, 'cancel']);
    Route::post('{subscription:uuid}/retry', [SubscriptionController::class, 'retry']);
});
