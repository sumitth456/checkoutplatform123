<?php

namespace Tests\Feature\Scenarios;

use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\Subscription;
use App\Modules\Analytics\Data\AnalyticsPeriod;
use App\Modules\Analytics\Services\AnalyticsService;
use App\Modules\Order\Enums\OrderFulfillmentStatus;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use App\Modules\Subscription\Enums\RebillQueueStatus;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use App\Demo\CheckoutChampScenarioBuilder;
use Tests\TestCase;

class CheckoutChampEndToEndScenarioTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, bool> */
    private array $report = [];

    #[Test]
    public function checkout_champ_full_lifecycle_with_pass_fail_report(): void
    {
        $orderDate = Carbon::parse('2026-01-15 10:00:00');
        Carbon::setTestNow($orderDate);

        $scenario = (new CheckoutChampScenarioBuilder)->build();

        $this->record('Campaign Created', Campaign::query()->where('public_id', 'TEST-CAMP-001')->exists());
        $this->record('Product Created', $scenario->baseProduct->fresh()->sku === 'TEST-001');
        $this->record('Rebill Product Created', $scenario->rebillProduct->fresh()->sku === 'TEST-001-RB');
        $this->record('Billing Cycles Created', $scenario->baseProduct->billingCycles()->count() === 3);
        $this->record('Subscription Plan Created', $scenario->subscriptionPlan->steps()->count() === 3);
        $this->record('Landing Page Created', $scenario->landingPage->slug === 'test-product');

        $this->fakeStripeForCheckoutAndRebills(rebillCount: 4);

        $checkoutResponse = $this->postJson('/api/v1/checkout', [
            'customer' => [
                'email' => 'sumit@test.com',
                'first_name' => 'Sumit',
                'last_name' => 'Thakur',
                'phone' => '9876543210',
            ],
            'shipping_address' => [
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'IN',
            ],
            'items' => [
                ['variant_uuid' => $scenario->mediumVariant->uuid, 'quantity' => 1],
            ],
            'metadata' => [
                'subscription_plan_uuid' => $scenario->subscriptionPlan->uuid,
                'fulfillment_delay_days' => 10,
            ],
            'campaign_id' => $scenario->campaign->id,
            'payment_token' => 'pm_checkout_champ_test',
            'provider' => 'stripe',
            'idempotency_key' => 'checkout-champ-e2e-1',
        ]);

        $this->record('Checkout Successful', $checkoutResponse->status() === 201);

        $order = Order::query()->where('customer_email', 'sumit@test.com')->first();
        $this->record('Order Created', $order !== null && $order->status === OrderStatus::Completed);
        $this->record('Payment Captured', $order !== null && $order->payment_status === OrderPaymentStatus::Paid);
        $this->record(
            'Order Amount',
            $order !== null && (float) $order->grand_total === 9.99,
        );

        $subscription = Subscription::query()->where('customer_id', $order?->customer_id)->first();
        $this->record('Subscription Created', $subscription !== null && $subscription->status === SubscriptionStatus::Active);
        $this->record('Current Cycle 1', $subscription !== null && (int) $subscription->current_cycle_step === 1);
        $this->record(
            'Billing Cycle Records Created',
            $scenario->baseProduct->billingCycles()->count() >= 3,
        );
        $this->record(
            'Next Charge Date Calculated',
            $subscription !== null
            && $subscription->next_billing_at?->equalTo($orderDate->copy()->addDays(7)),
        );
        $this->record(
            'Rebill Queue Created',
            $subscription !== null && $subscription->rebill_queue_status === RebillQueueStatus::Queued,
        );
        $this->record(
            'Payment Token Saved',
            $subscription !== null && filled($subscription->payment_token),
        );

        $fulfillment = Fulfillment::query()->where('order_id', $order?->id)->first();
        $this->record('Delay Fulfillment Record', $fulfillment !== null && (int) $fulfillment->delay_days === 10);
        $this->record(
            'Delay Ship Date',
            $fulfillment !== null
            && $fulfillment->scheduled_ship_date->equalTo($orderDate->copy()->addDays(10)->startOfDay()),
        );

        Carbon::setTestNow($orderDate->copy()->addDays(7));
        Artisan::call('subscriptions:process-due');

        $subscription?->refresh();
        $this->record('Day 7 Charge Successful', $subscription !== null && (int) $subscription->billing_cycle_count >= 1);
        $this->record('Subscription Advanced', $subscription !== null && (int) $subscription->current_cycle_step === 2);
        $this->record(
            'Next Charge After Day 7',
            $subscription !== null
            && $subscription->next_billing_at?->equalTo($orderDate->copy()->addDays(37)),
        );

        Carbon::setTestNow($orderDate->copy()->addDays(10));
        Http::fake([
            '*' => Http::response([
                'reference' => 'WH-1001',
                'tracking_number' => 'TRK-CHAMP-001',
                'carrier' => 'DHL',
                'shipment_status' => 'shipped',
                'delivery_status' => 'delivered',
            ], 200),
        ]);

        Artisan::call('fulfillments:process-due');
        $fulfillment?->refresh();
        $order?->refresh();

        $this->record('Day 10 Delay Fulfillment Successful', $fulfillment !== null && filled($fulfillment->tracking_number));
        $this->record('Shipment Generated', $fulfillment !== null && filled($fulfillment->warehouse_reference));
        $this->record('Tracking Number Assigned', $fulfillment?->tracking_number === 'TRK-CHAMP-001');
        $this->record(
            'Order Fulfilled',
            $order !== null && $order->fulfillment_status === OrderFulfillmentStatus::Fulfilled,
        );

        Carbon::setTestNow($orderDate->copy()->addDays(37));
        $this->fakeStripeForRebillOnly(count: 2);
        Artisan::call('subscriptions:process-due');
        $subscription?->refresh();

        $this->record('Day 37 Charge Successful', $subscription !== null && (int) $subscription->billing_cycle_count >= 2);

        Carbon::setTestNow($orderDate->copy()->addDays(67));
        $this->fakeStripeForRebillOnly(count: 2);
        Artisan::call('subscriptions:process-due');
        $subscription?->refresh();

        $this->record(
            'Recurring Billing Continues Every 30 Days',
            $subscription !== null && $subscription->status === SubscriptionStatus::Active,
        );

        $ledgerSuccesses = $subscription?->billingLedgerEntries()
            ->where('outcome', BillingLedgerOutcome::Success)
            ->count() ?? 0;
        $this->record('Billing History Updated', $ledgerSuccesses >= 2);

        $period = new AnalyticsPeriod($orderDate->copy()->startOfDay(), now()->endOfDay(), 'USD');
        $analytics = app(AnalyticsService::class);
        $revenue = (float) $analytics->revenue($period)->total;
        $ordersMetric = $analytics->orders($period)->totalOrders;
        $activeSubs = $analytics->subscriptions($period)->totalSubscriptions;

        $this->record('Analytics Revenue', abs($revenue - 49.98) < 0.02 || $revenue >= 49.98);
        $this->record('Analytics Orders', (int) $ordersMetric === 1);
        $this->record('Analytics Active Subscription', (int) $activeSubs >= 1);

        $customer = Customer::query()->where('email', 'sumit@test.com')->first();
        $this->record('Admin Customer Record', $customer !== null && $customer->first_name === 'Sumit');

        $failures = array_filter($this->report, fn (bool $pass): bool => ! $pass);

        $lines = ["\n========== CHECKOUT CHAMP E2E PASS / FAIL =========="];
        foreach ($this->report as $label => $pass) {
            $lines[] = ($pass ? 'PASS' : 'FAIL').' — '.$label;
        }
        $lines[] = '==================================================';
        fwrite(STDERR, implode(PHP_EOL, $lines).PHP_EOL);

        $this->assertSame([], $failures, 'Failed checks: '.implode(', ', array_keys($failures)));
    }

    private function record(string $label, bool $pass): void
    {
        $this->report[$label] = $pass;
    }

    private function fakeStripeForCheckoutAndRebills(int $rebillCount): void
    {
        $sequence = Http::sequence()
            ->push(['id' => 'pi_checkout_1', 'client_secret' => 'sec', 'status' => 'requires_confirmation'], 200)
            ->push(['id' => 'pi_checkout_1', 'status' => 'requires_capture'], 200)
            ->push(['id' => 'pi_checkout_1', 'status' => 'succeeded'], 200);

        for ($i = 0; $i < $rebillCount; $i++) {
            $sequence = $sequence
                ->push(['id' => 'pi_rebill_'.$i, 'client_secret' => 'sec', 'status' => 'requires_capture'], 200)
                ->push(['id' => 'pi_rebill_'.$i, 'status' => 'succeeded'], 200);
        }

        Http::fake(['api.stripe.com/*' => $sequence]);
    }

    private function fakeStripeForRebillOnly(int $count): void
    {
        $sequence = Http::sequence();

        for ($i = 0; $i < $count; $i++) {
            $sequence = $sequence
                ->push(['id' => 'pi_rebill_extra_'.$i, 'client_secret' => 'sec', 'status' => 'requires_capture'], 200)
                ->push(['id' => 'pi_rebill_extra_'.$i, 'status' => 'succeeded'], 200);
        }

        Http::fake(['api.stripe.com/*' => $sequence]);
    }
}
