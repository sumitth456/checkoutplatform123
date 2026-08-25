<?php

namespace Tests\Unit\Admin;

use App\Models\PaymentGatewayConfig;
use App\Modules\Admin\Services\PaymentGatewayAdminService;
use App\Modules\Payment\Enums\GatewayWebhookStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PaymentGatewayAdminServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_marks_webhook_verified_when_credentials_include_webhook_config(): void
    {
        $gateway = PaymentGatewayConfig::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Stripe Test',
            'slug' => 'stripe-test-'.Str::random(4),
            'provider' => 'stripe',
            'credentials' => ['webhook_secret' => 'whsec_x'],
            'is_active' => true,
        ]);

        $updated = app(PaymentGatewayAdminService::class)->verifyWebhook($gateway);

        $this->assertSame(GatewayWebhookStatus::Verified, $updated->webhook_status);
        $this->assertNotNull($updated->webhook_verified_at);
    }
}
