<?php

namespace App\Filament\Admin\Resources\LandingPageTemplates\Pages;

use App\Filament\Admin\Resources\LandingPageTemplates\LandingPageTemplateResource;
use App\Modules\LandingPage\Services\LandingPageTemplateService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLandingPageTemplate extends CreateRecord
{
    protected static string $resource = LandingPageTemplateResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(LandingPageTemplateService::class)->createBlank($data, auth()->id());
    }

    protected function getRedirectUrl(): string
    {
        return LandingPageTemplateResource::getUrl('builder', ['record' => $this->record]);
    }
}
