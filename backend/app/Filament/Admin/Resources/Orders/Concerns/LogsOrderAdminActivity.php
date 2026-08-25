<?php

namespace App\Filament\Admin\Resources\Orders\Concerns;

use App\Models\Order;
use App\Support\Admin\AdminActivityLogger;

trait LogsOrderAdminActivity
{
    protected function logOrderActivity(string $event, ?string $description = null, array $properties = []): void
    {
        if (! $this->record instanceof Order) {
            return;
        }

        AdminActivityLogger::log(
            module: 'orders',
            event: $event,
            description: $description,
            subject: $this->record,
            properties: $properties,
        );
    }
}
