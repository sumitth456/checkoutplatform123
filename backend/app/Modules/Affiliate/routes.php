<?php

use App\Modules\Affiliate\Http\Controllers\AffiliateTrackingController;
use App\Modules\Affiliate\Http\Controllers\Api\AffiliateApiController;
use Illuminate\Support\Facades\Route;

Route::get('/r/{code}', [AffiliateTrackingController::class, 'redirectLink'])->name('affiliate.redirect');
Route::get('/track', [AffiliateTrackingController::class, 'track'])->name('affiliate.track');

Route::prefix('api/v1/affiliate')->group(function (): void {
    Route::get('/profile', [AffiliateApiController::class, 'profile']);
    Route::get('/clicks', [AffiliateApiController::class, 'clicks']);
    Route::get('/conversions', [AffiliateApiController::class, 'conversions']);
    Route::get('/commissions', [AffiliateApiController::class, 'commissions']);
    Route::get('/payouts', [AffiliateApiController::class, 'payouts']);
});
