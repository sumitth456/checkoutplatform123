<?php

namespace App\Filament\Admin\Resources\WarehouseShipments\Pages;

use App\Filament\Admin\Exports\WarehouseShipmentExporter;
use App\Filament\Admin\Resources\WarehouseShipments\WarehouseShipmentResource;
use App\Modules\Admin\Services\WarehouseAdminService;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListWarehouseShipments extends ListRecords
{
    protected static string $resource = WarehouseShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(WarehouseShipmentExporter::class)
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'warehouse.export')),
        ];
    }

    public function getTabs(): array
    {
        $counts = app(WarehouseAdminService::class)->shipmentTabCounts();

        return [
            'all' => Tab::make('All')
                ->badge((string) $counts['all']),
            'submitted' => Tab::make('Submitted')
                ->badge((string) $counts['submitted'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', FulfillmentRecordStatus::Submitted)),
            'in_transit' => Tab::make('In transit')
                ->badge((string) $counts['in_transit'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', FulfillmentRecordStatus::Shipped)),
            'delivered' => Tab::make('Delivered')
                ->badge((string) $counts['delivered'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', FulfillmentRecordStatus::Delivered)),
            'failed' => Tab::make('Failed')
                ->badge((string) $counts['failed'])
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', FulfillmentRecordStatus::Failed)
                    ->whereNotNull('warehouse_request')),
        ];
    }
}
