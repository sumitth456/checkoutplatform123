<?php

namespace App\Filament\Admin\Resources\EmailLogs\Tables;

use App\Models\EmailLog;
use App\Modules\Email\Enums\EmailLogStatus;
use App\Modules\Email\Services\EmailDispatchService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (EmailLogStatus|string $state): string => match ($state instanceof EmailLogStatus ? $state->value : $state) {
                        'delivered', 'sent' => 'success',
                        'opened', 'clicked' => 'info',
                        'failed', 'bounced', 'spam' => 'danger',
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
                TextColumn::make('template.name')
                    ->label('Template')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('retry_count')
                    ->sortable(),
                TextColumn::make('sent_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('delivered_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(EmailLogStatus::cases())->mapWithKeys(
                        fn (EmailLogStatus $status): array => [
                            $status->value => str($status->name)->headline(),
                        ],
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('resend')
                    ->label('Resend')
                    ->icon('heroicon-o-paper-airplane')
                    ->authorize(fn (EmailLog $record): bool => auth()->user()?->can('resend', $record) ?? false)
                    ->visible(fn (EmailLog $record): bool => $record->template !== null)
                    ->requiresConfirmation()
                    ->action(function (EmailLog $record, EmailDispatchService $dispatch): void {
                        $dispatch->queueTemplate(
                            template: $record->template,
                            recipientEmail: $record->recipient_email,
                            recipientName: $record->recipient_name,
                        );

                        Notification::make()
                            ->title('Email requeued for delivery')
                            ->success()
                            ->send();
                    }),
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->authorize(fn (EmailLog $record): bool => auth()->user()?->can('retry', $record) ?? false)
                    ->visible(fn (EmailLog $record): bool => $record->queueItem !== null
                        && $record->status === EmailLogStatus::Failed)
                    ->requiresConfirmation()
                    ->action(function (EmailLog $record, EmailDispatchService $dispatch): void {
                        $dispatch->retry($record->queueItem);

                        Notification::make()
                            ->title('Queue item requeued')
                            ->success()
                            ->send();
                    }),
                Action::make('downloadHtml')
                    ->label('Download HTML')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->authorize(fn (EmailLog $record): bool => auth()->user()?->can('view', $record) ?? false)
                    ->visible(fn (EmailLog $record): bool => filled($record->html_body))
                    ->action(function (EmailLog $record): StreamedResponse {
                        return response()->streamDownload(
                            fn () => print($record->html_body ?? ''),
                            "email-{$record->uuid}.html",
                            ['Content-Type' => 'text/html; charset=UTF-8'],
                        );
                    }),
            ]);
    }
}
