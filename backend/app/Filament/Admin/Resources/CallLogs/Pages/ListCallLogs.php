<?php

namespace App\Filament\Admin\Resources\CallLogs\Pages;

use App\Filament\Admin\Resources\CallLogs\CallLogResource;
use Filament\Resources\Pages\ListRecords;

class ListCallLogs extends ListRecords
{
    protected static string $resource = CallLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
