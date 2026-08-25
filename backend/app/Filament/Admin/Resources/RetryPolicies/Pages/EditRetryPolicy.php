<?php

namespace App\Filament\Admin\Resources\RetryPolicies\Pages;

use App\Filament\Admin\Resources\RetryPolicies\RetryPolicyResource;
use App\Filament\Admin\Resources\RetryPolicies\Schemas\RetryPolicyForm;
use Filament\Resources\Pages\EditRecord;

class EditRetryPolicy extends EditRecord
{
    protected static string $resource = RetryPolicyResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return RetryPolicyForm::normalizeForForm($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return RetryPolicyForm::normalizeForSave($data);
    }
}
