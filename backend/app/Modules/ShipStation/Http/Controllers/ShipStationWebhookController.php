<?php

namespace App\Modules\ShipStation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ShipStation\Exceptions\ShipStationWebhookException;
use App\Modules\ShipStation\Services\ShipStationWebhookAuthenticator;
use App\Modules\ShipStation\Services\ShipStationWebhookProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShipStationWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        ShipStationWebhookAuthenticator $authenticator,
        ShipStationWebhookProcessor $processor,
    ): JsonResponse {
        try {
            $authenticator->verify($request);
            $processor->handle($request->all());
        } catch (ShipStationWebhookException) {
            return response()->json(['received' => false], 401);
        } catch (\Throwable $exception) {
            Log::warning('ShipStation webhook processing failed', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json(['received' => false], 500);
        }

        return response()->json(['received' => true]);
    }
}
