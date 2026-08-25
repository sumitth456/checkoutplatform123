<?php

namespace App\Filament\Admin\Resources\CallCenters\Pages;

use App\Filament\Admin\Resources\CallCenters\CallCenterResource;
use App\Filament\Admin\Resources\CallCenters\Concerns\LogsCallCenterAdminActivity;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCallCenter extends EditRecord
{
    use LogsCallCenterAdminActivity;

    protected static string $resource = CallCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn () => $this->logCallCenterActivity('deleted', "Call center {$this->record->name} soft deleted")),
            ForceDeleteAction::make()
                ->after(fn () => $this->logCallCenterActivity('force_deleted', "Call center {$this->record->name} permanently deleted")),
            RestoreAction::make()
                ->after(fn () => $this->logCallCenterActivity('restored', "Call center {$this->record->name} restored")),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->id();

        return $data;
    }

    protected function afterSave(): void
    {
        $this->logCallCenterActivity('updated', "Call center {$this->record->name} updated");
    }
}
