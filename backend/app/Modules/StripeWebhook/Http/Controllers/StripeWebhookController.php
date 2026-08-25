<?php

namespace App\Modules\StripeWebhook\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\StripeWebhook\Exceptions\StripeWebhookException;
use App\Modules\StripeWebhook\Services\StripeWebhookIngressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeWebhookIngressService $ingress,
    ) {}

    public function handle(Request $request): JsonResponse
    {
        try {
            $event = $this->ingress->receive($request)->fresh();

            return response()->json([
                'received' => true,
                'webhook_uuid' => $event->uuid,
                'status' => $event->status->value,
                'event' => $event->normalized_payload,
            ]);
        } catch (StripeWebhookException $exception) {
            return response()->json([
                'received' => false,
                'error' => $exception->getMessage(),
            ], 400);
        }
    }
}
