<?php

namespace App\Filament\Admin\Resources\Funnels\RelationManagers;

use App\Models\FunnelStep;
use App\Modules\Order\Enums\FunnelStepType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class FunnelStepsRelationManager extends RelationManager
{
    protected static string $relationship = 'steps';

    protected static ?string $title = 'Funnel steps';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Grid::make(2)->schema([
                    TextInput::make('step_order')
                        ->numeric()
                        ->required()
                        ->minValue(1),
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    Select::make('type')
                        ->options(collect(FunnelStepType::cases())->mapWithKeys(
                            fn (FunnelStepType $type): array => [$type->value => str($type->name)->headline()]
                        )->all())
                        ->required()
                        ->native(false),
                    Select::make('product_id')
                        ->label('Product')
                        ->relationship('product', 'name')
                        ->searchable()
                        ->preload(),
                    Select::make('variant_id')
                        ->label('Variant')
                        ->relationship('variant', 'sku')
                        ->searchable()
                        ->preload(),
                    Select::make('next_step_id')
                        ->label('Next step')
                        ->options(fn (): array => $this->getOwnerRecord()
                            ->steps()
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable(),
                    Toggle::make('is_required')
                        ->default(true),
                    Toggle::make('is_active')
                        ->default(true),
                ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('step_order')
            ->columns([
                TextColumn::make('step_order')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (FunnelStepType|string $state): string => $state instanceof FunnelStepType ? $state->value : $state),
                TextColumn::make('product.name')
                    ->placeholder('—'),
                TextColumn::make('variant.sku')
                    ->label('Variant')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->boolean(),
                IconColumn::make('is_required')
                    ->boolean(),
            ])
            ->defaultSort('step_order')
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        return (string) $ownerRecord->steps()->count();
    }
}
