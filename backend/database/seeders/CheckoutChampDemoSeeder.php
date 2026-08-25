<?php

namespace Database\Seeders;

use App\Demo\CheckoutChampScenarioBuilder;
use App\Models\Order;
use App\Models\Subscription;
use App\Modules\Order\DTOs\CheckoutPaymentInputDTO;
use App\Modules\Order\DTOs\CreateFunnelOrderDTO;
use App\Modules\Order\DTOs\RequestContextDTO;
use App\Modules\Order\Enums\DeviceType;
use App\Modules\Order\Services\CheckoutSubscriptionService;
use App\Modules\Order\Services\StripeCheckoutService;
use App\Modules\Product\Enums\ProductType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CheckoutChampDemoSeeder extends Seeder
{
    public function run(): void
    {
        $scenario = (new CheckoutChampScenarioBuilder)->buildIfMissing();
        $customerEmail = (string) config('checkout.demo_customer_email', 'sumit@test.com');

        $order = Order::query()->where('customer_email', $customerEmail)->first();

        if ($order === null) {
            Http::fake([
                'api.stripe.com/*' => Http::sequence()
                    ->push(['id' => 'pi_demo_sumit', 'client_secret' => 'sec', 'status' => 'requires_confirmation'], 200)
                    ->push(['id' => 'pi_demo_sumit', 'status' => 'requires_capture'], 200)
                    ->push(['id' => 'pi_demo_sumit', 'status' => 'succeeded'], 200),
            ]);

            $payload = [
                'customer' => [
                    'email' => $customerEmail,
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
            ];

            $context = new RequestContextDTO(
                ipAddress: '127.0.0.1',
                userAgent: 'CheckoutChampDemoSeeder',
                deviceType: DeviceType::Desktop,
            );

            $dto = CreateFunnelOrderDTO::fromValidated($payload, $context);
            $payment = CheckoutPaymentInputDTO::fromValidated([
                'payment_token' => 'pm_checkout_champ_demo',
                'provider' => 'stripe',
                'idempotency_key' => 'demo-seed-'.Str::uuid(),
            ]);

            $order = app(StripeCheckoutService::class)->submit($dto, $payment);

            $this->command?->info("Created demo order {$order->order_number} for Sumit Thakur ({$order->customer_email}).");
        } else {
            $this->command?->info("Demo order {$order->order_number} already exists — syncing subscription data.");
        }

        $this->syncSubscriptionForOrder($order->fresh(['items.product']));

        $subscription = Subscription::query()->where('order_id', $order->id)->first();

        $this->command?->info('--- Demo checkout complete ---');
        $this->command?->info('Landing page: '.$scenario->publicLandingUrl());
        $this->command?->info('Checkout API: '.url('/api/v1/checkout'));
        $this->command?->info("Order: {$order->order_number} ({$order->uuid}) — {$order->customer_email}");
        if ($subscription !== null) {
            $this->command?->info("Subscription: {$subscription->uuid} — status {$subscription->status->value}");
            $this->command?->info("Next billing: {$subscription->next_billing_at}");
        } else {
            $this->command?->warn('No subscription record linked to this order yet.');
        }
        $this->command?->info('Admin: '.url('/admin'));
    }

    private function syncSubscriptionForOrder(Order $order): void
    {
        foreach ($order->items as $item) {
            $product = $item->product;

            if ($product === null) {
                continue;
            }

            $shouldBeSubscription = $product->is_subscription
                || $product->type === ProductType::Subscription;

            if ($shouldBeSubscription && ! $item->is_subscription) {
                $item->update(['is_subscription' => true]);
            }
        }

        if (Subscription::query()->where('order_id', $order->id)->exists()) {
            return;
        }

        if (blank($order->metadata['subscription_plan_uuid'] ?? null)) {
            return;
        }

        $paymentToken = (string) ($order->metadata['payment_intent_id'] ?? 'pi_demo_sumit');

        app(CheckoutSubscriptionService::class)->createFromOrder(
            order: $order->fresh(['items']),
            paymentToken: $paymentToken,
            paymentProvider: 'stripe',
        );

        $subscription = Subscription::query()->where('order_id', $order->id)->first();

        if ($subscription !== null) {
            $this->command?->info("Linked subscription {$subscription->uuid}; next billing at {$subscription->next_billing_at}.");
        }
    }
}
