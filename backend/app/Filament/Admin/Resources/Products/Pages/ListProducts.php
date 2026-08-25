<?php

namespace App\Filament\Admin\Resources\Products\Pages;

use App\Filament\Admin\Exports\ProductExporter;
use App\Filament\Admin\Pages\ProductImportWizard;
use App\Filament\Admin\Resources\Products\ProductResource;
use App\Models\Funnel;
use App\Modules\Product\Import\Services\ProductCatalogExportService;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Response;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import products')
                ->icon('heroicon-o-arrow-up-tray')
                ->url(fn (): string => ProductImportWizard::getUrl())
                ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'products.import.manage')),
            Action::make('export_catalog')
                ->label('Export catalog (CSV/XLSX)')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'products.export'))
                ->form([
                    Select::make('format')
                        ->options(['csv' => 'CSV', 'xlsx' => 'Excel (XLSX)'])
                        ->default('csv')
                        ->required(),
                    Select::make('funnel_id')
                        ->label('Campaign filter')
                        ->options(fn (): array => Funnel::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable(),
                    Select::make('status')
                        ->options([
                            'active' => 'Active',
                            'draft' => 'Draft',
                            'archived' => 'Archived',
                        ]),
                ])
                ->action(function (array $data): mixed {
                    $path = app(ProductCatalogExportService::class)->export([
                        'format' => $data['format'] ?? 'csv',
                        'funnel_id' => $data['funnel_id'] ?? null,
                        'status' => $data['status'] ?? null,
                    ]);

                    AdminActivityLogger::log(
                        module: 'products',
                        event: 'catalog.exported',
                        description: 'Product catalog exported from admin',
                        properties: $data,
                    );

                    return Response::download($path, basename($path));
                }),
            ExportAction::make()
                ->exporter(ProductExporter::class)
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'products.export')),
            CreateAction::make(),
        ];
    }
}
