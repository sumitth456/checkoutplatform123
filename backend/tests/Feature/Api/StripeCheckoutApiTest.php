<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\SubscriptionPlan;
use App\Models\Variant;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Events\CheckoutPaymentFailed;
use App\Modules\Order\Events\CheckoutPaymentSucceeded;
use App\Modules\Order\Events\OrderPaid;
use App\Modules\Order\Events\OrderPlaced;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StripeCheckoutApiTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_submits_unified_checkout_with_stripe_payment_flow(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::sequence()
                ->push([
                    'id' => 'pi_checkout_submit',
                    'client_secret' => 'pi_checkout_submit_secret',
                    'status' => 'requires_confirmation',
                ], 200)
                ->push([
                    'id' => 'pi_checkout_submit',
                    'client_secret' => 'pi_checkout_submit_secret',
                    'status' => 'requires_capture',
                ], 200)
                ->push([
                    'id' => 'pi_checkout_submit',
                    'status' => 'succeeded',
                ], 200),
        ]);

        Event::fake([OrderPlaced::class, OrderPaid::class, CheckoutPaymentSucceeded::class]);
        Queue::fake();

        $product = Product::factory()->active()->create(['currency' => 'USD']);
        $variant = Variant::factory()->create([
            'product_id' => $product->id,
            'price' => 39.99,
            'currency' => 'USD',
            'track_inventory' => false,
        ]);

        $response = $this->postJson('/api/v1/checkout', [
            'customer' => [
                'email' => 'stripe-checkout@example.com',
                'first_name' => 'Stripe',
                'last_name' => 'Buyer',
            ],
            'items' => [
                ['variant_uuid' => $variant->uuid, 'quantity' => 1],
            ],
            'payment_token' => 'pm_checkout_test',
            'provider' => 'stripe',
            'idempotency_key' => 'checkout-submit-1',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.order.payment_status', OrderPaymentStatus::Paid->value)
            ->assertJsonPath('data.order.grand_total', '39.99');

        $this->assertDatabaseHas('orders', [
            'customer_email' => 'stripe-checkout@example.com',
            'payment_status' => OrderPaymentStatus::Paid->value,
        ]);

        $this->assertDatabaseHas('transactions', [
            'external_id' => 'pi_checkout_submit',
            'type' => 'capture',
        ]);

        Event::assertDispatched(OrderPlaced::class);
        Event::assertDispatched(OrderPaid::class);
        Event::assertDispatched(CheckoutPaymentSucceeded::class);
    }

    #[Test]
    public function it_creates_payment_intent_for_checkout(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'id' => 'pi_intent_only',
                'client_secret' => 'pi_intent_only_secret',
                'status' => 'requires_payment_method',
            ], 200),
        ]);

        $variant = Variant::factory()->create([
            'price' => 19.99,
            'currency' => 'USD',
            'track_inventory' => false,
        ]);

        $this->postJson('/api/v1/checkout/payment-intent', [
            'customer' => ['email' => 'intent@example.com'],
            'items' => [
                ['variant_uuid' => $variant->uuid, 'quantity' => 1],
            ],
            'idempotency_key' => 'intent-only-1',
        ])
            ->assertCreated()
            ->assertJsonPath('data.payment_intent.id', 'pi_intent_only')
            ->assertJsonPath('data.payment_intent.client_secret', 'pi_intent_only_secret')
            ->assertJsonPath('data.amount', '19.99');
    }

    #[Test]
    public function it_logs_failure_and_returns_payment_required_response(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::response([
                'error' => [
                    'message' => 'Your card was declined.',
                    'code' => 'card_declined',
                ],
            ], 402),
        ]);

        Event::fake([CheckoutPaymentFailed::class]);

        $variant = Variant::factory()->create([
            'price' => 10.00,
            'currency' => 'USD',
            'track_inventory' => false,
        ]);

        $response = $this->postJson('/api/v1/checkout', [
            'customer' => ['email' => 'declined@example.com'],
            'items' => [
                ['variant_uuid' => $variant->uuid, 'quantity' => 1],
            ],
            'payment_token' => 'pm_declined',
            'provider' => 'stripe',
            'idempotency_key' => 'checkout-fail-1',
        ]);

        $response->assertStatus(402)
            ->assertJsonPath('error.code', 'payment_failed');

        $this->assertDatabaseHas('checkout_payment_failures', [
            'customer_email' => 'declined@example.com',
            'idempotency_key' => 'checkout-fail-1',
        ]);

        Event::assertDispatched(CheckoutPaymentFailed::class);
    }

    #[Test]
    public function it_creates_subscription_when_plan_uuid_is_provided(): void
    {
        Http::fake([
            'api.stripe.com/*' => Http::sequence()
                ->push(['id' => 'pi_sub_checkout', 'client_secret' => 'secret', 'status' => 'requires_confirmation'], 200)
                ->push(['id' => 'pi_sub_checkout', 'status' => 'requires_capture'], 200)
                ->push(['id' => 'pi_sub_checkout', 'status' => 'succeeded'], 200),
        ]);

        $plan = SubscriptionPlan::factory()->checkoutChampTrialContinuity()->create();
        $variant = Variant::factory()->create([
            'price' => 29.99,
            'currency' => 'USD',
            'track_inventory' => false,
        ]);

        $this->postJson('/api/v1/checkout', [
            'customer' => ['email' => 'subscriber@example.com'],
            'items' => [
                ['variant_uuid' => $variant->uuid, 'quantity' => 1],
            ],
            'metadata' => [
                'subscription_plan_uuid' => $plan->uuid,
            ],
            'payment_token' => 'pm_subscriber',
            'provider' => 'stripe',
            'idempotency_key' => 'checkout-sub-1',
        ])->assertCreated();

        $this->assertDatabaseHas('subscriptions', [
            'subscription_plan_id' => $plan->id,
        ]);

        $this->assertDatabaseHas('customers', [
            'email' => 'subscriber@example.com',
        ]);
    }
}
