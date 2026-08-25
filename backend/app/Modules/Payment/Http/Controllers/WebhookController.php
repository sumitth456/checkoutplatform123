<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Services\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WebhookController extends Controller
{
    public function __construct(
        private readonly WebhookDispatcher $dispatcher,
    ) {}

    public function handle(Request $request, string $provider): JsonResponse
    {
        $providerEnum = PaymentProvider::tryFrom($provider);

        if ($providerEnum === null) {
            abort(404, 'Unknown payment provider.');
        }

        $event = $this->dispatcher->dispatch($providerEnum, $request);

        return response()->json(['received' => true, 'event' => $event->toArray()]);
    }
}
