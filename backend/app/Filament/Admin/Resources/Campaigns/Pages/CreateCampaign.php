<?php

namespace App\Filament\Admin\Resources\Campaigns\Pages;

use App\Filament\Admin\Resources\Campaigns\CampaignResource;
use App\Filament\Admin\Resources\Campaigns\Concerns\LogsCampaignAdminActivity;
use Filament\Resources\Pages\CreateRecord;

class CreateCampaign extends CreateRecord
{
    use LogsCampaignAdminActivity;

    protected static string $resource = CampaignResource::class;

    protected function afterCreate(): void
    {
        $this->logCampaignActivity('created', "Campaign {$this->record->name} created");
    }
}
