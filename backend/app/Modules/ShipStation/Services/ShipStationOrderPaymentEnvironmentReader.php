<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Order;
use App\Models\PaymentGatewayConfig;
use App\Models\Transaction;

/**
 * Read-only payment environment metadata for ShipStation eligibility.
 * Delegates to PayPalConfigResolver when present (production); otherwise uses gateway config only.
 */
final class ShipStationOrderPaymentEnvironmentReader
{
    /**
     * @return array{test_mode: bool, landing_override_slug: ?string}
     */
    public function resolveRefundEnvironment(Order $order, ?Transaction $transaction = null): array
    {
        if (class_exists(\App\Modules\PayPal\Services\PayPalConfigResolver::class)) {
            /** @var object $resolver */
            $resolver = app(\App\Modules\PayPal\Services\PayPalConfigResolver::class);

            return $resolver->resolveRefundEnvironment($order, $transaction);
        }

        $transaction?->loadMissing('paymentGateway');
        $order->loadMissing('paymentGateway');

        $gateway = $transaction?->paymentGateway ?? $order->paymentGateway;

        return [
            'test_mode' => $this->resolveTestMode($gateway),
            'landing_override_slug' => null,
        ];
    }

    private function resolveTestMode(?PaymentGatewayConfig $gateway): bool
    {
        if ($gateway !== null) {
            return (bool) $gateway->test_mode;
        }

        $paypal = config('payment.providers.paypal', []);
        $mode = strtolower((string) ($paypal['mode'] ?? ''));

        return $mode === 'sandbox' || (bool) ($paypal['test_mode'] ?? false);
    }
}