<?php

namespace App\Filament\Admin\Resources\CallLogs\Pages;

use App\Filament\Admin\Resources\CallLogs\CallLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewCallLog extends ViewRecord
{
    protected static string $resource = CallLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
