<?php

use App\Modules\Ach\Http\Controllers\AchAnalyticsController;
use App\Modules\Ach\Http\Controllers\AchTokenizeController;
use Illuminate\Support\Facades\Route;

Route::prefix('ach')->middleware(['throttle:ach-tokenize'])->group(function (): void {
    Route::post('tokenize', AchTokenizeController::class)->name('ach.tokenize');
});

Route::get('analytics/ach', AchAnalyticsController::class)->middleware('auth:sanctum');
