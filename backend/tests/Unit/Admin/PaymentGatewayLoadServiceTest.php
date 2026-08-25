<?php

namespace Tests\Unit\Admin;

use App\Models\PaymentGatewayConfig;
use App\Models\Transaction;
use App\Modules\Admin\Services\PaymentGatewayLoadService;
use App\Modules\Payment\Enums\PaymentProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PaymentGatewayLoadServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createGateway(array $overrides = []): PaymentGatewayConfig
    {
        return PaymentGatewayConfig::query()->create(array_merge([
            'uuid' => (string) Str::uuid(),
            'name' => 'Test Stripe',
            'slug' => 'test-stripe-'.Str::random(6),
            'provider' => PaymentProvider::Stripe->value,
            'is_active' => true,
            'sort_order' => 1,
            'weight' => 100,
            'daily_limit_amount' => 1000,
            'monthly_limit_amount' => 5000,
        ], $overrides));
    }

    public function test_stats_reflects_volume_and_limits(): void
    {
        $gateway = $this->createGateway();

        Transaction::factory()->create([
            'payment_gateway_id' => $gateway->id,
            'amount' => 250,
            'status' => 'captured',
            'processed_at' => now(),
        ]);

        $service = app(PaymentGatewayLoadService::class);
        $stats = $service->stats($gateway);

        $this->assertSame(250.0, $stats['daily_volume']);
        $this->assertSame(250.0, $stats['monthly_volume']);
        $this->assertTrue($stats['within_limits']);
        $this->assertSame(1000.0, $stats['daily_limit']);
    }

    public function test_is_within_limits_false_when_daily_cap_reached(): void
    {
        $gateway = $this->createGateway(['daily_limit_amount' => 100]);

        Transaction::factory()->create([
            'payment_gateway_id' => $gateway->id,
            'amount' => 100,
            'status' => 'succeeded',
            'processed_at' => now(),
        ]);

        $service = app(PaymentGatewayLoadService::class);

        $this->assertFalse($service->isWithinLimits($gateway));
    }
}
