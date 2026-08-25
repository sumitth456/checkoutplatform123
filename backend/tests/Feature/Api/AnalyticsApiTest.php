<?php

namespace Tests\Feature\Api;

use App\Models\BillingLedgerEntry;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_analytics_overview(): void
    {
        Transaction::factory()->create(['amount' => 100.00, 'processed_at' => now()]);
        Transaction::factory()->create(['amount' => 50.00, 'processed_at' => now()]);
        Transaction::factory()->refund(25.00)->create();
        Transaction::factory()->failed(40.00)->create();

        Order::factory()->create(['grand_total' => 120.00, 'placed_at' => now(), 'paid_at' => now()]);
        Subscription::factory()->active()->create(['amount' => 49.99]);

        $response = $this->getJson('/api/v1/analytics/overview?currency=USD');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'revenue' => ['total', 'transaction_count', 'average_order_value'],
                    'mrr' => ['mrr', 'arr', 'active_subscriptions'],
                    'refunds' => ['total_refunded', 'refund_count', 'refund_rate'],
                    'failed_payments' => ['failed_count', 'failed_amount'],
                    'orders' => ['total_orders', 'new_orders', 'by_status'],
                    'subscriptions' => ['total_subscriptions', 'by_status'],
                ],
            ])
            ->assertJsonPath('data.revenue.total', '150.00')
            ->assertJsonPath('data.refunds.total_refunded', '25.00')
            ->assertJsonPath('data.mrr.active_subscriptions', 1);
    }

    #[Test]
    public function it_returns_revenue_metrics(): void
    {
        Transaction::factory()->create(['amount' => 75.50, 'processed_at' => now()]);

        $this->getJson('/api/v1/analytics/revenue')
            ->assertOk()
            ->assertJsonPath('data.total', '75.50')
            ->assertJsonPath('data.transaction_count', 1);
    }

    #[Test]
    public function it_returns_mrr_from_active_subscriptions(): void
    {
        Subscription::factory()->active()->create();

        $this->getJson('/api/v1/analytics/mrr')
            ->assertOk()
            ->assertJsonPath('data.mrr', '49.99')
            ->assertJsonPath('data.arr', '599.88')
            ->assertJsonPath('data.active_subscriptions', 1);
    }

    #[Test]
    public function it_returns_order_metrics(): void
    {
        Order::factory()->count(2)->create(['placed_at' => now(), 'paid_at' => now()]);
        Order::factory()->create([
            'status' => 'cancelled',
            'payment_status' => 'failed',
            'placed_at' => now(),
        ]);

        $this->getJson('/api/v1/analytics/orders')
            ->assertOk()
            ->assertJsonPath('data.total_orders', 3)
            ->assertJsonPath('data.new_orders', 3);
    }

    #[Test]
    public function it_returns_subscription_metrics(): void
    {
        Subscription::factory()->create(['status' => SubscriptionStatus::Trial]);
        Subscription::factory()->active()->create();
        Subscription::factory()->create(['status' => SubscriptionStatus::PastDue]);

        $this->getJson('/api/v1/analytics/subscriptions')
            ->assertOk()
            ->assertJsonPath('data.total_subscriptions', 3)
            ->assertJsonPath('data.past_due_subscriptions', 1);
    }

    #[Test]
    public function it_returns_failed_payment_metrics_including_billing_failures(): void
    {
        Transaction::factory()->failed(30.00)->create();

        $subscription = Subscription::factory()->create();
        BillingLedgerEntry::create([
            'subscription_id' => $subscription->id,
            'cycle_step' => 1,
            'retry_attempt' => 1,
            'outcome' => BillingLedgerOutcome::Failure,
            'amount' => 29.99,
            'currency' => 'USD',
            'idempotency_key' => 'test-failure',
            'attempted_at' => now(),
        ]);

        $this->getJson('/api/v1/analytics/failed-payments')
            ->assertOk()
            ->assertJsonPath('data.failed_count', 1)
            ->assertJsonPath('data.billing_failure_count', 1)
            ->assertJsonPath('data.failed_amount', '30.00');
    }

    #[Test]
    public function it_validates_date_range(): void
    {
        $this->getJson('/api/v1/analytics/overview?from=2026-07-20&to=2026-07-10')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }
}
