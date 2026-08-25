<?php

namespace App\Filament\Admin\Resources\ShippingRules\Pages;

use App\Filament\Admin\Resources\ShippingRules\ShippingRuleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewShippingRule extends ViewRecord
{
    protected static string $resource = ShippingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}