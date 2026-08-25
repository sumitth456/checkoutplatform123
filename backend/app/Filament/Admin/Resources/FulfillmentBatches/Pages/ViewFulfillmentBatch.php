<?php

namespace App\Filament\Admin\Resources\FulfillmentBatches\Pages;

use App\Filament\Admin\Resources\FulfillmentBatches\FulfillmentBatchResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFulfillmentBatch extends ViewRecord
{
    protected static string $resource = FulfillmentBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}