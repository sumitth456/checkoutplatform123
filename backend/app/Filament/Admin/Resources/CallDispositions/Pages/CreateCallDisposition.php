<?php

namespace App\Filament\Admin\Resources\CallDispositions\Pages;

use App\Filament\Admin\Resources\CallDispositions\CallDispositionResource;
use App\Filament\Admin\Resources\CallDispositions\Concerns\LogsCallDispositionAdminActivity;
use Filament\Resources\Pages\CreateRecord;

class CreateCallDisposition extends CreateRecord
{
    use LogsCallDispositionAdminActivity;

    protected static string $resource = CallDispositionResource::class;

    protected function afterCreate(): void
    {
        $this->logCallDispositionActivity('created', "Disposition {$this->record->name} created");
    }
}
