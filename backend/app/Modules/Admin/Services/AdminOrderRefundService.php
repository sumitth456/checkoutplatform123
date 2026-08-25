<?php

namespace App\Modules\Admin\Services;

use App\Models\AchProvider;
use App\Models\Order;
use App\Models\PaymentGatewayConfig;
use App\Models\Transaction;
use App\Modules\Ach\Services\AchCheckoutPaymentService;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Payment\DTOs\RefundRequest;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Modules\Payment\Services\PaymentOrchestrator;
use Illuminate\Support\Str;
use RuntimeException;

class AdminOrderRefundService
{
    public function __construct(
        private readonly PaymentOrchestrator $orchestrator,
        private readonly AchCheckoutPaymentService $achCheckoutPayment,
    ) {}

    public function refund(Order $order, string $amount, ?string $reason = null): Order
    {
        $amount = number_format((float) $amount, 2, '.', '');
        $refundable = (float) $order->grand_total - (float) $order->refunded_total;

        if ((float) $amount <= 0 || (float) $amount > $refundable) {
            throw new RuntimeException('Refund amount exceeds refundable balance.');
        }

        $transaction = $this->resolveRefundableTransaction($order);

        if ($transaction === null) {
            throw new RuntimeException('No refundable capture transaction found for this order.');
        }

        if ($this->isSandboxTransaction($transaction)) {
            $this->processSandboxRefund($order, $transaction, $amount, $reason);
        } elseif ($this->isAchTransaction($order, $transaction)) {
            $this->processAchRefund($order, $transaction, $amount, $reason);
        } else {
            $provider = $this->resolveProvider($order, $transaction);
            $this->assertGatewayConfigured($provider, $order, $transaction);

            $this->orchestrator->processRefund(new RefundRequest(
                transactionId: (string) ($transaction->external_id ?? $transaction->uuid),
                amount: $amount,
                currency: strtoupper((string) $order->currency),
                reason: $reason,
                provider: $provider,
                idempotencyKey: (string) Str::uuid(),
                metadata: ['order_id' => $order->id, 'source' => 'admin_panel'],
            ));
        }

        $order->refunded_total = round((float) $order->refunded_total + (float) $amount, 2);
        $order->payment_status = (float) $order->refunded_total >= (float) $order->grand_total
            ? OrderPaymentStatus::Refunded
            : OrderPaymentStatus::PartiallyRefunded;
        $order->save();

        return $order->fresh();
    }

    private function processSandboxRefund(
        Order $order,
        Transaction $transaction,
        string $amount,
        ?string $reason,
    ): void {
        Transaction::query()->create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'payment_gateway_id' => $transaction->payment_gateway_id ?? $order->payment_gateway_id,
            'parent_transaction_id' => $transaction->id,
            'type' => 'refund',
            'status' => 'refunded',
            'external_id' => 're_sandbox_'.Str::uuid(),
            'external_status' => 'refunded',
            'currency' => $order->currency,
            'amount' => $amount,
            'processed_at' => now(),
            'gateway_response' => [
                'sandbox' => true,
                'reason' => $reason,
                'source' => 'admin_panel',
                'original_transaction_id' => $transaction->external_id,
            ],
            'metadata' => [
                'sandbox' => true,
                'reason' => $reason,
            ],
        ]);
    }

    private function resolveRefundableTransaction(Order $order): ?Transaction
    {
        return $order->transactions()
            ->whereIn('type', ['capture', 'charge', 'sale', 'payment'])
            ->whereIn('status', ['captured', 'succeeded', 'completed', 'paid'])
            ->orderByDesc('processed_at')
            ->first();
    }

    private function isSandboxTransaction(Transaction $transaction): bool
    {
        $externalId = (string) ($transaction->external_id ?? '');

        if (str_starts_with($externalId, 'pi_sandbox_')) {
            return true;
        }

        $response = $transaction->gateway_response ?? [];

        if (str_starts_with((string) ($response['payment_intent_id'] ?? ''), 'pi_sandbox_')) {
            return true;
        }

        if (($response['sandbox'] ?? false) === true) {
            return true;
        }

        if (($response['capture']['metadata']['sandbox'] ?? false) === true) {
            return true;
        }

        return ($response['capture']['raw']['sandbox'] ?? false) === true;
    }

    private function processAchRefund(
        Order $order,
        Transaction $transaction,
        string $amount,
        ?string $reason,
    ): void {
        $provider = $this->resolveAchProvider($order, $transaction);

        $response = $this->achCheckoutPayment->refund(
            transactionId: (string) ($transaction->external_id ?? $transaction->uuid),
            amount: $amount,
            currency: strtoupper((string) $order->currency),
            provider: $provider,
        );

        Transaction::query()->create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'parent_transaction_id' => $transaction->id,
            'type' => 'refund',
            'status' => 'refunded',
            'external_id' => $response->gatewayReference,
            'external_status' => 'refunded',
            'refund_id' => $response->metadata['refund_id'] ?? $response->gatewayReference,
            'refunded_at' => now(),
            'currency' => $order->currency,
            'amount' => $amount,
            'payment_method_type' => 'ach',
            'payment_method_last4' => $transaction->payment_method_last4,
            'payment_method_brand' => $transaction->payment_method_brand,
            'processed_at' => now(),
            'gateway_response' => $response->toArray(),
            'metadata' => [
                'reason' => $reason,
                'source' => 'admin_panel',
                'payment_method' => 'ach',
            ],
        ]);
    }

    private function isAchTransaction(Order $order, Transaction $transaction): bool
    {
        if ($transaction->payment_method_type === 'ach' || $order->payment_method_type === 'ach') {
            return true;
        }

        $externalId = (string) ($transaction->external_id ?? '');

        return str_contains($externalId, 'sandbox_ach')
            || str_contains($externalId, 'stripe_ach')
            || str_contains($externalId, 'authorize_net_echeck')
            || str_contains($externalId, 'nmi_ach')
            || str_contains($externalId, 'usaepay_ach');
    }

    private function resolveAchProvider(Order $order, Transaction $transaction): ?AchProvider
    {
        $providerId = $order->metadata['ach_provider_id'] ?? null;

        if ($providerId !== null) {
            $provider = AchProvider::query()->find($providerId);

            if ($provider !== null) {
                return $provider;
            }
        }

        return AchProvider::query()
            ->where('status', 'enabled')
            ->orderByDesc('is_default')
            ->first();
    }

    private function resolveProvider(Order $order, Transaction $transaction): PaymentProvider
    {
        $gateway = $transaction->paymentGateway ?? $order->paymentGateway;

        if ($gateway !== null) {
            return PaymentProvider::from($gateway->provider);
        }

        return PaymentProvider::Stripe;
    }

    private function assertGatewayConfigured(
        PaymentProvider $provider,
        Order $order,
        Transaction $transaction,
    ): void {
        if ($provider !== PaymentProvider::Stripe) {
            return;
        }

        $secretKey = $this->resolveStripeSecretKey($order, $transaction);

        if ($this->isPlaceholderStripeSecretKey($secretKey)) {
            throw new RuntimeException(
                'Stripe is not configured for live refunds. Set STRIPE_SECRET_KEY in the server environment, or enter a valid secret key under Admin → Payment Gateways.',
            );
        }
    }

    private function resolveStripeSecretKey(Order $order, Transaction $transaction): ?string
    {
        $gateway = $transaction->paymentGateway ?? $order->paymentGateway;

        if ($gateway !== null) {
            $credentials = $gateway->credentials ?? [];

            if (filled($credentials['secret_key'] ?? null)) {
                return (string) $credentials['secret_key'];
            }
        }

        $dbGateway = PaymentGatewayConfig::query()
            ->where('provider', PaymentProvider::Stripe->value)
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('sort_order')
            ->first();

        if ($dbGateway !== null) {
            $credentials = $dbGateway->credentials ?? [];

            if (filled($credentials['secret_key'] ?? null)) {
                return (string) $credentials['secret_key'];
            }
        }

        $envKey = config('payment.providers.stripe.secret_key');

        return filled($envKey) ? (string) $envKey : null;
    }

    private function isPlaceholderStripeSecretKey(?string $secretKey): bool
    {
        if (blank($secretKey)) {
            return true;
        }

        $secretKey = trim($secretKey);

        return in_array($secretKey, ['sk_test', 'sk_live'], true)
            || strlen($secretKey) < 20;
    }
}
