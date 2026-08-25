<?php

namespace App\Filament\Admin\Resources\FulfillmentBatches\Pages;

use App\Filament\Admin\Resources\FulfillmentBatches\FulfillmentBatchResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFulfillmentBatch extends EditRecord
{
    protected static string $resource = FulfillmentBatchResource::class;

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