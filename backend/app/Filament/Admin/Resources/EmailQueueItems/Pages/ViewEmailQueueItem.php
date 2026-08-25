<?php

namespace App\Filament\Admin\Resources\EmailQueueItems\Pages;

use App\Filament\Admin\Resources\EmailQueueItems\EmailQueueItemResource;
use App\Models\EmailQueueItem;
use App\Modules\Email\Enums\EmailQueueStatus;
use App\Modules\Email\Services\EmailDispatchService;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewEmailQueueItem extends ViewRecord
{
    protected static string $resource = EmailQueueItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
                    $this->record = $record->fresh();
                    Notification::make()->title('Queue item requeued')->success()->send();
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
                    $this->record = $record->fresh();
                    Notification::make()->title('Queue item cancelled')->success()->send();
                }),
        ];
    }
}
