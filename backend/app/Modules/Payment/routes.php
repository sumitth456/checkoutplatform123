<?php

use App\Modules\Payment\Http\Controllers\PaymentController;
use App\Modules\Payment\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('payments')->group(function (): void {
    Route::post('authorize', [PaymentController::class, 'authorizePayment']);
    Route::post('capture', [PaymentController::class, 'capture']);
    Route::post('refund', [PaymentController::class, 'refund']);
    Route::post('void', [PaymentController::class, 'void']);
    Route::get('{transactionId}/status', [PaymentController::class, 'status']);
});

Route::post('webhooks/{provider}', [WebhookController::class, 'handle'])
    ->where('provider', 'paypal|authorize_net|nmi');
