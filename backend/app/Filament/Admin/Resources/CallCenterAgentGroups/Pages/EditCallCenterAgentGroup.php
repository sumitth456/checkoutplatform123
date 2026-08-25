<?php

namespace App\Filament\Admin\Resources\CallCenterAgentGroups\Pages;

use App\Filament\Admin\Resources\CallCenterAgentGroups\CallCenterAgentGroupResource;
use App\Filament\Admin\Resources\CallCenterAgentGroups\Concerns\LogsCallCenterAgentGroupAdminActivity;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCallCenterAgentGroup extends EditRecord
{
    use LogsCallCenterAgentGroupAdminActivity;

    protected static string $resource = CallCenterAgentGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn () => $this->logCallCenterAgentGroupActivity('deleted', "Agent group {$this->record->name} soft deleted")),
            ForceDeleteAction::make()
                ->after(fn () => $this->logCallCenterAgentGroupActivity('force_deleted', "Agent group {$this->record->name} permanently deleted")),
            RestoreAction::make()
                ->after(fn () => $this->logCallCenterAgentGroupActivity('restored', "Agent group {$this->record->name} restored")),
        ];
    }

    protected function afterSave(): void
    {
        $this->logCallCenterAgentGroupActivity('updated', "Agent group {$this->record->name} updated");
    }
}
