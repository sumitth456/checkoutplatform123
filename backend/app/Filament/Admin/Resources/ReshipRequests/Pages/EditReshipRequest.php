<?php

namespace App\Filament\Admin\Resources\ReshipRequests\Pages;

use App\Filament\Admin\Resources\ReshipRequests\ReshipRequestResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditReshipRequest extends EditRecord
{
    protected static string $resource = ReshipRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [ViewAction::make()];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (array_key_exists('updated_by', $this->getRecord()->getFillable())) {
            $data['updated_by'] = auth()->id();
        }

        return $data;
    }
}