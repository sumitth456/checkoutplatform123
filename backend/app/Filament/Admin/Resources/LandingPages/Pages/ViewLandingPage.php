<?php

namespace App\Filament\Admin\Resources\LandingPages\Pages;

use App\Filament\Admin\Resources\LandingPages\LandingPageResource;
use App\Filament\Admin\Support\Actions\SaveLandingPageAsTemplateAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLandingPage extends ViewRecord
{
    protected static string $resource = LandingPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SaveLandingPageAsTemplateAction::make(),
        ];
    }
}
