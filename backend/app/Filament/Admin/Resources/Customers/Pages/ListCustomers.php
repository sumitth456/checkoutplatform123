<?php

namespace App\Filament\Admin\Resources\Customers\Pages;

use App\Filament\Admin\Exports\CustomerExporter;
use App\Filament\Admin\Resources\Customers\CustomerResource;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomers extends ListRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(CustomerExporter::class)
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'customers.export')),
            CreateAction::make(),
        ];
    }
}
