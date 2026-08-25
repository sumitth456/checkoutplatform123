<?php

namespace App\Filament\Admin\Resources\RebillQueue\Tables;

use App\Filament\Admin\Resources\Subscriptions\SubscriptionResource;
use App\Models\Subscription;
use App\Modules\Admin\Services\AdminSubscriptionActionService;
use App\Modules\Admin\Services\RebillQueueService;
use App\Modules\Subscription\Enums\RebillQueueStatus;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use InvalidArgumentException;

class RebillQueueTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.email')
                    ->label('Customer')
                    ->searchable(),
                TextColumn::make('plan.name')
                    ->label('Plan')
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('rebill_queue_status')
                    ->label('Queue')
                    ->badge(),
                TextColumn::make('amount')
                    ->money(fn (Subscription $record) => $record->currency),
                TextColumn::make('retry_count')
                    ->label('Retries'),
                TextColumn::make('next_billing_at')
                    ->label('Next charge')
                    ->dateTime(),
                TextColumn::make('rebill_skipped_until')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('payment_provider')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('next_billing_at', 'asc')
            ->recordActions([
                ViewAction::make()
                    ->url(fn (Subscription $record): string => SubscriptionResource::getUrl('view', ['record' => $record])),
                Action::make('manual_charge')
                    ->label('Charge now')
                    ->icon('heroicon-o-bolt')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'rebill_queue.manage'))
                    ->action(function (Subscription $record, AdminSubscriptionActionService $actions): void {
                        try {
                            $actions->forceCharge($record);
                            Notification::make()->title('Charge executed')->success()->send();
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    }),
                Action::make('retry')
                    ->label('Retry')
                    ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'rebill_queue.manage'))
                    ->requiresConfirmation()
                    ->action(function (Subscription $record, AdminSubscriptionActionService $actions): void {
                        try {
                            $actions->retryCharge($record, async: false);
                            Notification::make()->title('Retry executed')->success()->send();
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    }),
                Action::make('skip')
                    ->label('Skip charge')
                    ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'rebill_queue.manage'))
                    ->form([
                        DateTimePicker::make('until')
                            ->label('Skip until')
                            ->required()
                            ->default(fn (Subscription $record) => $record->next_billing_at?->addWeek()),
                    ])
                    ->action(function (Subscription $record, array $data, AdminSubscriptionActionService $actions): void {
                        $actions->skipRebillCharge($record, $data['until']);
                        Notification::make()->title('Charge skipped')->success()->send();
                    }),
                Action::make('cancel_rebill')
                    ->label('Cancel rebill')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'rebill_queue.manage'))
                    ->form([
                        TextInput::make('reason')
                            ->default('rebill_cancelled_admin'),
                    ])
                    ->action(function (Subscription $record, array $data, AdminSubscriptionActionService $actions): void {
                        $actions->cancelRebillCharge($record, $data['reason'] ?? null);
                        Notification::make()->title('Rebill cancelled')->success()->send();
                    }),
                Action::make('requeue')
                    ->label('Return to queue')
                    ->visible(fn (Subscription $record): bool => AdminAuthorization::can(auth()->user(), 'rebill_queue.manage')
                        && $record->rebill_queue_status === RebillQueueStatus::Skipped)
                    ->requiresConfirmation()
                    ->action(function (Subscription $record, RebillQueueService $queue): void {
                        $queue->resumeQueue($record);
                        Notification::make()->title('Subscription returned to rebill queue')->success()->send();
                    }),
            ])
            ->toolbarActions([
                ExportBulkAction::make()
                    ->exporter(\App\Filament\Admin\Exports\RebillQueueExporter::class)
                    ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'rebill_queue.export')),
            ]);
    }
}
