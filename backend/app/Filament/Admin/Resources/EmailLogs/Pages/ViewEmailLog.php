<?php

namespace App\Filament\Admin\Resources\EmailLogs\Pages;

use App\Filament\Admin\Resources\EmailLogs\EmailLogResource;
use App\Models\EmailLog;
use App\Modules\Email\Enums\EmailLogStatus;
use App\Modules\Email\Services\EmailDispatchService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewEmailLog extends ViewRecord
{
    protected static string $resource = EmailLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
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

                    Notification::make()->title('Email requeued for delivery')->success()->send();
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
                    Notification::make()->title('Queue item requeued')->success()->send();
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
        ];
    }
}
