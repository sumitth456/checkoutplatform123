<?php

namespace App\Filament\Admin\Resources\Subscriptions\Concerns;

use Filament\Actions\Action;

trait InteractsWithSubscriptionBillingActions
{
    /**
     * @return array<int, Action>
     */
    protected function getSubscriptionBillingActions(): array
    {
        return [];
    }
}