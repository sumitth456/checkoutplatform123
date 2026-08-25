<?php

namespace App\Filament\Admin\Resources\WarehouseSettings\Pages;

use App\Filament\Admin\Resources\WarehouseSettings\WarehouseSettingResource;
use App\Models\WarehouseSetting;
use App\Modules\Admin\Services\WarehouseAdminService;
use App\Modules\Order\Enums\WarehouseConnectionStatus;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditWarehouseSetting extends EditRecord
{
    protected static string $resource = WarehouseSettingResource::class;

    protected static ?string $title = 'Warehouse Integration';

    public function mount(int | string $record): void
    {
        parent::mount($record);
    }

    public function getSubheading(): string|Htmlable|null
    {
        $status = $this->record->connection_status->value;
        $tested = $this->record->last_tested_at?->toDateTimeString() ?? 'never';

        return "Connection: {$status} · Last tested: {$tested}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('test_connection')
                ->label('Test connection')
                ->icon('heroicon-o-signal')
                ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'warehouse.manage'))
                ->action(function (WarehouseAdminService $service): void {
                    $this->record = $service->testConnection($this->record);
                    $this->fillForm();

                    if ($this->record->connection_status === WarehouseConnectionStatus::Healthy) {
                        Notification::make()->title('Connection healthy')->success()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Connection failed')
                        ->body($this->record->last_test_error ?? 'Unknown error')
                        ->danger()
                        ->send();
                }),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (blank($data['api_key'] ?? null)) {
            unset($data['api_key']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        AdminActivityLogger::log(
            module: 'warehouse',
            event: 'settings_updated',
            description: 'Warehouse integration settings updated',
            subject: $this->record,
        );
    }
}
