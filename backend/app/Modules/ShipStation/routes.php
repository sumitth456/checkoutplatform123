<?php

use App\Modules\ShipStation\Http\Controllers\ShipStationConnectionTestController;
use App\Modules\ShipStation\Http\Controllers\ShipStationWebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/shipstation', ShipStationWebhookController::class)
    ->middleware('throttle:120,1');

Route::get('/integrations/shipstation/test', ShipStationConnectionTestController::class);
