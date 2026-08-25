<?php

namespace Tests\Unit\Admin;

use App\Models\PaymentGatewayConfig;
use App\Modules\Admin\Services\GatewayRoutingAdminService;
use App\Modules\Payment\Enums\PaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GatewayRoutingAdminServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_routing_plan_selects_first_active_gateway_within_limits(): void
    {
        PaymentGatewayConfig::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Primary',
            'slug' => 'primary-'.Str::random(4),
            'provider' => PaymentProvider::Stripe->value,
            'is_active' => true,
            'sort_order' => 1,
        ]);

        PaymentGatewayConfig::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Secondary',
            'slug' => 'secondary-'.Str::random(4),
            'provider' => PaymentProvider::PayPal->value,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $plan = app(GatewayRoutingAdminService::class)->routingPlan();

        $this->assertSame('Primary', $plan['selected']?->name);
        $this->assertSame('selected', $plan['hops'][0]['status']);
        $this->assertSame('standby', $plan['hops'][1]['status']);
    }

    public function test_routing_plan_skips_capped_gateway_and_uses_next_in_queue(): void
    {
        PaymentGatewayConfig::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Capped',
            'slug' => 'capped-'.Str::random(4),
            'provider' => PaymentProvider::Stripe->value,
            'is_active' => true,
            'sort_order' => 1,
            'daily_limit_amount' => 0,
        ]);

        PaymentGatewayConfig::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Fallback',
            'slug' => 'fallback-'.Str::random(4),
            'provider' => PaymentProvider::Nmi->value,
            'is_active' => true,
            'sort_order' => 2,
        ]);

        $plan = app(GatewayRoutingAdminService::class)->routingPlan();

        $this->assertSame('Fallback', $plan['selected']?->name);
        $this->assertSame('skipped', $plan['hops'][0]['status']);
        $this->assertSame('selected', $plan['hops'][1]['status']);
    }

    public function test_routing_plan_uses_default_gateway_when_queue_has_no_eligible_gateway(): void
    {
        PaymentGatewayConfig::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Capped',
            'slug' => 'capped-'.Str::random(4),
            'provider' => PaymentProvider::Stripe->value,
            'is_active' => true,
            'sort_order' => 1,
            'daily_limit_amount' => 0,
        ]);

        PaymentGatewayConfig::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Default',
            'slug' => 'default-'.Str::random(4),
            'provider' => PaymentProvider::Nmi->value,
            'is_active' => true,
            'is_default' => true,
            'sort_order' => 99,
            'daily_limit_amount' => 0,
        ]);

        $plan = app(GatewayRoutingAdminService::class)->routingPlan();

        $this->assertSame('Default', $plan['selected']?->name);
        $this->assertNotEmpty($plan['failover_steps']);
    }

    public function test_role_for_inactive_gateway(): void
    {
        $gateway = PaymentGatewayConfig::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Off',
            'slug' => 'off-'.Str::random(4),
            'provider' => PaymentProvider::Stripe->value,
            'is_active' => false,
            'sort_order' => 1,
        ]);

        $role = app(GatewayRoutingAdminService::class)->roleForGateway($gateway);

        $this->assertSame('Inactive', $role['label']);
    }
}
