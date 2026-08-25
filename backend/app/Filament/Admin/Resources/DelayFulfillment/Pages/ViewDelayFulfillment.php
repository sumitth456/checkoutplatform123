<?php

namespace App\Filament\Admin\Resources\DelayFulfillment\Pages;

use App\Filament\Admin\Resources\DelayFulfillment\Concerns\LogsDelayFulfillmentAdminActivity;
use App\Filament\Admin\Resources\DelayFulfillment\DelayFulfillmentResource;
use App\Models\Fulfillment;
use App\Modules\Admin\Services\AdminDelayFulfillmentService;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use InvalidArgumentException;

class ViewDelayFulfillment extends ViewRecord
{
    use LogsDelayFulfillmentAdminActivity;

    protected static string $resource = DelayFulfillmentResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        $this->record->load(['order', 'timelineEvents']);

        $this->logDelayFulfillmentActivity(
            'viewed',
            "Delayed fulfillment {$this->record->uuid} viewed",
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            ...$this->managementActions(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    protected function managementActions(): array
    {
        if (! AdminAuthorization::can(auth()->user(), 'fulfillment.manage')
            && ! AdminAuthorization::can(auth()->user(), 'delay_fulfillment.manage')) {
            return [];
        }

        return [
            Action::make('submit_now')
                ->label('Submit to warehouse')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->requiresConfirmation()
                ->visible(fn (Fulfillment $record): bool => in_array($record->status, [
                    FulfillmentRecordStatus::Scheduled,
                    FulfillmentRecordStatus::Failed,
                ], true))
                ->action(function (Fulfillment $record, AdminDelayFulfillmentService $service): void {
                    try {
                        $this->record = $service->forceSubmitToWarehouse($record);
                        $this->logDelayFulfillmentActivity('submitted', 'Fulfillment submitted to warehouse from admin');
                        Notification::make()->title('Submitted to warehouse')->success()->send();
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();
                    }
                }),
            Action::make('reschedule')
                ->label('Reschedule')
                ->icon('heroicon-o-calendar')
                ->visible(fn (Fulfillment $record): bool => $record->status === FulfillmentRecordStatus::Scheduled)
                ->schema([
                    DatePicker::make('scheduled_ship_date')
                        ->label('New ship date')
                        ->required()
                        ->default(fn (Fulfillment $record) => $record->scheduled_ship_date)
                        ->native(false),
                ])
                ->action(function (Fulfillment $record, array $data, AdminDelayFulfillmentService $service): void {
                    try {
                        $this->record = $service->reschedule($record, \Illuminate\Support\Carbon::parse($data['scheduled_ship_date']));
                        $this->logDelayFulfillmentActivity('rescheduled', 'Ship date updated from admin');
                        Notification::make()->title('Ship date updated')->success()->send();
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();
                    }
                }),
            Action::make('retry')
                ->label('Retry')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->visible(fn (Fulfillment $record): bool => $record->status === FulfillmentRecordStatus::Failed)
                ->action(function (Fulfillment $record, AdminDelayFulfillmentService $service): void {
                    try {
                        $this->record = $service->retryFailed($record);
                        $this->logDelayFulfillmentActivity('retried', 'Failed fulfillment returned to scheduled');
                        Notification::make()->title('Fulfillment re-queued')->success()->send();
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();
                    }
                }),
            Action::make('cancel')
                ->label('Cancel')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (Fulfillment $record): bool => in_array($record->status, [
                    FulfillmentRecordStatus::Scheduled,
                    FulfillmentRecordStatus::Failed,
                ], true))
                ->schema([
                    Textarea::make('reason')
                        ->label('Reason')
                        ->rows(2),
                ])
                ->action(function (Fulfillment $record, array $data, AdminDelayFulfillmentService $service): void {
                    try {
                        $this->record = $service->cancel($record, $data['reason'] ?? null);
                        $this->logDelayFulfillmentActivity('cancelled', 'Delayed fulfillment cancelled from admin');
                        Notification::make()->title('Fulfillment cancelled')->success()->send();
                    } catch (InvalidArgumentException $exception) {
                        Notification::make()->title($exception->getMessage())->danger()->send();
                    }
                }),
        ];
    }
}
