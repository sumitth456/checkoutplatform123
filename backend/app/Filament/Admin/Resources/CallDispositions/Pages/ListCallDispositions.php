<?php

namespace App\Filament\Admin\Resources\CallDispositions\Pages;

use App\Filament\Admin\Resources\CallDispositions\CallDispositionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCallDispositions extends ListRecords
{
    protected static string $resource = CallDispositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
