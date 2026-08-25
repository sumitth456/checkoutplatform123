<?php

namespace App\Filament\Admin\Resources\Products\Concerns;

use App\Models\Product;
use App\Support\Admin\AdminActivityLogger;

trait LogsProductAdminActivity
{
    protected function logProductActivity(string $event, ?string $description = null, array $properties = []): void
    {
        if (! $this->record instanceof Product) {
            return;
        }

        AdminActivityLogger::log(
            module: 'products',
            event: $event,
            description: $description,
            subject: $this->record,
            properties: $properties,
        );
    }
}
