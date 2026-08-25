<?php

namespace App\Filament\Admin\Resources\PaymentGateways\Pages;

use App\Filament\Admin\Resources\PaymentGateways\Concerns\LogsPaymentGatewayAdminActivity;
use App\Filament\Admin\Resources\PaymentGateways\PaymentGatewayResource;
use App\Models\PaymentGatewayConfig;
use App\Modules\Admin\Services\PaymentGatewayAdminService;
use App\Modules\Payment\Enums\GatewayWebhookStatus;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPaymentGateway extends ViewRecord
{
    use LogsPaymentGatewayAdminActivity;

    protected static string $resource = PaymentGatewayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('toggleActive')
                ->label(fn (PaymentGatewayConfig $record): string => $record->is_active ? 'Disable gateway' : 'Enable gateway')
                ->icon(fn (PaymentGatewayConfig $record): string => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                ->color(fn (PaymentGatewayConfig $record): string => $record->is_active ? 'danger' : 'success')
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'payment_gateways.update'))
                ->requiresConfirmation()
                ->action(function (PaymentGatewayConfig $record): void {
                    $record->update(['is_active' => ! $record->is_active]);
                    $this->record = $record->fresh();
                    $this->logGatewayActivity(
                        $this->record->is_active ? 'enabled' : 'disabled',
                        $this->record->is_active
                            ? "Gateway {$this->record->name} enabled"
                            : "Gateway {$this->record->name} disabled",
                    );
                    Notification::make()
                        ->title($this->record->is_active ? 'Gateway enabled' : 'Gateway disabled')
                        ->success()
                        ->send();
                }),
            Action::make('verifyWebhook')
                ->label('Test webhook')
                ->icon('heroicon-o-signal')
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'payment_gateways.update'))
                ->action(function (PaymentGatewayConfig $record): void {
                    $gateway = app(PaymentGatewayAdminService::class)->verifyWebhook($record);
                    $this->record = $gateway;

                    if ($gateway->webhook_status === GatewayWebhookStatus::Verified) {
                        Notification::make()->title('Webhook verified')->success()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Webhook check failed')
                        ->body($gateway->webhook_last_error ?? 'Unknown error')
                        ->danger()
                        ->send();
                }),
            DeleteAction::make()
                ->after(fn () => $this->logGatewayActivity('deleted', "Gateway {$this->record->name} soft deleted")),
            ForceDeleteAction::make()
                ->after(fn () => $this->logGatewayActivity('force_deleted', "Gateway {$this->record->name} permanently deleted")),
            RestoreAction::make()
                ->after(fn () => $this->logGatewayActivity('restored', "Gateway {$this->record->name} restored")),
        ];
    }
}
