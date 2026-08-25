<?php

namespace App\Filament\Admin\Resources\Subscriptions\Pages;

use App\Filament\Admin\Exports\SubscriptionExporter;
use App\Filament\Admin\Resources\Subscriptions\SubscriptionResource;
use App\Filament\Admin\Support\Concerns\AppliesDashboardFilters;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSubscriptions extends ListRecords
{
    use AppliesDashboardFilters;

    protected static string $resource = SubscriptionResource::class;

    protected function getTableQuery(): ?Builder
    {
        $query = parent::getTableQuery();

        return $query ? $this->applyDashboardFilters($query, 'subscriptions') : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(SubscriptionExporter::class)
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'subscriptions.export')),
            CreateAction::make(),
        ];
    }
}
