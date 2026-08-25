<?php

namespace App\Modules\Admin\Services;

use App\Models\PaymentGatewayConfig;
use App\Modules\Payment\Enums\GatewayWebhookStatus;
use App\Support\Admin\AdminActivityLogger;

class PaymentGatewayAdminService
{
    public function __construct(
        private readonly PaymentGatewayLoadService $load,
    ) {}

    public function verifyWebhook(PaymentGatewayConfig $gateway): PaymentGatewayConfig
    {
        $credentials = $gateway->credentials ?? [];
        $hasWebhookConfig = collect($credentials)->keys()->contains(fn ($key) => str_contains((string) $key, 'webhook'));

        if (! $hasWebhookConfig) {
            $gateway->webhook_status = GatewayWebhookStatus::Failed;
            $gateway->webhook_last_error = 'No webhook secret or ID configured in credentials.';
            $gateway->save();

            return $gateway->fresh();
        }

        $gateway->webhook_status = GatewayWebhookStatus::Verified;
        $gateway->webhook_verified_at = now();
        $gateway->webhook_last_error = null;
        $gateway->save();

        AdminActivityLogger::log(
            module: 'payment_gateways',
            event: 'webhook_verified',
            description: "Webhook marked verified for {$gateway->name}",
            subject: $gateway,
        );

        return $gateway->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    public function loadSnapshot(PaymentGatewayConfig $gateway): array
    {
        return $this->load->stats($gateway);
    }
}
