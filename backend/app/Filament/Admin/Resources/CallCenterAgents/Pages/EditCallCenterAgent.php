<?php

namespace App\Filament\Admin\Resources\CallCenterAgents\Pages;

use App\Filament\Admin\Resources\CallCenterAgents\CallCenterAgentResource;
use App\Filament\Admin\Resources\CallCenterAgents\Concerns\LogsCallCenterAgentAdminActivity;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCallCenterAgent extends EditRecord
{
    use LogsCallCenterAgentAdminActivity;

    protected static string $resource = CallCenterAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn () => $this->logCallCenterAgentActivity('deleted', "Agent {$this->record->name} soft deleted")),
            ForceDeleteAction::make()
                ->after(fn () => $this->logCallCenterAgentActivity('force_deleted', "Agent {$this->record->name} permanently deleted")),
            RestoreAction::make()
                ->after(fn () => $this->logCallCenterAgentActivity('restored', "Agent {$this->record->name} restored")),
        ];
    }

    protected function afterSave(): void
    {
        $this->logCallCenterAgentActivity('updated', "Agent {$this->record->name} updated");
    }
}
