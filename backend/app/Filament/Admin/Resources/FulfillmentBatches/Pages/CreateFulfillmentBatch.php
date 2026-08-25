<?php

namespace App\Filament\Admin\Resources\FulfillmentBatches\Pages;

use App\Filament\Admin\Resources\FulfillmentBatches\FulfillmentBatchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateFulfillmentBatch extends CreateRecord
{
    protected static string $resource = FulfillmentBatchResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (array_key_exists('created_by', $this->getRecord()->getFillable())) {
            $data['created_by'] = auth()->id();
            $data['updated_by'] = auth()->id();
        }

        return $data;
    }
}