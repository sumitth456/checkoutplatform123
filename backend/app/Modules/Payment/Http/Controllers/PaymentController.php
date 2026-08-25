<?php

namespace App\Modules\Payment\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\DTOs\RefundRequest;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Http\Requests\ProcessPaymentRequest;
use App\Modules\Payment\Http\Requests\RefundPaymentRequest;
use App\Modules\Payment\Http\Resources\PaymentResponseResource;
use App\Modules\Payment\Services\PaymentOrchestrator;
use App\Modules\Payment\Services\WebhookDispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentOrchestrator $orchestrator,
    ) {}

    public function authorizePayment(ProcessPaymentRequest $request): JsonResponse
    {
        $response = $this->orchestrator->processPayment(
            PaymentRequest::fromArray($request->validated()),
        );

        return (new PaymentResponseResource($response))
            ->response()
            ->setStatusCode(201);
    }

    public function capture(Request $request): PaymentResponseResource
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'provider' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string'],
        ]);

        $response = $this->orchestrator->capture(
            transactionId: $validated['transaction_id'],
            amount: (string) $validated['amount'],
            currency: strtoupper($validated['currency']),
            provider: $validated['provider'] ?? null,
            idempotencyKey: $validated['idempotency_key'] ?? null,
        );

        return new PaymentResponseResource($response);
    }

    public function refund(RefundPaymentRequest $request): PaymentResponseResource
    {
        $validated = $request->validated();

        $response = $this->orchestrator->processRefund(new RefundRequest(
            transactionId: $validated['transaction_id'],
            amount: (string) $validated['amount'],
            currency: strtoupper($validated['currency']),
            reason: $validated['reason'] ?? null,
            provider: isset($validated['provider']) ? PaymentProvider::from($validated['provider']) : null,
            idempotencyKey: $validated['idempotency_key'] ?? null,
            metadata: $validated['metadata'] ?? [],
        ));

        return new PaymentResponseResource($response);
    }

    public function void(Request $request): PaymentResponseResource
    {
        $validated = $request->validate([
            'transaction_id' => ['required', 'string'],
            'provider' => ['nullable', 'string'],
            'idempotency_key' => ['nullable', 'string'],
        ]);

        $response = $this->orchestrator->voidTransaction(
            transactionId: $validated['transaction_id'],
            provider: $validated['provider'] ?? null,
            idempotencyKey: $validated['idempotency_key'] ?? null,
        );

        return new PaymentResponseResource($response);
    }

    public function status(string $transactionId, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['nullable', 'string'],
        ]);

        $status = $this->orchestrator->getStatus(
            transactionId: $transactionId,
            provider: $validated['provider'] ?? null,
        );

        return response()->json(['transaction_id' => $transactionId, 'status' => $status->value]);
    }
}
