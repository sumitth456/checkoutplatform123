<?php

namespace Tests\Feature\Ach;

use App\Models\AchProvider;
use App\Models\Customer;
use App\Models\EmailProvider;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Modules\Ach\Enums\AchProviderStatus;
use App\Modules\Ach\Enums\AchProviderType;
use Database\Seeders\AchModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AchCheckoutFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AchModuleSeeder::class);
        config(['payment.sandbox_mode' => true, 'ach.sandbox_mode' => true]);
    }

    #[Test]
    public function it_tokenizes_ach_bank_details_without_storing_sensitive_data(): void
    {
        $response = $this->postJson('/api/v1/ach/tokenize', [
            'account_holder_name' => 'Sumit Thakur',
            'routing_number' => '110000000',
            'account_number' => '1234567890',
            'account_number_confirmation' => '1234567890',
            'account_type' => 'checking',
            'account_holder_type' => 'personal',
            'customer_email' => 'sumitthakur8313@gmail.com',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.payment_method', 'ach')
            ->assertJsonPath('data.last4', '7890');

        $this->assertDatabaseMissing('customer_payment_methods', [
            'last4' => '7890',
            'payment_token' => '1234567890',
        ]);

        $this->assertDatabaseHas('customer_payment_methods', [
            'last4' => '7890',
            'payment_method' => 'ach',
        ]);

        $log = \App\Models\AchPaymentLog::query()->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('1234567890', json_encode($log->request_payload));
    }

    #[Test]
    public function it_rejects_invalid_routing_number(): void
    {
        $response = $this->postJson('/api/v1/ach/tokenize', [
            'account_holder_name' => 'Test User',
            'routing_number' => '12345',
            'account_number' => '1234567890',
            'account_number_confirmation' => '1234567890',
            'account_type' => 'checking',
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function it_rejects_mismatched_account_numbers(): void
    {
        $response = $this->postJson('/api/v1/ach/tokenize', [
            'account_holder_name' => 'Test User',
            'routing_number' => '110000000',
            'account_number' => '1234567890',
            'account_number_confirmation' => '9999999999',
            'account_type' => 'checking',
        ]);

        $response->assertUnprocessable();
    }

    #[Test]
    public function failed_test_routing_returns_error(): void
    {
        config(['ach.fail_test_routing' => '000000000']);

        $response = $this->postJson('/api/v1/ach/tokenize', [
            'account_holder_name' => 'Test User',
            'routing_number' => '000000000',
            'account_number' => '1234567890',
            'account_number_confirmation' => '1234567890',
            'account_type' => 'checking',
        ]);

        $response->assertUnprocessable();
    }
}
