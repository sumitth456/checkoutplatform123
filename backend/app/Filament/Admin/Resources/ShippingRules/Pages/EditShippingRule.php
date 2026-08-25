<?php

namespace App\Filament\Admin\Resources\ShippingRules\Pages;

use App\Filament\Admin\Resources\ShippingRules\ShippingRuleResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditShippingRule extends EditRecord
{
    protected static string $resource = ShippingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('updated_by', $this->getRecord()->getFillable())) {
            $data['updated_by'] = auth()->id();
        }

        return $data;
    }
}