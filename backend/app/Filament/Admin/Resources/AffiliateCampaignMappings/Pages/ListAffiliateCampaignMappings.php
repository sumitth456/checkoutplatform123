<?php

namespace App\Filament\Admin\Resources\AffiliateCampaignMappings\Pages;

use App\Filament\Admin\Resources\AffiliateCampaignMappings\AffiliateCampaignMappingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliateCampaignMappings extends ListRecords
{
    protected static string $resource = AffiliateCampaignMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
