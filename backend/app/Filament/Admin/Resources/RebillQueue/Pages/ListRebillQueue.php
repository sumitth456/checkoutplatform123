<?php

namespace App\Filament\Admin\Resources\RebillQueue\Pages;

use App\Filament\Admin\Exports\RebillQueueExporter;
use App\Filament\Admin\Resources\RebillQueue\RebillQueueResource;
use App\Filament\Admin\Support\Concerns\AppliesDashboardFilters;
use App\Modules\Admin\Services\RebillQueueService;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use App\Modules\Subscription\Enums\RebillQueueStatus;
use App\Modules\Subscription\Enums\SubscriptionStatus;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRebillQueue extends ListRecords
{
    use AppliesDashboardFilters;

    protected static string $resource = RebillQueueResource::class;

    public function mount(): void
    {
        parent::mount();

        if (request()->boolean('df_upcoming')) {
            $this->activeTab = 'upcoming';
        } elseif (request()->boolean('df_failed')) {
            $this->activeTab = 'failed';
        } elseif (request()->boolean('df_retry')) {
            $this->activeTab = 'retries';
        }
    }

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        return $query ? $this->applyDashboardFilters($query, 'rebill_queue') : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(RebillQueueExporter::class)
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'rebill_queue.export')),
        ];
    }

    public function getTabs(): array
    {
        $counts = app(RebillQueueService::class)->counts();

        return [
            'upcoming' => Tab::make('Upcoming charges')
                ->badge((string) $counts['upcoming'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('rebill_queue_status', RebillQueueStatus::Queued)
                    ->whereIn('status', [
                        SubscriptionStatus::Trial,
                        SubscriptionStatus::Active,
                        SubscriptionStatus::PastDue,
                    ])
                    ->whereNotNull('next_billing_at')
                    ->where('next_billing_at', '>', now())),
            'due' => Tab::make('Due now')
                ->badge((string) $counts['due'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('rebill_queue_status', RebillQueueStatus::Queued)
                    ->whereIn('status', [
                        SubscriptionStatus::Trial,
                        SubscriptionStatus::Active,
                        SubscriptionStatus::PastDue,
                    ])
                    ->whereNotNull('next_billing_at')
                    ->where('next_billing_at', '<=', now())),
            'failed' => Tab::make('Failed charges')
                ->badge((string) $counts['failed'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('rebill_queue_status', RebillQueueStatus::Queued)
                    ->where(function (Builder $inner): void {
                        $inner->where('status', SubscriptionStatus::PastDue)
                            ->orWhereHas('billingLedgerEntries', function (Builder $ledger): void {
                                $ledger->where('outcome', BillingLedgerOutcome::Failure)
                                    ->where('attempted_at', '>=', now()->subDays(30));
                            });
                    })),
            'retries' => Tab::make('Retries')
                ->badge((string) $counts['retries'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('rebill_queue_status', RebillQueueStatus::Queued)
                    ->where('retry_count', '>', 0)
                    ->whereIn('status', [
                        SubscriptionStatus::PastDue,
                        SubscriptionStatus::Active,
                        SubscriptionStatus::Trial,
                    ])),
            'skipped' => Tab::make('Skipped')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('rebill_queue_status', RebillQueueStatus::Skipped)),
        ];
    }
}
