<?php



namespace Tests\Unit\Admin;



use App\Models\PaymentGatewayConfig;

use App\Models\StripeWebhookEvent;

use App\Modules\Admin\Services\AdminWebhookService;

use App\Modules\Payment\Enums\GatewayWebhookStatus;

use App\Modules\Payment\Enums\PaymentProvider;

use App\Modules\StripeWebhook\Enums\StripeWebhookProcessingStatus;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Illuminate\Support\Str;

use Tests\TestCase;



class AdminWebhookServiceTest extends TestCase

{

    use RefreshDatabase;



    public function test_inbound_endpoints_include_all_providers(): void

    {

        $endpoints = app(AdminWebhookService::class)->inboundEndpoints();



        $this->assertCount(4, $endpoints);

        $this->assertStringContainsString('/api/v1/webhooks/stripe', $endpoints[0]['url']);

    }



    public function test_endpoint_for_gateway_matches_provider(): void

    {

        $gateway = PaymentGatewayConfig::query()->create([

            'uuid' => (string) Str::uuid(),

            'name' => 'Stripe US',

            'slug' => 'stripe-us',

            'provider' => PaymentProvider::Stripe->value,

        ]);



        $url = app(AdminWebhookService::class)->endpointForGateway($gateway);



        $this->assertStringContainsString('/api/v1/webhooks/stripe', (string) $url);

    }



    public function test_overview_aggregates_gateway_and_event_metrics(): void

    {

        PaymentGatewayConfig::query()->create([

            'uuid' => (string) Str::uuid(),

            'name' => 'Verified GW',

            'slug' => 'verified-gw',

            'provider' => PaymentProvider::Stripe->value,

            'webhook_status' => GatewayWebhookStatus::Verified,

            'is_active' => true,

        ]);

        PaymentGatewayConfig::query()->create([

            'uuid' => (string) Str::uuid(),

            'name' => 'Failed GW',

            'slug' => 'failed-gw',

            'provider' => PaymentProvider::Nmi->value,

            'webhook_status' => GatewayWebhookStatus::Failed,

        ]);



        StripeWebhookEvent::query()->create([

            'stripe_event_id' => 'evt_test_1',

            'event_type' => 'payment_intent.succeeded',

            'status' => StripeWebhookProcessingStatus::Processed,

            'payload' => ['id' => 'evt_test_1'],

            'received_at' => now(),

        ]);



        $overview = app(AdminWebhookService::class)->overview();



        $this->assertSame(2, $overview['gateways_total']);

        $this->assertSame(1, $overview['webhook_verified']);

        $this->assertSame(1, $overview['webhook_failed']);

        $this->assertSame(1, $overview['stripe_events_24h']);

        $this->assertSame('payment_intent.succeeded', $overview['last_stripe_event_type']);

    }



    public function test_verify_gateway_writes_webhooks_activity_log(): void

    {

        $gateway = PaymentGatewayConfig::query()->create([

            'uuid' => (string) Str::uuid(),

            'name' => 'Stripe',

            'slug' => 'stripe-verify',

            'provider' => PaymentProvider::Stripe->value,

            'credentials' => ['webhook_secret' => 'whsec_test'],

        ]);



        app(AdminWebhookService::class)->verifyGateway($gateway);



        $this->assertDatabaseHas('admin_activity_logs', [

            'module' => 'webhooks',

            'event' => 'gateway_verified',

        ]);

    }



    public function test_reset_gateway_status_clears_verification_fields(): void

    {

        $gateway = PaymentGatewayConfig::query()->create([

            'uuid' => (string) Str::uuid(),

            'name' => 'Stripe',

            'slug' => 'stripe-reset',

            'provider' => PaymentProvider::Stripe->value,

            'webhook_status' => GatewayWebhookStatus::Verified,

            'webhook_verified_at' => now(),

            'webhook_last_error' => 'old',

        ]);



        $updated = app(AdminWebhookService::class)->resetGatewayStatus($gateway);



        $this->assertSame(GatewayWebhookStatus::Unknown, $updated->webhook_status);

        $this->assertNull($updated->webhook_verified_at);

        $this->assertNull($updated->webhook_last_error);

    }

}

