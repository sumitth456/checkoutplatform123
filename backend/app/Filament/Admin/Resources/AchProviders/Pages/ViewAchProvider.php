<?php

namespace App\Filament\Admin\Resources\AchProviders\Pages;

use App\Filament\Admin\Resources\AchProviders\AchProviderResource;
use App\Modules\Ach\Services\AchTokenizationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewAchProvider extends ViewRecord
{
    protected static string $resource = AchProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('testConnection')
                ->label('Test connection')
                ->icon('heroicon-o-signal')
                ->action(function (): void {
                    $service = app(AchTokenizationService::class);

                    try {
                        $passed = $service->testConnection($this->record);

                        Notification::make()
                            ->title($passed ? 'Connection successful' : 'Connection failed')
                            ->success($passed)
                            ->danger(! $passed)
                            ->send();
                    } catch (\Throwable $exception) {
                        Notification::make()
                            ->title('Connection failed')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
