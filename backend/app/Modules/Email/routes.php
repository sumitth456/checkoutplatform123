<?php

use App\Modules\Email\Http\Controllers\EmailApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('email')->middleware(['auth:sanctum'])->group(function (): void {
    Route::post('send', [EmailApiController::class, 'send']);
    Route::post('queue', [EmailApiController::class, 'queue']);
    Route::post('queue/{item:uuid}/retry', [EmailApiController::class, 'retry']);
    Route::post('queue/{item:uuid}/cancel', [EmailApiController::class, 'cancel']);
    Route::post('preview', [EmailApiController::class, 'preview']);
    Route::get('templates', [EmailApiController::class, 'templates']);
    Route::get('providers', [EmailApiController::class, 'providers']);
    Route::get('logs', [EmailApiController::class, 'logs']);
    Route::get('analytics', [EmailApiController::class, 'analytics']);
    Route::post('providers/{provider:uuid}/test', [EmailApiController::class, 'testProvider']);
});
