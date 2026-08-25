<?php

namespace App\Filament\Admin\Resources\AffiliateCampaignMappings\Pages;

use App\Filament\Admin\Resources\AffiliateCampaignMappings\AffiliateCampaignMappingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliateCampaignMapping extends ViewRecord
{
    protected static string $resource = AffiliateCampaignMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
