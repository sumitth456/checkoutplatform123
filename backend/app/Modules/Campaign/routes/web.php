<?php

use App\Modules\Campaign\Http\Controllers\CampaignCheckoutWebController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'throttle:120,1'])->group(function (): void {
    Route::get('checkout/{campaign}', [CampaignCheckoutWebController::class, 'show'])
        ->name('campaign.checkout');
});
