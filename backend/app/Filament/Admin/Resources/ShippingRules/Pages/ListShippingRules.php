<?php

namespace App\Filament\Admin\Resources\ShippingRules\Pages;

use App\Filament\Admin\Resources\ShippingRules\ShippingRuleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShippingRules extends ListRecords
{
    protected static string $resource = ShippingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}