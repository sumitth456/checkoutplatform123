<?php

namespace App\Models;

use App\Policies\PlatformSettingPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

#[UsePolicy(PlatformSettingPolicy::class)]
class PlatformSetting extends Model
{
    protected $fillable = [
        'store_name',
        'support_email',
        'default_currency',
        'timezone',
        'payment_default_provider',
        'payment_idempotency_ttl_minutes',
        'payment_retry_attempts',
        'payment_retry_delay_ms',
        'payment_timeout_seconds',
        'payment_circuit_failure_threshold',
        'payment_circuit_recovery_seconds',
        'fulfillment_default_delay_days',
        'subscription_charge_lock_seconds',
        'checkout_queue',
        'checkout_fulfillment_queue',
        'fulfillment_queue',
        'subscription_billing_queue',
        'subscription_dunning_queue',
        'subscription_webhook_queue',
        'checkout_require_matching_billing',
        'maintenance_mode_checkout',
    ];

    protected function casts(): array
    {
        return [
            'checkout_require_matching_billing' => 'boolean',
            'maintenance_mode_checkout' => 'boolean',
        ];
    }

    public function adminActivityLogs(): MorphMany
    {
        return $this->morphMany(AdminActivityLog::class, 'subject')->latest();
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(
            ['id' => 1],
            static::defaultsFromConfig(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultsFromConfig(): array
    {
        return [
            'store_name' => config('app.name', 'Checkout Platform'),
            'support_email' => config('mail.from.address'),
            'default_currency' => 'USD',
            'timezone' => config('app.timezone'),
            'payment_default_provider' => (string) config('payment.default_provider', 'stripe'),
            'payment_idempotency_ttl_minutes' => (int) config('payment.idempotency_ttl_minutes', 1440),
            'payment_retry_attempts' => (int) config('payment.resilience.max_attempts', 3),
            'payment_retry_delay_ms' => (int) config('payment.resilience.retry_delay_ms', 250),
            'payment_timeout_seconds' => (int) config('payment.resilience.timeout_seconds', 30),
            'payment_circuit_failure_threshold' => (int) config('payment.resilience.circuit_failure_threshold', 5),
            'payment_circuit_recovery_seconds' => (int) config('payment.resilience.circuit_recovery_seconds', 60),
            'fulfillment_default_delay_days' => (int) config('fulfillment.default_delay_days', 10),
            'subscription_charge_lock_seconds' => (int) config('subscription.charge_lock_seconds', 300),
            'checkout_queue' => (string) config('checkout.queue', 'default'),
            'checkout_fulfillment_queue' => (string) config('checkout.fulfillment_queue', 'default'),
            'fulfillment_queue' => (string) config('fulfillment.queue', 'default'),
            'subscription_billing_queue' => (string) config('subscription.queues.billing', 'default'),
            'subscription_dunning_queue' => (string) config('subscription.queues.dunning', 'default'),
            'subscription_webhook_queue' => (string) config('subscription.queues.webhooks', 'default'),
        ];
    }
}
