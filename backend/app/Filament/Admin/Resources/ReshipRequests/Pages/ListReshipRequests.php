<?php

namespace App\Filament\Admin\Resources\ReshipRequests\Pages;

use App\Filament\Admin\Resources\ReshipRequests\ReshipRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListReshipRequests extends ListRecords
{
    protected static string $resource = ReshipRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}