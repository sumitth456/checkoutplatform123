<?php

namespace App\Filament\Admin\Resources\DelayFulfillment\Concerns;

use App\Models\Fulfillment;
use App\Support\Admin\AdminActivityLogger;

trait LogsDelayFulfillmentAdminActivity
{
    protected function logDelayFulfillmentActivity(string $event, ?string $description = null, array $properties = []): void
    {
        if (! $this->record instanceof Fulfillment) {
            return;
        }

        AdminActivityLogger::log(
            module: 'delay_fulfillment',
            event: $event,
            description: $description,
            subject: $this->record,
            properties: $properties,
        );
    }
}
