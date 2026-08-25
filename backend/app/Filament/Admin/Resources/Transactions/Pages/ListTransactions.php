<?php

namespace App\Filament\Admin\Resources\Transactions\Pages;

use App\Filament\Admin\Exports\TransactionExporter;
use App\Filament\Admin\Resources\Transactions\TransactionResource;
use App\Filament\Admin\Support\Concerns\AppliesDashboardFilters;
use App\Modules\Admin\Services\TransactionLedgerService;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListTransactions extends ListRecords
{
    use AppliesDashboardFilters;

    protected static string $resource = TransactionResource::class;

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        return $query ? $this->applyDashboardFilters($query, 'transactions') : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(TransactionExporter::class)
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'transactions.export')),
        ];
    }

    public function getTabs(): array
    {
        $counts = app(TransactionLedgerService::class)->tabCounts();
        $successful = app(TransactionLedgerService::class)->successfulStatuses();

        return [
            'all' => Tab::make('All')
                ->badge((string) $counts['all']),
            'successful' => Tab::make('Successful')
                ->badge((string) $counts['successful'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', $successful)),
            'failed' => Tab::make('Failed')
                ->badge((string) $counts['failed'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'failed')),
            'refunds' => Tab::make('Refunds')
                ->badge((string) $counts['refunds'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                    $inner->where('type', 'refund')
                        ->orWhere('status', 'refunded');
                })),
        ];
    }
}
