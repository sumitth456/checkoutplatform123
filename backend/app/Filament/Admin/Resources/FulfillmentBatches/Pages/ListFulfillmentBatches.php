<?php

namespace App\Filament\Admin\Resources\FulfillmentBatches\Pages;

use App\Filament\Admin\Resources\FulfillmentBatches\FulfillmentBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFulfillmentBatches extends ListRecords
{
    protected static string $resource = FulfillmentBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}