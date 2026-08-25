<?php

namespace App\Filament\Admin\Resources\Customers\Concerns;

use App\Models\Customer;
use App\Support\Admin\AdminActivityLogger;

trait LogsCustomerAdminActivity
{
    protected function logCustomerActivity(string $event, ?string $description = null, array $properties = []): void
    {
        if (! $this->record instanceof Customer) {
            return;
        }

        AdminActivityLogger::log(
            module: 'customers',
            event: $event,
            description: $description,
            subject: $this->record,
            properties: $properties,
        );
    }
}
