<?php

namespace App\Filament\Admin\Resources\RetryPolicies\Pages;

use App\Filament\Admin\Resources\RetryPolicies\RetryPolicyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRetryPolicies extends ListRecords
{
    protected static string $resource = RetryPolicyResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
