<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Order;
use App\Models\Transaction;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Services\OrderTestDetector;
use App\Modules\Order\Support\CustomerEmailValidator;
use App\Modules\ShipStation\Enums\ShipStationSyncStatus;
use App\Modules\ShipStation\Support\ShipStationFaceShieldLineItemClassifier;
use App\Modules\ShipStation\Support\ShipStationSubmissionDecision;
use Illuminate\Support\Facades\Log;

class ShipStationOrderEligibilityService
{
    public function __construct(
        private readonly OrderTestDetector $testOrders,
        private readonly ShipStationOrderPaymentEnvironmentReader $paymentEnvironment,
        private readonly ShipStationRebillGuard $rebillGuard,
        private readonly CustomerEmailValidator $customerEmail,
        private readonly ShipStationFaceShieldLineItemClassifier $faceShieldLineItems,
    ) {}

    public function evaluate(Order $order): ShipStationSubmissionDecision
    {
        if ($this->isRefundedForShipStation($order)) {
            return ShipStationSubmissionDecision::ineligible('ineligible_refunded');
        }

        if ($this->customerEmail->orderHasInvalidCustomerEmail($order)) {
            return ShipStationSubmissionDecision::ineligible('ineligible_invalid_customer_email');
        }

        if ($this->rebillGuard->isExcluded($order)) {
            return ShipStationSubmissionDecision::ineligible('ineligible_rebill');
        }

        if ($this->testOrders->isTestCustomerFirstName($order)) {
            return ShipStationSubmissionDecision::ineligible('ineligible_test_customer');
        }

        if ($this->testOrders->isTestOrder($order)) {
            return ShipStationSubmissionDecision::ineligible('ineligible_test_order');
        }

        if ($this->isPayPalSandboxLandingOrder($order)) {
            $capture = $this->resolveCaptureTransaction($order);
            $environment = $this->paymentEnvironment->resolveRefundEnvironment($order, $capture);
            $slug = is_string($environment['landing_override_slug'] ?? null)
                ? (string) $environment['landing_override_slug']
                : null;

            return ShipStationSubmissionDecision::ineligible('ineligible_sandbox_payment', $slug);
        }

        if ($this->faceShieldLineItems->orderHasOnlyFaceShieldLineItems($order)) {
            return ShipStationSubmissionDecision::ineligible('ineligible_face_shields_only');
        }

        return ShipStationSubmissionDecision::eligible();
    }

    public function isEligibleForShipStationSubmission(Order $order): bool
    {
        return $this->evaluate($order)->eligible;
    }

    /**
     * Sandbox/test exclusions stop warehouse jobs for ShipStation houses.
     * Rebill orders remain eligible for internal warehouse routing.
     */
    public function shouldBlockShipStationHouseWarehouseJob(Order $order): bool
    {
        $decision = $this->evaluate($order);

        if ($decision->eligible) {
            return false;
        }

        return in_array($decision->reasonCode, [
            'ineligible_test_customer',
            'ineligible_test_order',
            'ineligible_sandbox_payment',
            'ineligible_refunded',
            'ineligible_invalid_customer_email',
        ], true);
    }

    public function isRefundedForShipStation(Order $order): bool
    {
        if (in_array($order->payment_status, [
            OrderPaymentStatus::Refunded,
            OrderPaymentStatus::PartiallyRefunded,
        ], true)) {
            return true;
        }

        $grandTotal = (float) $order->grand_total;
        $refundedTotal = (float) $order->refunded_total;

        if ($grandTotal > 0 && $refundedTotal >= $grandTotal) {
            return true;
        }

        return false;
    }

    public function markSubmissionSkipped(Order $order, ShipStationSubmissionDecision $decision, ?int $fulfillmentId = null): Order
    {
        if ($order->shipstation_status === ShipStationSyncStatus::Skipped->value) {
            return $order->fresh(['shipStationIntegration', 'transactions']);
        }

        if ($decision->reasonCode === 'ineligible_invalid_customer_email') {
            Log::info('ShipStation submission skipped — invalid customer email.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer_email' => $order->customer_email,
                'fulfillment_id' => $fulfillmentId,
            ]);
        } elseif ($decision->reasonCode === 'ineligible_refunded') {
            Log::info('ShipStation submission skipped — refunded order.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_status' => $order->payment_status?->value ?? (string) $order->payment_status,
                'refunded_total' => (string) $order->refunded_total,
                'fulfillment_id' => $fulfillmentId,
            ]);
        } elseif ($decision->reasonCode === 'ineligible_test_customer') {
            Log::info('ShipStation shipment skipped for test customer', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'customer_id' => $order->customer_id,
            ]);
        } elseif ($decision->reasonCode === 'ineligible_face_shields_only') {
            Log::info('ShipStation submission skipped — order contains only face shield line items.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'fulfillment_id' => $fulfillmentId,
            ]);
        } else {
            Log::info('ShipStation submission skipped — sandbox/test order.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'fulfillment_id' => $fulfillmentId,
                'reason' => $decision->reasonCode,
                'landing_override_slug' => $decision->landingOverrideSlug,
            ]);
        }

        $order->update([
            'shipstation_status' => ShipStationSyncStatus::Skipped->value,
            'shipstation_sync_error' => null,
        ]);

        return $order->fresh(['shipStationIntegration', 'transactions']);
    }

    private function isPayPalSandboxLandingOrder(Order $order): bool
    {
        $capture = $this->resolveCaptureTransaction($order);
        $environment = $this->paymentEnvironment->resolveRefundEnvironment($order, $capture);

        return filled($environment['landing_override_slug'] ?? null)
            && ($environment['test_mode'] ?? false) === true;
    }

    private function resolveCaptureTransaction(Order $order): ?Transaction
    {
        if ($order->relationLoaded('transactions')) {
            $transaction = $order->transactions
                ->whereIn('type', ['capture', 'charge', 'sale', 'payment'])
                ->sortByDesc('processed_at')
                ->first();

            return $transaction instanceof Transaction ? $transaction : null;
        }

        return $order->transactions()
            ->whereIn('type', ['capture', 'charge', 'sale', 'payment'])
            ->orderByDesc('processed_at')
            ->first();
    }
}
