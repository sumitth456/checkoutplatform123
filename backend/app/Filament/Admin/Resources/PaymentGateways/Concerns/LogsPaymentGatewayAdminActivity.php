<?php

namespace App\Filament\Admin\Resources\PaymentGateways\Concerns;

use App\Models\PaymentGatewayConfig;
use App\Support\Admin\AdminActivityLogger;

trait LogsPaymentGatewayAdminActivity
{
    protected function logGatewayActivity(string $event, ?string $description = null, array $properties = []): void
    {
        if (! $this->record instanceof PaymentGatewayConfig) {
            return;
        }

        AdminActivityLogger::log(
            module: 'payment_gateways',
            event: $event,
            description: $description,
            subject: $this->record,
            properties: $properties,
        );
    }
}
