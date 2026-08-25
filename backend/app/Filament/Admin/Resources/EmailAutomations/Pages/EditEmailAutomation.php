<?php

namespace App\Filament\Admin\Resources\EmailAutomations\Pages;

use App\Filament\Admin\Resources\EmailAutomations\EmailAutomationResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmailAutomation extends EditRecord
{
    protected static string $resource = EmailAutomationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
