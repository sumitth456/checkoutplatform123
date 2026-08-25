<?php

namespace App\Filament\Admin\Resources\CallDispositions\Pages;

use App\Filament\Admin\Resources\CallDispositions\CallDispositionResource;
use App\Filament\Admin\Resources\CallDispositions\Concerns\LogsCallDispositionAdminActivity;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCallDisposition extends EditRecord
{
    use LogsCallDispositionAdminActivity;

    protected static string $resource = CallDispositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(fn () => $this->logCallDispositionActivity('deleted', "Disposition {$this->record->name} soft deleted")),
            ForceDeleteAction::make()
                ->after(fn () => $this->logCallDispositionActivity('force_deleted', "Disposition {$this->record->name} permanently deleted")),
            RestoreAction::make()
                ->after(fn () => $this->logCallDispositionActivity('restored', "Disposition {$this->record->name} restored")),
        ];
    }

    protected function afterSave(): void
    {
        $this->logCallDispositionActivity('updated', "Disposition {$this->record->name} updated");
    }
}
