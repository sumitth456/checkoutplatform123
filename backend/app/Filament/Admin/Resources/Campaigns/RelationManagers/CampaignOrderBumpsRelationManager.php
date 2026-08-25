<?php

namespace App\Filament\Admin\Resources\Campaigns\RelationManagers;

use App\Filament\Admin\Resources\Campaigns\Concerns\CampaignMerchandisingFormFields;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CampaignOrderBumpsRelationManager extends RelationManager
{
    protected static string $relationship = 'orderBumps';

    protected static ?string $title = 'Order bumps';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            ...CampaignMerchandisingFormFields::productAndPriority(),
            ...CampaignMerchandisingFormFields::orderBumpFields(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.sku')->label('SKU'),
                TextColumn::make('product.name')->label('Product')->searchable(),
                TextColumn::make('bump_price')->money(fn ($record) => $record->product?->currency ?? 'USD'),
                TextColumn::make('position')->badge(),
                TextColumn::make('display_rule')->badge(),
                TextColumn::make('priority'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
