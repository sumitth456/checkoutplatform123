<?php

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Filament\Admin\Resources\ProductImportBatches\ProductImportBatchResource;
use App\Models\ProductImportBatch;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class ProductImportHistory extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::PRODUCTS;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Import history';

    protected static ?string $slug = 'products/import-history';

    protected static ?string $title = 'Product import history';

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'products.import.view');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(ProductImportBatch::query()->with('user')->latest())
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('user.name')->label('Imported by'),
                TextColumn::make('original_filename')->label('File')->limit(30),
                TextColumn::make('status')->badge(),
                TextColumn::make('stats.imported_count')
                    ->label('Products')
                    ->default('—'),
                TextColumn::make('stats.validation.error_count')
                    ->label('Errors')
                    ->default('—'),
                TextColumn::make('stats.duration_ms')
                    ->label('Duration (ms)')
                    ->default('—'),
                TextColumn::make('completed_at')->dateTime()->placeholder('—'),
            ])
            ->recordUrl(fn (ProductImportBatch $record): string => ProductImportBatchResource::getUrl('view', ['record' => $record]));
    }
}
