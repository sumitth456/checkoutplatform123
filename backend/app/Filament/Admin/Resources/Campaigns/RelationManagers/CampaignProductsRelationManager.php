<?php

namespace App\Filament\Admin\Resources\Campaigns\RelationManagers;

use App\Filament\Admin\Support\BillingCycles\CampaignBillingCycleActions;
use App\Models\CampaignProduct;
use App\Models\Product;
use App\Modules\Campaign\Enums\CampaignProductInventoryRule;
use App\Modules\Campaign\Services\CampaignProductBillingCycleService;
use App\Modules\Campaign\Services\CampaignProductBulkService;
use App\Modules\Campaign\Services\SubscriptionPlanCampaignBillingService;
use App\Modules\Product\BillingCycles\BillingCycleAttributeMapper;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class CampaignProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'campaignProducts';

    protected static ?string $title = 'Campaign products';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return 'Campaign products';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->schema([
                Select::make('product_id')
                    ->label('Base product')
                    ->options(fn (): array => Product::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required()
                    ->disabledOn('edit'),
                Select::make('variant_id')
                    ->label('Variant')
                    ->searchable()
                    ->nullable()
                    ->options(function ($get): array {
                        $productId = $get('product_id');

                        if (blank($productId)) {
                            return [];
                        }

                        return Product::query()->find($productId)?->variants()->pluck('sku', 'id')->all() ?? [];
                    }),
            ]),
            Grid::make(3)->schema([
                TextInput::make('quantity')->numeric()->minValue(1)->default(1)->required(),
                TextInput::make('sku_override')->label('SKU override')->maxLength(120),
                TextInput::make('priority')->numeric()->default(100)->required(),
            ]),
            Grid::make(2)->schema([
                TextInput::make('sort_order')->numeric()->default(0)->required(),
                TextInput::make('campaign_price')->numeric()->minValue(0)->prefix('$'),
            ]),
            Grid::make(2)->schema([
                TextInput::make('min_quantity')->numeric()->minValue(0),
                TextInput::make('max_quantity')->numeric()->minValue(0),
            ]),
            Select::make('inventory_rule')
                ->options(collect(CampaignProductInventoryRule::cases())->mapWithKeys(
                    fn (CampaignProductInventoryRule $rule): array => [$rule->value => str($rule->name)->headline()]
                )->all())
                ->default(CampaignProductInventoryRule::Inherit->value),
            Toggle::make('is_active')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        $bulkService = app(CampaignProductBulkService::class);
        $cycleService = app(CampaignProductBillingCycleService::class);
        $planBilling = app(SubscriptionPlanCampaignBillingService::class);
        $campaign = $this->getOwnerRecord();

        return $table
            ->recordTitleAttribute('product.name')
            ->columns([
                TextColumn::make('product.sku')->label('SKU')->searchable(),
                TextColumn::make('product.name')->label('Product')->searchable(),
                TextColumn::make('variant.sku')->label('Variant'),
                TextColumn::make('quantity')->label('Qty'),
                TextColumn::make('billing_cycles_count')->counts('billingCycles')->label('Cycles'),
                TextColumn::make('priority')->sortable(),
                TextColumn::make('sort_order')->label('Sort')->sortable(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                Action::make('bulk_add')
                    ->label('Bulk add products')
                    ->icon('heroicon-o-plus')
                    ->form([
                        Select::make('product_ids')
                            ->label('Products')
                            ->multiple()
                            ->searchable()
                            ->getSearchResultsUsing(fn (string $search): array => Product::query()
                                ->search($search)
                                ->limit(50)
                                ->pluck('name', 'id')
                                ->all())
                            ->getOptionLabelsUsing(fn (array $values): array => Product::query()
                                ->whereIn('id', $values)
                                ->pluck('name', 'id')
                                ->all())
                            ->required(),
                        TextInput::make('priority')->numeric()->default(100),
                    ])
                    ->action(function (array $data) use ($bulkService): void {
                        $result = $bulkService->bulkAdd($this->getOwnerRecord(), $data['product_ids'], [
                            'priority' => (int) ($data['priority'] ?? 100),
                        ]);

                        Notification::make()
                            ->title('Products added')
                            ->body("{$result['attached']} attached, {$result['skipped']} skipped.")
                            ->success()
                            ->send();
                    }),
                CreateAction::make()
                    ->label('Add product')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['sort_order'] ??= ((int) $this->getOwnerRecord()->campaignProducts()->max('sort_order')) + 1;
                        $data['inventory_rule'] ??= CampaignProductInventoryRule::Inherit->value;
                        $data['is_active'] ??= true;

                        return $data;
                    })
                    ->after(function (CampaignProduct $record) use ($cycleService): void {
                        $cycleService->inheritForCampaignProduct($record);
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                CampaignBillingCycleActions::editBillingCyclesAction($campaign, $cycleService, $planBilling),
                CampaignBillingCycleActions::resetBillingCyclesAction($campaign, $cycleService, $planBilling),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('bulk_remove')
                        ->label('Remove selected')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function ($records) use ($bulkService): void {
                            $bulkService->bulkRemove(
                                $this->getOwnerRecord(),
                                $records->pluck('product_id')->all(),
                            );

                            Notification::make()->title('Products removed')->success()->send();
                        }),
                    BulkAction::make('bulk_replace')
                        ->label('Replace catalog with selection')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records) use ($bulkService): void {
                            $bulkService->bulkReplace(
                                $this->getOwnerRecord(),
                                $records->pluck('product_id')->all(),
                            );

                            Notification::make()->title('Campaign catalog replaced')->success()->send();
                        }),
                    BulkAction::make('bulk_priority')
                        ->label('Set priority')
                        ->form([
                            TextInput::make('priority')->numeric()->required()->minValue(0)->maxValue(9999),
                        ])
                        ->action(function ($records, array $data) use ($bulkService): void {
                            $bulkService->updatePriority(
                                $this->getOwnerRecord(),
                                $records->map(fn (CampaignProduct $row): array => [
                                    'product_id' => $row->product_id,
                                    'priority' => (int) $data['priority'],
                                ])->all(),
                            );

                            Notification::make()->title('Priority updated')->success()->send();
                        }),
                ]),
            ]);
    }
}
