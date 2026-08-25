<?php

namespace App\Modules\Order\Listeners;

use App\Models\Order;
use App\Models\Transaction;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Events\OrderPaid;
use App\Modules\Order\Services\PaymentFailureLoggerService;
use App\Modules\Payment\Enums\PaymentEventType;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Enums\PaymentStatus;
use App\Modules\Payment\Events\PaymentEventReceived;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Event;

class ReconcileStripePaymentWithOrderListener implements ShouldQueue
{
    public string $queue;

    public function __construct(
        private readonly PaymentFailureLoggerService $failureLogger,
    ) {
        $this->queue = (string) config('checkout.queue', 'default');
    }

    public function handle(PaymentEventReceived $event): void
    {
        if ($event->event->provider !== PaymentProvider::Stripe) {
            return;
        }

        $paymentIntentId = $event->event->externalId;
        $orderUuid = $event->event->payload['metadata']['order_uuid'] ?? null;

        $order = null;

        if (filled($orderUuid)) {
            $order = Order::query()->where('uuid', $orderUuid)->first();
        }

        if ($order === null) {
            $transaction = Transaction::query()
                ->where('external_id', $paymentIntentId)
                ->latest('id')
                ->first();

            $order = $transaction?->order;
        }

        if ($order === null) {
            return;
        }

        if ($event->event->type === PaymentEventType::Failed) {
            if ($order->payment_status === OrderPaymentStatus::Pending) {
                $this->failureLogger->log(
                    idempotencyKey: 'webhook-'.$event->event->externalId,
                    customerEmail: $order->customer_email,
                    amount: (float) $order->grand_total,
                    currency: $order->currency,
                    failureCode: (string) ($event->event->payload['last_payment_error']['code'] ?? 'payment_failed'),
                    failureMessage: (string) ($event->event->payload['last_payment_error']['message'] ?? $event->event->status),
                    paymentIntentId: $paymentIntentId,
                    requestPayload: ['order_uuid' => $order->uuid],
                    gatewayResponse: $event->event->payload,
                );
            }

            return;
        }

        if ($event->event->type !== PaymentEventType::Captured) {
            return;
        }

        if ($order->payment_status === OrderPaymentStatus::Paid) {
            return;
        }

        $order->update([
            'status' => OrderStatus::Completed,
            'payment_status' => OrderPaymentStatus::Paid,
            'paid_at' => now(),
        ]);

        Event::dispatch(new OrderPaid(
            order: $order->fresh(),
            paymentResponse: new \App\Modules\Payment\DTOs\PaymentResponse(
                status: PaymentStatus::Captured,
                transactionId: $paymentIntentId,
                gatewayReference: $paymentIntentId,
                provider: PaymentProvider::Stripe,
                amount: number_format((float) $order->grand_total, 2, '.', ''),
                currency: $order->currency,
                raw: $event->event->payload,
            ),
        ));
    }
}
