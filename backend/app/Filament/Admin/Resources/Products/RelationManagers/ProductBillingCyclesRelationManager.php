<?php

namespace App\Filament\Admin\Resources\Products\RelationManagers;

use App\Filament\Admin\Support\BillingCycles\BillingCycleFormSchema;
use App\Models\Product;
use App\Models\ProductBillingCycle;
use App\Modules\Product\BillingCycles\ProductBillingCycleService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductBillingCyclesRelationManager extends RelationManager
{
    protected static string $relationship = 'billingCycles';

    protected static ?string $title = 'Billing cycles';

    protected static ?string $recordTitleAttribute = 'cycle_number';

    public function form(Schema $schema): Schema
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();

        return $schema->components(BillingCycleFormSchema::cycleFields('product', $product));
    }

    public function table(Table $table): Table
    {
        $service = app(ProductBillingCycleService::class);

        return $table
            ->heading('Rebill products')
            ->description('Default billing cycles inherited when this product is added to a campaign. Use + Add cycle for Cycle 4, 5, 6…')
            ->columns([
                TextColumn::make('cycle_number')
                    ->label('Cycle')
                    ->formatStateUsing(fn (ProductBillingCycle $record): string => $record->label())
                    ->sortable(),
                TextColumn::make('billProduct.name')->label('Bill product')->searchable(),
                TextColumn::make('variant.sku')->label('Variant SKU'),
                TextColumn::make('price')->money(fn (ProductBillingCycle $record) => $record->billProduct?->currency ?? 'USD'),
                TextColumn::make('shipping_price')->label('Shipping')->money(fn (ProductBillingCycle $record) => $record->billProduct?->currency ?? 'USD'),
                TextColumn::make('bill_delay_days')->label('Bill delay'),
                IconColumn::make('delay_fulfillment')->boolean(),
                IconColumn::make('shippable')->boolean(),
                IconColumn::make('subscription_enabled')->label('Subscription')->boolean(),
                IconColumn::make('repeat_forever')->boolean(),
            ])
            ->defaultSort('cycle_number')
            ->headerActions([
                Action::make('seed_defaults')
                    ->label('Generate default template')
                    ->icon('heroicon-o-sparkles')
                    ->visible(fn (): bool => $this->getOwnerRecord()->billingCycles()->count() === 0)
                    ->action(function () use ($service): void {
                        $service->ensureDefaultTemplate($this->getOwnerRecord());

                        Notification::make()
                            ->title('Default billing cycles created')
                            ->body('Cycles 1, 2, and 3+ were generated from the catalog product.')
                            ->success()
                            ->send();
                    }),
                CreateAction::make()
                    ->label('Add cycle')
                    ->mutateFormDataUsing(function (array $data) use ($service): array {
                        $product = $this->getOwnerRecord();
                        $data['cycle_number'] ??= ($product->billingCycles()->max('cycle_number') ?? 0) + 1;

                        return $data;
                    })
                    ->using(function (array $data, ProductBillingCycleService $service): ProductBillingCycle {
                        return $service->addCycle($this->getOwnerRecord(), $data);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->using(function (ProductBillingCycle $record, array $data, ProductBillingCycleService $service): ProductBillingCycle {
                        return $service->updateCycle($record, $data);
                    }),
                DeleteAction::make()
                    ->using(fn (ProductBillingCycle $record, ProductBillingCycleService $service) => $service->deleteCycle($record)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
