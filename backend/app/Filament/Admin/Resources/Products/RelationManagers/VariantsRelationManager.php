<?php

namespace App\Filament\Admin\Resources\Products\RelationManagers;

use App\Models\Variant;
use App\Modules\Variant\Enums\InventoryPolicy;
use App\Modules\Variant\Enums\VariantStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class VariantsRelationManager extends RelationManager
{
    protected static string $relationship = 'variants';

    protected static ?string $title = 'Variants';

    protected static ?string $recordTitleAttribute = 'sku';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Grid::make(2)->schema([
                    TextInput::make('name')
                        ->maxLength(255),
                    TextInput::make('sku')
                        ->label('SKU')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),
                    TextInput::make('barcode')
                        ->maxLength(255),
                    Select::make('status')
                        ->options(collect(VariantStatus::cases())->mapWithKeys(
                            fn (VariantStatus $status): array => [$status->value => str($status->name)->headline()]
                        )->all())
                        ->required()
                        ->default(VariantStatus::Active->value),
                    Toggle::make('is_default')
                        ->label('Default variant'),
                ]),
                Grid::make(2)->schema([
                    TextInput::make('currency')
                        ->default(fn (): string => $this->getOwnerRecord()->currency ?? 'USD')
                        ->maxLength(3)
                        ->required(),
                    TextInput::make('price')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->prefix('$'),
                    TextInput::make('compare_at_price')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('$'),
                    TextInput::make('cost')
                        ->numeric()
                        ->minValue(0)
                        ->prefix('$'),
                ]),
                Grid::make(2)->schema([
                    Toggle::make('track_inventory')
                        ->live(),
                    TextInput::make('inventory_quantity')
                        ->numeric()
                        ->default(0)
                        ->visible(fn ($get): bool => (bool) $get('track_inventory')),
                    Select::make('inventory_policy')
                        ->options(collect(InventoryPolicy::cases())->mapWithKeys(
                            fn (InventoryPolicy $policy): array => [$policy->value => str($policy->name)->headline()]
                        )->all())
                        ->default(InventoryPolicy::Deny->value)
                        ->visible(fn ($get): bool => (bool) $get('track_inventory')),
                    TextInput::make('weight')
                        ->numeric()
                        ->minValue(0),
                    TextInput::make('weight_unit')
                        ->maxLength(10),
                ]),
                KeyValue::make('options')
                    ->label('Variant options')
                    ->columnSpanFull(),
                FileUpload::make('images')
                    ->label('Images')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->directory('products/variants')
                    ->disk('public')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('price')
                    ->money(fn (Variant $record) => $record->currency)
                    ->sortable(),
                TextColumn::make('inventory_quantity')
                    ->label('Inventory')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('track_inventory')
                    ->boolean()
                    ->toggleable(),
                IconColumn::make('is_default')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
            ])
            ->defaultSort('is_default', 'desc')
            ->filters([
                TrashedFilter::make(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        if (blank($data['currency'] ?? null)) {
                            $data['currency'] = $this->getOwnerRecord()->currency ?? 'USD';
                        }

                        return $this->ensureSingleDefault($data);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->mutateFormDataUsing(fn (array $data): array => $this->ensureSingleDefault($data)),
                DeleteAction::make(),
                ForceDeleteAction::make(),
                RestoreAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function ensureSingleDefault(array $data): array
    {
        if (! ($data['is_default'] ?? false)) {
            return $data;
        }

        $this->getOwnerRecord()
            ->variants()
            ->where('is_default', true)
            ->update(['is_default' => false]);

        return $data;
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->variants()->count();
    }
}
