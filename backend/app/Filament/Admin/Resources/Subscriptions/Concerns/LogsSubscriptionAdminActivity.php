<?php

namespace App\Filament\Admin\Resources\Subscriptions\Concerns;

use App\Models\Subscription;
use App\Support\Admin\AdminActivityLogger;

trait LogsSubscriptionAdminActivity
{
    protected function logSubscriptionActivity(string $event, ?string $description = null, array $properties = []): void
    {
        if (! $this->record instanceof Subscription) {
            return;
        }

        AdminActivityLogger::log(
            module: 'subscriptions',
            event: $event,
            description: $description,
            subject: $this->record,
            properties: $properties,
        );
    }
}