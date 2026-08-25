<?php

use App\Modules\Analytics\Http\Controllers\AnalyticsController;
use Illuminate\Support\Facades\Route;

Route::prefix('analytics')->group(function (): void {
    Route::get('overview', [AnalyticsController::class, 'overview']);
    Route::get('revenue', [AnalyticsController::class, 'revenue']);
    Route::get('mrr', [AnalyticsController::class, 'mrr']);
    Route::get('refunds', [AnalyticsController::class, 'refunds']);
    Route::get('failed-payments', [AnalyticsController::class, 'failedPayments']);
    Route::get('orders', [AnalyticsController::class, 'orders']);
    Route::get('subscriptions', [AnalyticsController::class, 'subscriptions']);
});
