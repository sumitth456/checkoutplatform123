<?php

namespace App\Filament\Admin\Pages\CampaignManagement;

use App\Filament\Admin\Resources\Campaigns\CampaignResource;
use App\Filament\Admin\Support\BillingCycles\CampaignBillingCycleActions;
use App\Models\CampaignProduct;
use App\Models\Product;
use App\Modules\Campaign\Enums\CampaignProductInventoryRule;
use App\Modules\Campaign\Services\CampaignProductBillingCycleService;
use App\Modules\Campaign\Services\CampaignProductBulkService;
use App\Modules\Campaign\Services\SubscriptionPlanCampaignBillingService;
use App\Support\Admin\AdminActivityLogger;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page as ResourcePage;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ManageCampaignProducts extends ResourcePage implements Tables\Contracts\HasTable
{
    use InteractsWithRecord;
    use Tables\Concerns\InteractsWithTable;

    protected static string $resource = CampaignResource::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'Campaign products';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);
        $this->record->loadCount('campaignProducts');

        abort_unless(
            AdminAuthorization::can(auth()->user(), 'campaigns.products.manage'),
            403,
        );

        AdminActivityLogger::log(
            module: 'campaigns',
            event: 'products_manager_viewed',
            description: "Campaign product manager opened for {$this->record->public_id}",
            subject: $this->record,
        );
    }

    public function getTitle(): string
    {
        return "Campaign products — {$this->record->name}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_products')
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
                ->action(function (array $data): void {
                    $service = app(CampaignProductBulkService::class);
                    $result = $service->bulkAdd($this->record, $data['product_ids'], [
                        'priority' => (int) ($data['priority'] ?? 100),
                    ]);

                    AdminActivityLogger::log(
                        module: 'campaigns',
                        event: 'products_bulk_added',
                        description: "Added {$result['attached']} products to campaign {$this->record->public_id}",
                        subject: $this->record,
                        properties: $result,
                    );

                    Notification::make()
                        ->title('Products added')
                        ->body("{$result['attached']} attached, {$result['skipped']} skipped.")
                        ->success()
                        ->send();
                }),
            Action::make('back')
                ->label('Back to campaign')
                ->url(CampaignResource::getUrl('view', ['record' => $this->record])),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        $service = app(CampaignProductBulkService::class);
        $cycleService = app(CampaignProductBillingCycleService::class);
        $planBilling = app(SubscriptionPlanCampaignBillingService::class);
        $campaign = $this->record;

        return $table
            ->heading('Campaign products')
            ->description(fn (): string => app(SubscriptionPlanCampaignBillingService::class)->campaignUsesPlan($this->record)
                ? 'This campaign uses a subscription plan — rebill schedule, pricing, retry, and gateway settings are inherited. Configure bill product/SKU per line only.'
                : 'Drag rows to reorder. Each line inherits catalog billing cycles and can be overridden per cycle.')
            ->relationship(fn () => $this->record->campaignProducts()->with(['product', 'variant']))
            ->columns([
                TextColumn::make('product.sku')->label('SKU')->searchable(),
                TextColumn::make('product.name')->label('Product')->searchable(),
                TextColumn::make('variant.sku')->label('Variant'),
                TextColumn::make('quantity')->label('Qty'),
                TextColumn::make('billing_cycles_count')->counts('billingCycles')->label('Cycles'),
                TextColumn::make('priority'),
                TextColumn::make('sort_order')->label('Sort'),
                TextColumn::make('inventory_rule')->badge(),
                IconColumn::make('is_active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->searchable(['product.name', 'product.sku'])
            ->recordActions([
                CampaignBillingCycleActions::editBillingCyclesAction($campaign, $cycleService, $planBilling),
            ])
            ->toolbarActions([
                BulkAction::make('bulk_remove')
                    ->label('Remove from campaign')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(fn ($records) => $this->runBulk(
                        fn () => $service->bulkRemove($this->record, $records->pluck('product_id')->all()),
                        'Products removed',
                    )),
                BulkAction::make('bulk_replace')
                    ->label('Replace campaign catalog')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Removes all other products and keeps only the selected rows.')
                    ->action(fn ($records) => $this->runBulk(
                        fn () => $service->bulkReplace($this->record, $records->pluck('product_id')->all()),
                        'Campaign catalog replaced',
                    )),
                BulkAction::make('bulk_priority')
                    ->label('Set priority')
                    ->form([
                        TextInput::make('priority')->numeric()->required()->minValue(0)->maxValue(9999),
                    ])
                    ->action(function ($records, array $data) use ($service): void {
                        $service->updatePriority(
                            $this->record,
                            $records->map(fn (CampaignProduct $row): array => [
                                'product_id' => $row->product_id,
                                'priority' => (int) $data['priority'],
                            ])->all(),
                        );
                        $this->runBulk(null, 'Priority updated');
                    }),
                BulkAction::make('bulk_rules')
                    ->label('Update quantity rules')
                    ->form([
                        TextInput::make('min_quantity')->numeric()->minValue(0),
                        TextInput::make('max_quantity')->numeric()->minValue(0),
                        Select::make('inventory_rule')
                            ->options(collect(CampaignProductInventoryRule::cases())->mapWithKeys(
                                fn (CampaignProductInventoryRule $rule): array => [$rule->value => str($rule->name)->headline()]
                            )->all()),
                    ])
                    ->action(function ($records, array $data) use ($service): void {
                        $updates = $records->map(fn (CampaignProduct $row): array => array_filter([
                            'product_id' => $row->product_id,
                            'min_quantity' => isset($data['min_quantity']) ? (int) $data['min_quantity'] : null,
                            'max_quantity' => isset($data['max_quantity']) ? (int) $data['max_quantity'] : null,
                            'inventory_rule' => $data['inventory_rule'] ?? null,
                        ]))->all();

                        $service->bulkUpdateRules($this->record, $updates);
                        $this->runBulk(null, 'Quantity rules updated');
                    }),
            ]);
    }

    private function runBulk(?callable $callback, string $message): void
    {
        if ($callback !== null) {
            $callback();
        }

        $this->record->loadCount('campaignProducts');

        AdminActivityLogger::log(
            module: 'campaigns',
            event: 'products_bulk_updated',
            description: $message,
            subject: $this->record,
        );

        Notification::make()->title($message)->success()->send();
    }
}
