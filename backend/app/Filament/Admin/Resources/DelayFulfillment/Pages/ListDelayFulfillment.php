<?php

namespace App\Filament\Admin\Resources\DelayFulfillment\Pages;

use App\Filament\Admin\Exports\DelayFulfillmentExporter;
use App\Filament\Admin\Resources\DelayFulfillment\DelayFulfillmentResource;
use App\Filament\Admin\Support\Concerns\AppliesDashboardFilters;
use App\Modules\Admin\Services\AdminDelayFulfillmentService;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListDelayFulfillment extends ListRecords
{
    use AppliesDashboardFilters;

    protected static string $resource = DelayFulfillmentResource::class;

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        return $query ? $this->applyDashboardFilters($query, 'fulfillments') : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('process_due')
                ->label('Process due queue')
                ->icon('heroicon-o-play')
                ->requiresConfirmation()
                ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'fulfillment.manage')
                    || AdminAuthorization::can(auth()->user(), 'delay_fulfillment.manage'))
                ->action(function (AdminDelayFulfillmentService $service): void {
                    $count = $service->processDueQueue();
                    Notification::make()
                        ->title("Queued {$count} warehouse submission job(s)")
                        ->success()
                        ->send();
                }),
            ExportAction::make()
                ->exporter(DelayFulfillmentExporter::class)
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'fulfillment.view')
                    || AdminAuthorization::can(auth()->user(), 'delay_fulfillment.export')),
        ];
    }

    public function getTabs(): array
    {
        $counts = app(AdminDelayFulfillmentService::class)->tabCounts();
        $today = now()->toDateString();
        $badge = fn (string $key): string => (string) ($counts[$key] ?? 0);

        return [
            'scheduled' => Tab::make('Scheduled')
                ->badge($badge('scheduled'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', FulfillmentRecordStatus::Scheduled)
                    ->whereDate('scheduled_ship_date', '>', $today)),
            'due_today' => Tab::make('Due today')
                ->badge($badge('due_today'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', FulfillmentRecordStatus::Scheduled)
                    ->whereDate('scheduled_ship_date', $today)),
            'overdue' => Tab::make('Overdue')
                ->badge($badge('overdue'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', FulfillmentRecordStatus::Scheduled)
                    ->whereDate('scheduled_ship_date', '<', $today)),
            'in_transit' => Tab::make('In transit')
                ->badge($badge('in_transit'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    FulfillmentRecordStatus::Submitted,
                    FulfillmentRecordStatus::Shipped,
                    FulfillmentRecordStatus::Delivered,
                ])),
            'failed' => Tab::make('Failed')
                ->badge($badge('failed'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', FulfillmentRecordStatus::Failed)),
            'cancelled' => Tab::make('Cancelled')
                ->badge($badge('cancelled'))
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', FulfillmentRecordStatus::Cancelled)),
        ];
    }
}
