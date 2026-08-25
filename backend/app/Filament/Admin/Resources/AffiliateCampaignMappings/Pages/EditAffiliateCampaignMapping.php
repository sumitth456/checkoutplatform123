<?php

namespace App\Filament\Admin\Resources\AffiliateCampaignMappings\Pages;

use App\Filament\Admin\Resources\AffiliateCampaignMappings\AffiliateCampaignMappingResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAffiliateCampaignMapping extends EditRecord
{
    protected static string $resource = AffiliateCampaignMappingResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
