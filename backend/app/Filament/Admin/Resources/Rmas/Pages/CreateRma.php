<?php

namespace App\Filament\Admin\Resources\Rmas\Pages;

use App\Filament\Admin\Resources\Rmas\RmaResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRma extends CreateRecord
{
    protected static string $resource = RmaResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('created_by', $this->getRecord()->getFillable())) {
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();
        }

        return $data;
    }
}