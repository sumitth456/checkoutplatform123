<?php

namespace App\Modules\Admin\Services;

use App\Models\PlatformSetting;
use App\Modules\Payment\Enums\PaymentProvider;
use Illuminate\Support\Facades\Schema;

class PlatformConfigResolver
{
    public function settings(): PlatformSetting
    {
        if (! Schema::hasTable('platform_settings')) {
            return (new PlatformSetting(PlatformSetting::defaultsFromConfig()))->forceFill(['id' => 1]);
        }

        return PlatformSetting::current();
    }

    public function storeName(): string
    {
        return (string) $this->settings()->store_name;
    }

    public function supportEmail(): ?string
    {
        return $this->settings()->support_email;
    }

    public function defaultCurrency(): string
    {
        return strtoupper((string) $this->settings()->default_currency);
    }

    public function timezone(): string
    {
        return (string) ($this->settings()->timezone ?: config('app.timezone', 'UTC'));
    }

    public function defaultPaymentProvider(): PaymentProvider
    {
        $value = (string) $this->settings()->payment_default_provider;

        return PaymentProvider::tryFrom($value)
            ?? PaymentProvider::from(config('payment.default_provider', PaymentProvider::Stripe->value));
    }

    public function paymentIdempotencyTtlMinutes(): int
    {
        return (int) $this->settings()->payment_idempotency_ttl_minutes;
    }

    /**
     * @return array{max_attempts: int, retry_delay_ms: int, timeout_seconds: int, circuit_failure_threshold: int, circuit_recovery_seconds: int}
     */
    public function paymentResilience(): array
    {
        $settings = $this->settings();

        return [
            'max_attempts' => (int) $settings->payment_retry_attempts,
            'retry_delay_ms' => (int) $settings->payment_retry_delay_ms,
            'timeout_seconds' => (int) $settings->payment_timeout_seconds,
            'circuit_failure_threshold' => (int) $settings->payment_circuit_failure_threshold,
            'circuit_recovery_seconds' => (int) $settings->payment_circuit_recovery_seconds,
        ];
    }

    public function fulfillmentDefaultDelayDays(): int
    {
        return max(0, (int) $this->settings()->fulfillment_default_delay_days);
    }

    public function subscriptionChargeLockSeconds(): int
    {
        return max(1, (int) $this->settings()->subscription_charge_lock_seconds);
    }

    public function checkoutQueue(): string
    {
        return (string) $this->settings()->checkout_queue;
    }

    public function checkoutFulfillmentQueue(): string
    {
        return (string) $this->settings()->checkout_fulfillment_queue;
    }

    public function fulfillmentQueue(): string
    {
        return (string) $this->settings()->fulfillment_queue;
    }

    public function subscriptionBillingQueue(): string
    {
        return (string) $this->settings()->subscription_billing_queue;
    }

    public function subscriptionDunningQueue(): string
    {
        return (string) $this->settings()->subscription_dunning_queue;
    }

    public function subscriptionWebhookQueue(): string
    {
        return (string) $this->settings()->subscription_webhook_queue;
    }

    public function checkoutMaintenanceMode(): bool
    {
        return (bool) $this->settings()->maintenance_mode_checkout;
    }

    public function checkoutRequireMatchingBilling(): bool
    {
        return (bool) $this->settings()->checkout_require_matching_billing;
    }
}
