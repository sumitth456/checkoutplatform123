<?php

namespace Tests\Unit\Order;

use App\Models\BillingLedgerEntry;
use App\Models\Order;
use App\Models\Subscription;
use App\Modules\Order\Actions\CreateRebillOrderAction;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderType;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateRebillOrderActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_rebill_order_from_a_ledger_entry(): void
    {
        $subscription = Subscription::factory()->create();
        $parentOrder = Order::factory()->create([
            'customer_id' => $subscription->customer_id,
            'order_type' => OrderType::Initial,
        ]);
        $subscription->update(['order_id' => $parentOrder->id]);

        $ledgerEntry = BillingLedgerEntry::query()->create([
            'subscription_id' => $subscription->id,
            'cycle_step' => 2,
            'retry_attempt' => 0,
            'outcome' => BillingLedgerOutcome::Success,
            'amount' => 19.99,
            'currency' => 'USD',
            'idempotency_key' => 'test-key-'.uniqid(),
            'transaction_id' => 'pi_test_123',
            'attempted_at' => now(),
        ]);

        $order = app(CreateRebillOrderAction::class)->execute($subscription, $ledgerEntry);

        $this->assertSame(OrderType::Rebill, $order->order_type);
        $this->assertSame(OrderPaymentStatus::Paid, $order->payment_status);
        $this->assertSame($parentOrder->id, $order->parent_order_id);
        $this->assertSame($subscription->id, $order->source_subscription_id);
        $this->assertSame(2, $order->billing_cycle_step);
    }

    public function test_it_is_idempotent_for_the_same_ledger_entry(): void
    {
        $subscription = Subscription::factory()->create();
        $ledgerEntry = BillingLedgerEntry::query()->create([
            'subscription_id' => $subscription->id,
            'cycle_step' => 1,
            'retry_attempt' => 1,
            'outcome' => BillingLedgerOutcome::Failure,
            'amount' => 9.99,
            'currency' => 'USD',
            'idempotency_key' => 'test-key-'.uniqid(),
            'attempted_at' => now(),
        ]);

        $action = app(CreateRebillOrderAction::class);
        $first = $action->execute($subscription, $ledgerEntry);
        $second = $action->execute($subscription, $ledgerEntry);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, Order::query()->where('order_type', OrderType::Rebill)->count());
    }
}
