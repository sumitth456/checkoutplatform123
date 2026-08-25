<?php

namespace App\Filament\Admin\Resources\LandingPageTemplates\Pages;

use App\Filament\Admin\Resources\LandingPageTemplates\LandingPageTemplateResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;

class EditLandingPageTemplate extends EditRecord
{
    protected static string $resource = LandingPageTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('builder')
                ->label('Open builder')
                ->icon('heroicon-o-paint-brush')
                ->url(fn (): string => LandingPageTemplateResource::getUrl('builder', ['record' => $this->record])),
            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->url(fn (): string => route('landing-page-templates.preview', $this->record))
                ->openUrlInNewTab(),
            ...parent::getHeaderActions(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return LandingPageTemplateResource::getUrl('index');
    }
}

