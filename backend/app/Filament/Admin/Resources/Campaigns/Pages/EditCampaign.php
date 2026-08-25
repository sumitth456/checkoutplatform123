<?php

namespace App\Filament\Admin\Resources\Campaigns\Pages;

use App\Filament\Admin\Resources\Campaigns\CampaignResource;
use App\Filament\Admin\Resources\Campaigns\Concerns\LogsCampaignAdminActivity;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCampaign extends EditRecord
{
    use LogsCampaignAdminActivity;

    protected static string $resource = CampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn () => $this->logCampaignActivity('deleted', "Campaign {$this->record->name} soft deleted")),
            ForceDeleteAction::make()
                ->after(fn () => $this->logCampaignActivity('force_deleted', "Campaign {$this->record->name} permanently deleted")),
            RestoreAction::make()
                ->after(fn () => $this->logCampaignActivity('restored', "Campaign {$this->record->name} restored")),
        ];
    }

    protected function afterSave(): void
    {
        $this->logCampaignActivity('updated', "Campaign {$this->record->name} updated");
    }
}
