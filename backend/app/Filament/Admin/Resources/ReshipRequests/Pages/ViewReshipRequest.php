<?php

namespace App\Filament\Admin\Resources\ReshipRequests\Pages;

use App\Filament\Admin\Resources\ReshipRequests\ReshipRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewReshipRequest extends ViewRecord
{
    protected static string $resource = ReshipRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}