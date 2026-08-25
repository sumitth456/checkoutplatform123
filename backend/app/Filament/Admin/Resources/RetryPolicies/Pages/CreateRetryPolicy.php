<?php

namespace App\Filament\Admin\Resources\RetryPolicies\Pages;

use App\Filament\Admin\Resources\RetryPolicies\RetryPolicyResource;
use App\Filament\Admin\Resources\RetryPolicies\Schemas\RetryPolicyForm;
use Filament\Resources\Pages\CreateRecord;

class CreateRetryPolicy extends CreateRecord
{
    protected static string $resource = RetryPolicyResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return RetryPolicyForm::normalizeForSave($data);
    }
}
