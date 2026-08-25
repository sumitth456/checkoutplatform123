<?php

namespace App\Modules\Order\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Admin\Services\PlatformConfigResolver;
use App\Modules\Affiliate\Actions\MergeAffiliateCheckoutMetadataAction;
use App\Modules\Order\Actions\CaptureRequestContextAction;
use App\Modules\Order\DTOs\CheckoutPaymentInputDTO;
use App\Modules\Order\DTOs\CreateFunnelOrderDTO;
use App\Modules\Order\Exceptions\CheckoutPaymentFailedException;
use App\Modules\Order\Http\Requests\CreateCheckoutPaymentIntentRequest;
use App\Modules\Order\Http\Requests\SubmitCheckoutRequest;
use App\Modules\Order\Http\Resources\CheckoutResultResource;
use App\Modules\Order\Services\StripeCheckoutService;
use Illuminate\Http\JsonResponse;

class CheckoutController extends Controller
{
    public function __construct(
        private readonly StripeCheckoutService $checkout,
        private readonly CaptureRequestContextAction $captureRequestContext,
        private readonly PlatformConfigResolver $platformConfig,
        private readonly MergeAffiliateCheckoutMetadataAction $mergeAffiliateMetadata,
    ) {}

    public function submit(SubmitCheckoutRequest $request): JsonResponse
    {
        if ($this->platformConfig->checkoutMaintenanceMode()) {
            return response()->json([
                'message' => 'Checkout is temporarily unavailable.',
                'error' => ['code' => 'checkout_maintenance'],
            ], 503);
        }

        try {
            $context = $this->captureRequestContext->execute($request);
            $validated = $this->mergeAffiliateMetadata->execute($request, $request->validated());
            $dto = CreateFunnelOrderDTO::fromValidated($validated, $context);
            $payment = CheckoutPaymentInputDTO::fromValidated($validated);

            $order = $this->checkout->submit($dto, $payment);

            return (new CheckoutResultResource($order->load(['items', 'customer', 'transactions'])))
                ->response()
                ->setStatusCode(201);
        } catch (CheckoutPaymentFailedException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'error' => [
                    'code' => 'payment_failed',
                    'failure_uuid' => $exception->failure->uuid,
                ],
            ], 402);
        }
    }

    public function createPaymentIntent(CreateCheckoutPaymentIntentRequest $request): JsonResponse
    {
        if ($this->platformConfig->checkoutMaintenanceMode()) {
            return response()->json([
                'message' => 'Checkout is temporarily unavailable.',
                'error' => ['code' => 'checkout_maintenance'],
            ], 503);
        }

        $context = $this->captureRequestContext->execute($request);
        $validated = $this->mergeAffiliateMetadata->execute($request, $request->validated());
        $dto = CreateFunnelOrderDTO::fromValidated($validated, $context);
        $idempotencyKey = (string) ($validated['idempotency_key'] ?? '');

        $result = $this->checkout->createPaymentIntent($dto, $idempotencyKey);
        $prepared = $result['prepared'];
        $intent = $result['payment_intent'];

        return response()->json([
            'data' => [
                'payment_intent' => [
                    'id' => $intent->id,
                    'client_secret' => $intent->clientSecret,
                    'status' => $intent->status,
                ],
                'amount' => number_format($prepared->totals->grandTotal, 2, '.', ''),
                'currency' => $prepared->totals->currency,
            ],
        ], 201);
    }
}
