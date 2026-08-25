<?php

namespace App\Filament\Admin\Resources\ReshipRequests\Pages;

use App\Filament\Admin\Resources\ReshipRequests\ReshipRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateReshipRequest extends CreateRecord
{
    protected static string $resource = ReshipRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requested_by'] = auth()->id();

        return $data;
    }
}