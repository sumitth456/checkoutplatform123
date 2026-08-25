<?php

namespace App\Filament\Admin\Resources\CallDispositions\Pages;

use App\Filament\Admin\Resources\CallDispositions\CallDispositionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCallDisposition extends ViewRecord
{
    protected static string $resource = CallDispositionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
