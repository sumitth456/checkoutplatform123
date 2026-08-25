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

class CampaignDownsellsRelationManager extends RelationManager
{
    protected static string $relationship = 'downsells';

    protected static ?string $title = 'Downsells';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            ...CampaignMerchandisingFormFields::productAndPriority(),
            ...CampaignMerchandisingFormFields::triggerFields(),
            ...CampaignMerchandisingFormFields::discountFields(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product.sku')->label('SKU'),
                TextColumn::make('product.name')->label('Product')->searchable(),
                TextColumn::make('priority')->sortable(),
                TextColumn::make('trigger')->badge(),
                TextColumn::make('discount_type')->badge(),
                TextColumn::make('discount_value'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }
}
