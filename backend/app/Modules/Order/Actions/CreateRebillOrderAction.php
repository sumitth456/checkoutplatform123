<?php

namespace App\Modules\Order\Actions;

use App\Models\BillingLedgerEntry;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Modules\Order\Enums\OrderFulfillmentStatus;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Enums\OrderType;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;

class CreateRebillOrderAction
{
    public function __construct(
        private readonly GenerateRebillOrderNumberAction $generateRebillOrderNumber,
    ) {}

    public function execute(Subscription $subscription, BillingLedgerEntry $ledgerEntry): Order
    {
        $existing = Order::query()
            ->where('source_billing_ledger_entry_id', $ledgerEntry->id)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $subscription->loadMissing(['customer', 'order', 'product']);
        $parentOrder = $subscription->order;
        $success = $ledgerEntry->outcome === BillingLedgerOutcome::Success;

        $order = Order::query()->create([
            'order_number' => $this->generateRebillOrderNumber->execute(),
            'order_type' => OrderType::Rebill,
            'parent_order_id' => $parentOrder?->id,
            'source_subscription_id' => $subscription->id,
            'source_billing_ledger_entry_id' => $ledgerEntry->id,
            'billing_cycle_step' => $ledgerEntry->cycle_step,
            'rebill_number' => max(1, (int) $ledgerEntry->cycle_step),
            'rebill_retry_count' => (int) $ledgerEntry->retry_attempt,
            'customer_id' => $subscription->customer_id,
            'funnel_id' => $parentOrder?->funnel_id,
            'platform_campaign_id' => $subscription->platform_campaign_id ?? $parentOrder?->platform_campaign_id,
            'payment_gateway_id' => $subscription->payment_gateway_id ?? $parentOrder?->payment_gateway_id,
            'affiliate_id' => $parentOrder?->affiliate_id,
            'affiliate_click_id' => $parentOrder?->affiliate_click_id,
            'affiliate_offer_id' => $parentOrder?->affiliate_offer_id,
            'affiliate_sub_ids' => $parentOrder?->affiliate_sub_ids,
            'status' => OrderStatus::Completed,
            'payment_status' => $success ? OrderPaymentStatus::Paid : OrderPaymentStatus::Failed,
            'fraud_status' => $parentOrder?->fraud_status,
            'fulfillment_status' => OrderFulfillmentStatus::Fulfilled,
            'currency' => strtoupper((string) $ledgerEntry->currency),
            'subtotal' => $ledgerEntry->amount,
            'discount_total' => 0,
            'tax_total' => 0,
            'shipping_total' => 0,
            'grand_total' => $ledgerEntry->amount,
            'refunded_total' => 0,
            'customer_email' => $parentOrder?->customer_email ?? $subscription->customer?->email,
            'customer_phone' => $parentOrder?->customer_phone ?? $subscription->customer?->phone,
            'billing_address' => $parentOrder?->billing_address,
            'shipping_address' => $parentOrder?->shipping_address,
            'campaign_id' => $parentOrder?->campaign_id,
            'country_code' => $parentOrder?->country_code,
            'device_type' => $parentOrder?->device_type,
            'placed_at' => $ledgerEntry->attempted_at ?? now(),
            'paid_at' => $success ? ($ledgerEntry->attempted_at ?? now()) : null,
            'metadata' => [
                'rebill' => true,
                'subscription_uuid' => $subscription->uuid,
                'ledger_uuid' => $ledgerEntry->uuid,
                'outcome' => $ledgerEntry->outcome->value,
                'product_name' => $subscription->product?->name,
                'failure_code' => $ledgerEntry->failure_code,
                'failure_message' => $ledgerEntry->failure_message,
            ],
        ]);

        if ($success && filled($ledgerEntry->transaction_id)) {
            Transaction::query()->create([
                'order_id' => $order->id,
                'subscription_id' => $subscription->id,
                'customer_id' => $subscription->customer_id,
                'payment_gateway_id' => $order->payment_gateway_id,
                'type' => 'capture',
                'status' => 'captured',
                'external_id' => $ledgerEntry->transaction_id,
                'external_status' => 'succeeded',
                'currency' => $order->currency,
                'amount' => $order->grand_total,
                'processed_at' => $ledgerEntry->attempted_at ?? now(),
                'gateway_response' => $ledgerEntry->payload,
                'metadata' => [
                    'rebill' => true,
                    'billing_ledger_entry_uuid' => $ledgerEntry->uuid,
                ],
            ]);
        }

        return $order->fresh(['customer', 'sourceSubscription', 'parentOrder']);
    }
}
