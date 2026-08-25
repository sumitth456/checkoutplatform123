<?php

namespace App\Filament\Admin\Resources\LandingPages\Pages;

use App\Filament\Admin\Pages\LandingPageManagement\PublishedLandingPages;
use App\Filament\Admin\Resources\LandingPages\LandingPageResource;
use App\Filament\Admin\Support\Actions\SaveLandingPageAsTemplateAction;
use App\Modules\LandingPage\Services\LandingPageService;
use App\Modules\LandingPage\Services\LandingPageVersionService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditLandingPage extends EditRecord
{
    protected static string $resource = LandingPageResource::class;

    protected function getHeaderActions(): array
    {
        $service = app(LandingPageService::class);

        return [
            Action::make('checkout_form')
                ->label('Checkout form')
                ->icon('heroicon-o-clipboard-document-list')
                ->url(fn (): string => LandingPageResource::getUrl('checkout-form', ['record' => $this->record])),
            Action::make('builder')
                ->label('Open builder')
                ->icon('heroicon-o-code-bracket-square')
                ->color('primary')
                ->url(fn (): string => LandingPageResource::getUrl('builder', ['record' => $this->record])),
            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->url(fn (): string => route('landing-pages.preview', $this->record))
                ->openUrlInNewTab(),
            Action::make('publish')
                ->label('Publish')
                ->icon('heroicon-o-arrow-up-tray')
                ->requiresConfirmation()
                ->action(function () use ($service): void {
                    $service->publish($this->record, auth()->id());
                    Notification::make()->title('Published')->success()->send();
                    $this->refreshFormData(['status', 'published_at']);
                }),
            Action::make('unpublish')
                ->label('Unpublish')
                ->color('warning')
                ->action(function () use ($service): void {
                    $service->unpublish($this->record, auth()->id());
                    Notification::make()->title('Unpublished')->success()->send();
                }),
            Action::make('duplicate')
                ->label('Duplicate')
                ->action(function () use ($service): void {
                    $copy = $service->duplicate($this->record, auth()->id());
                    $this->redirect(PublishedLandingPages::getUrl());
                }),
            SaveLandingPageAsTemplateAction::make(),
            Action::make('archive')
                ->label('Archive')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function () use ($service): void {
                    $service->archive($this->record, auth()->id());
                    Notification::make()->title('Archived')->success()->send();
                }),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return PublishedLandingPages::getUrl();
    }

    protected function afterSave(): void
    {
        app(LandingPageVersionService::class)->snapshot($this->record->fresh(), 'Saved from editor', auth()->id());
    }
}
