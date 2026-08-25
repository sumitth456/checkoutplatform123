<?php

namespace App\Filament\Admin\Resources\EmailQueueItems\Pages;

use App\Filament\Admin\Resources\EmailQueueItems\EmailQueueItemResource;
use Filament\Resources\Pages\ListRecords;

class ListEmailQueueItems extends ListRecords
{
    protected static string $resource = EmailQueueItemResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
