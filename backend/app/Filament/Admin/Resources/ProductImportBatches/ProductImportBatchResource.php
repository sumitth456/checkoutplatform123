<?php

namespace App\Filament\Admin\Resources\ProductImportBatches;

use App\Filament\Admin\Resources\ProductImportBatches\Pages\ViewProductImportBatch;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\ProductImportBatch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ProductImportBatchResource extends Resource
{
    protected static ?string $model = ProductImportBatch::class;

    protected static ?string $slug = 'product-import-batches';

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $modelLabel = 'Import batch';

    public static function getPages(): array
    {
        return [
            'view' => ViewProductImportBatch::route('/{record}'),
        ];
    }
}
