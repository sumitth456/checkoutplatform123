<?php

namespace App\Filament\Admin\Resources\AchProviders\Pages;

use App\Filament\Admin\Resources\AchProviders\AchProviderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAchProviders extends ListRecords
{
    protected static string $resource = AchProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
