<?php

namespace App\Filament\Admin\Resources\EmailQueueItems\Tables;

use App\Models\EmailQueueItem;
use App\Modules\Email\Enums\EmailQueueStatus;
use App\Modules\Email\Services\EmailDispatchService;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class QueueItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('scheduled_at', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (EmailQueueStatus|string $state): string => match ($state instanceof EmailQueueStatus ? $state->value : $state) {
                        'sent' => 'success',
                        'processing', 'queued' => 'info',
                        'failed', 'dead' => 'danger',
                        'cancelled' => 'gray',
                        'retry' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('recipient_email')
                    ->label('Recipient')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('attempts')
                    ->sortable(),
                TextColumn::make('scheduled_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('sent_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('template.name')
                    ->label('Template')
                    ->toggleable()
                    ->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(EmailQueueStatus::cases())->mapWithKeys(
                        fn (EmailQueueStatus $status): array => [
                            $status->value => str($status->name)->headline(),
                        ],
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (EmailQueueItem $record): bool => in_array($record->status, [
                        EmailQueueStatus::Failed,
                        EmailQueueStatus::Retry,
                        EmailQueueStatus::Dead,
                    ], true))
                    ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'email_logs.resend'))
                    ->requiresConfirmation()
                    ->action(function (EmailQueueItem $record, EmailDispatchService $dispatch): void {
                        $dispatch->retry($record);

                        Notification::make()
                            ->title('Queue item requeued')
                            ->success()
                            ->send();
                    }),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (EmailQueueItem $record): bool => ! in_array($record->status, [
                        EmailQueueStatus::Sent,
                        EmailQueueStatus::Cancelled,
                        EmailQueueStatus::Dead,
                    ], true))
                    ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'email_logs.resend'))
                    ->requiresConfirmation()
                    ->action(function (EmailQueueItem $record): void {
                        $record->update(['status' => EmailQueueStatus::Cancelled]);

                        Notification::make()
                            ->title('Queue item cancelled')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
