<?php

namespace App\Filament\Admin\Resources\Funnels\Pages;

use App\Filament\Admin\Exports\FunnelExporter;
use App\Filament\Admin\Resources\Funnels\FunnelResource;
use App\Modules\Admin\Services\FunnelAdminService;
use App\Modules\Order\Enums\FunnelStatus;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListFunnels extends ListRecords
{
    protected static string $resource = FunnelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(FunnelExporter::class)
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'funnels.export')),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $counts = app(FunnelAdminService::class)->tabCounts();

        return [
            'all' => Tab::make('All')
                ->badge((string) $counts['all']),
            'active' => Tab::make('Active')
                ->badge((string) $counts['active'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', FunnelStatus::Active)),
            'draft' => Tab::make('Draft')
                ->badge((string) $counts['draft'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', FunnelStatus::Draft)),
            'archived' => Tab::make('Archived')
                ->badge((string) $counts['archived'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', FunnelStatus::Archived)),
        ];
    }
}
