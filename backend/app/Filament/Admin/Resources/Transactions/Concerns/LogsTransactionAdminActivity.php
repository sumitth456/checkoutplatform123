<?php

namespace App\Filament\Admin\Resources\Transactions\Concerns;

use App\Models\Transaction;
use App\Support\Admin\AdminActivityLogger;

trait LogsTransactionAdminActivity
{
    protected function logTransactionActivity(string $event, ?string $description = null, array $properties = []): void
    {
        if (! $this->record instanceof Transaction) {
            return;
        }

        AdminActivityLogger::log(
            module: 'transactions',
            event: $event,
            description: $description,
            subject: $this->record,
            properties: $properties,
        );
    }
}
