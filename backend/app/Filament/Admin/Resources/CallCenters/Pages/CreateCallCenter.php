<?php

namespace App\Filament\Admin\Resources\CallCenters\Pages;

use App\Filament\Admin\Resources\CallCenters\CallCenterResource;
use App\Filament\Admin\Resources\CallCenters\Concerns\LogsCallCenterAdminActivity;
use Filament\Resources\Pages\CreateRecord;

class CreateCallCenter extends CreateRecord
{
    use LogsCallCenterAdminActivity;

    protected static string $resource = CallCenterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['updated_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->logCallCenterActivity('created', "Call center {$this->record->name} created");
    }
}
