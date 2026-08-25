<?php



namespace Tests\Unit\Admin;



use App\Models\PlatformSetting;

use App\Modules\Admin\Services\PlatformConfigResolver;

use App\Modules\Payment\Enums\PaymentProvider;

use Illuminate\Foundation\Testing\RefreshDatabase;

use Tests\TestCase;



class PlatformConfigResolverTest extends TestCase

{

    use RefreshDatabase;



    public function test_reads_platform_settings_with_config_fallback_on_create(): void

    {

        $settings = PlatformSetting::current();



        $this->assertSame(config('app.name', 'Checkout Platform'), $settings->store_name);

        $this->assertSame('stripe', $settings->payment_default_provider);



        $resolver = app(PlatformConfigResolver::class);



        $this->assertSame(PaymentProvider::Stripe, $resolver->defaultPaymentProvider());

        $this->assertSame(10, $resolver->fulfillmentDefaultDelayDays());

    }



    public function test_updated_settings_drive_resolver_values(): void

    {

        $settings = PlatformSetting::current();

        $settings->update([

            'payment_default_provider' => PaymentProvider::PayPal->value,

            'fulfillment_default_delay_days' => 21,

            'subscription_charge_lock_seconds' => 600,

            'maintenance_mode_checkout' => true,

        ]);



        $resolver = app(PlatformConfigResolver::class);



        $this->assertSame(PaymentProvider::PayPal, $resolver->defaultPaymentProvider());

        $this->assertSame(21, $resolver->fulfillmentDefaultDelayDays());

        $this->assertSame(600, $resolver->subscriptionChargeLockSeconds());

        $this->assertTrue($resolver->checkoutMaintenanceMode());

    }



    public function test_payment_resilience_array_matches_settings(): void

    {

        PlatformSetting::current()->update([

            'payment_retry_attempts' => 5,

            'payment_circuit_failure_threshold' => 7,

        ]);



        $resilience = app(PlatformConfigResolver::class)->paymentResilience();



        $this->assertSame(5, $resilience['max_attempts']);

        $this->assertSame(7, $resilience['circuit_failure_threshold']);

    }

}

