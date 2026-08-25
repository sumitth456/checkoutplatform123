<?php

namespace App\Filament\Admin\Resources\EmailAutomations\Pages;

use App\Filament\Admin\Resources\EmailAutomations\EmailAutomationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmailAutomations extends ListRecords
{
    protected static string $resource = EmailAutomationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
