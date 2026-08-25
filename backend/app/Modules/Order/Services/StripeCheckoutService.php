<?php

namespace App\Modules\Order\Services;

use App\Models\Order;
use App\Modules\Ach\Services\AchCheckoutPaymentService;
use App\Modules\Order\Actions\CompletePaidCheckoutAction;
use App\Modules\Order\Actions\LogCheckoutPaymentFailureAction;
use App\Modules\Order\Actions\PrepareCheckoutAction;
use App\Modules\Order\Contracts\CheckoutPaymentServiceInterface;
use App\Modules\Order\DTOs\CheckoutPaymentInputDTO;
use App\Modules\Order\DTOs\CheckoutPaymentResultDTO;
use App\Modules\Order\DTOs\CreateFunnelOrderDTO;
use App\Modules\Order\Events\CheckoutPaymentFailed;
use App\Modules\Order\Events\CheckoutPaymentSucceeded;
use App\Modules\Order\Events\OrderPaid;
use App\Modules\Order\Exceptions\CheckoutPaymentFailedException;
use App\Modules\Payment\DTOs\PaymentRequest;
use App\Modules\Payment\Exceptions\PaymentGatewayException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class StripeCheckoutService
{
    public function __construct(
        private readonly PrepareCheckoutAction $prepareCheckout,
        private readonly CheckoutPaymentServiceInterface $checkoutPayment,
        private readonly AchCheckoutPaymentService $achCheckoutPayment,
        private readonly CompletePaidCheckoutAction $completePaidCheckout,
        private readonly LogCheckoutPaymentFailureAction $logFailure,
    ) {}

    public function submit(CreateFunnelOrderDTO $dto, CheckoutPaymentInputDTO $payment): Order
    {
        $prepared = $this->prepareCheckout->execute($dto);

        $paymentRequest = new PaymentRequest(
            amount: number_format($prepared->totals->grandTotal, 2, '.', ''),
            currency: $prepared->totals->currency,
            paymentToken: $payment->paymentToken,
            customerId: (string) $prepared->customer->id,
            orderId: (string) Str::uuid(),
            provider: $payment->provider,
            idempotencyKey: $payment->idempotencyKey,
            metadata: array_merge($dto->metadata, [
                'customer_email' => $prepared->customer->email,
                'checkout_type' => 'unified',
                'payment_method' => $payment->paymentMethod,
                'ach_provider' => $payment->achProvider,
            ]),
        );

        try {
            $result = $payment->isAch()
                ? $this->achCheckoutPayment->processAuthorizeAndCapture($paymentRequest)
                : $this->checkoutPayment->processAuthorizeAndCapture($paymentRequest);

            return DB::transaction(function () use ($dto, $prepared, $result, $payment): Order {
                $order = $this->completePaidCheckout->execute($dto, $prepared, $result, $payment);

                Event::dispatch(new CheckoutPaymentSucceeded($order, $result));
                Event::dispatch(new OrderPaid($order, $result->captureResponse));

                return $order;
            });
        } catch (PaymentGatewayException $exception) {
            $failure = $this->logFailure->execute(
                dto: $dto,
                idempotencyKey: $payment->idempotencyKey,
                paymentIntentId: $exception->context['payment_intent_id'] ?? null,
                failureCode: (string) ($exception->context['action'] ?? 'payment_gateway_error'),
                failureMessage: $exception->getMessage(),
                gatewayResponse: $exception->context,
                ipAddress: $dto->context->ipAddress,
                amount: $prepared->totals->grandTotal,
                currency: $prepared->totals->currency,
            );

            Event::dispatch(new CheckoutPaymentFailed(
                failure: $failure,
                exception: $exception,
                idempotencyKey: $payment->idempotencyKey,
            ));

            throw new CheckoutPaymentFailedException($failure, $exception);
        }
    }

    public function createPaymentIntent(CreateFunnelOrderDTO $dto, string $idempotencyKey): array
    {
        $prepared = $this->prepareCheckout->execute($dto);

        $intent = $this->checkoutPayment->createPaymentIntent(
            amount: number_format($prepared->totals->grandTotal, 2, '.', ''),
            currency: $prepared->totals->currency,
            metadata: [
                'customer_id' => (string) $prepared->customer->id,
                'customer_email' => $prepared->customer->email,
                'idempotency_key' => $idempotencyKey,
            ],
            idempotencyKey: $idempotencyKey,
        );

        return [
            'prepared' => $prepared,
            'payment_intent' => $intent,
        ];
    }
}
