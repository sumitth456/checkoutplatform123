<?php

namespace App\Filament\Admin\Resources\PaymentGateways\Tables;

use App\Filament\Admin\Exports\PaymentGatewayExporter;
use App\Models\PaymentGatewayConfig;
use App\Modules\Admin\Services\PaymentGatewayAdminService;
use App\Modules\Admin\Services\PaymentGatewayLoadService;
use App\Modules\Payment\Enums\GatewayWebhookStatus;
use App\Modules\Payment\Enums\PaymentProvider;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentGatewaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->modifyQueryUsing(fn (Builder $query) => $query)
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('provider')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title())
                    ->color(fn (string $state): string => match ($state) {
                        PaymentProvider::Stripe->value => 'info',
                        PaymentProvider::PayPal->value => 'primary',
                        PaymentProvider::AuthorizeNet->value => 'warning',
                        PaymentProvider::Nmi->value => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('sort_order')
                    ->label('Priority')
                    ->sortable(),
                TextColumn::make('weight')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('daily_load')
                    ->label('Daily load')
                    ->state(function (PaymentGatewayConfig $record): string {
                        $stats = app(PaymentGatewayLoadService::class)->stats($record);
                        $limit = $stats['daily_limit'] !== null ? number_format($stats['daily_limit'], 0) : '∞';

                        return number_format($stats['daily_volume'], 0).' / '.$limit;
                    })
                    ->toggleable(),
                TextColumn::make('webhook_status')
                    ->badge()
                    ->formatStateUsing(fn (GatewayWebhookStatus|string $state): string => $state instanceof GatewayWebhookStatus ? $state->value : $state)
                    ->color(fn (GatewayWebhookStatus|string $state): string => match ($state instanceof GatewayWebhookStatus ? $state : GatewayWebhookStatus::from($state)) {
                        GatewayWebhookStatus::Verified => 'success',
                        GatewayWebhookStatus::Failed => 'danger',
                        GatewayWebhookStatus::Pending => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->disabled(fn (): bool => ! AdminAuthorization::can(auth()->user(), 'payment_gateways.update'))
                    ->afterStateUpdated(function (PaymentGatewayConfig $record, bool $state): void {
                        AdminActivityLogger::log(
                            module: 'payment_gateways',
                            event: $state ? 'enabled' : 'disabled',
                            description: $state
                                ? "Gateway {$record->name} enabled"
                                : "Gateway {$record->name} disabled",
                            subject: $record,
                        );
                    }),
                IconColumn::make('test_mode')
                    ->label('Test')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('provider')
                    ->options(collect(PaymentProvider::cases())->mapWithKeys(
                        fn (PaymentProvider $provider): array => [
                            $provider->value => str($provider->name)->headline(),
                        ]
                    )->all()),
                SelectFilter::make('is_active')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
                SelectFilter::make('webhook_status')
                    ->options(collect(GatewayWebhookStatus::cases())->mapWithKeys(
                        fn (GatewayWebhookStatus $status): array => [
                            $status->value => str($status->name)->headline(),
                        ]
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('verifyWebhook')
                    ->label('Test webhook')
                    ->icon('heroicon-o-signal')
                    ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'payment_gateways.update'))
                    ->action(function (PaymentGatewayConfig $record): void {
                        $gateway = app(PaymentGatewayAdminService::class)->verifyWebhook($record);

                        if ($gateway->webhook_status === GatewayWebhookStatus::Verified) {
                            Notification::make()
                                ->title('Webhook verified')
                                ->success()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Webhook check failed')
                            ->body($gateway->webhook_last_error ?? 'Unknown error')
                            ->danger()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ExportBulkAction::make()
                        ->exporter(PaymentGatewayExporter::class)
                        ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'payment_gateways.export')),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
