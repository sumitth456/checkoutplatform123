<?php

namespace App\Filament\Admin\Support\BillingCycles;

use App\Models\Product;
use App\Models\Variant;
use App\Modules\Campaign\Enums\CampaignProductInventoryRule;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Illuminate\Database\Eloquent\Builder;

final class BillingCycleFormSchema
{
    /**
     * Per-cycle bill product/SKU fields when billing is managed by a campaign subscription plan.
     *
     * @return list<\Filament\Schemas\Components\Component>
     */
    public static function planManagedSkuFields(): array
    {
        return [
            TextInput::make('cycle_number')
                ->label('Cycle #')
                ->numeric()
                ->disabled()
                ->dehydrated(),
            Select::make('product_id')
                ->label('Bill product (SKU)')
                ->searchable()
                ->preload()
                ->required()
                ->getSearchResultsUsing(fn (string $search): array => Product::query()
                    ->search($search)
                    ->limit(50)
                    ->pluck('name', 'id')
                    ->all())
                ->getOptionLabelUsing(fn ($value): ?string => Product::query()->find($value)?->name)
                ->live(),
            Select::make('variant_id')
                ->label('Variant SKU')
                ->searchable()
                ->nullable()
                ->options(function ($get): array {
                    $productId = $get('product_id');

                    if (blank($productId)) {
                        return [];
                    }

                    return Variant::query()
                        ->where('product_id', $productId)
                        ->pluck('sku', 'id')
                        ->all();
                }),
            Grid::make(3)->schema([
                TextInput::make('price')
                    ->prefix('$')
                    ->disabled()
                    ->dehydrated(),
                TextInput::make('bill_delay_days')
                    ->label('Bill delay (days)')
                    ->disabled()
                    ->dehydrated(),
                Toggle::make('repeat_forever')
                    ->label('Repeat forever')
                    ->disabled()
                    ->dehydrated(),
            ]),
        ];
    }

    /**
     * @param  'product'|'campaign'  $context
     * @return list<\Filament\Schemas\Components\Component>
     */
    public static function cycleFields(string $context = 'campaign', ?Product $defaultProduct = null): array
    {
        $productField = $context === 'product' ? 'bill_product_id' : 'product_id';

        return [
            TextInput::make('cycle_number')
                ->label('Cycle #')
                ->numeric()
                ->minValue(1)
                ->required(),
            Select::make($productField)
                ->label('Bill product')
                ->searchable()
                ->preload()
                ->required()
                ->default($defaultProduct?->id)
                ->getSearchResultsUsing(fn (string $search): array => Product::query()
                    ->search($search)
                    ->limit(50)
                    ->pluck('name', 'id')
                    ->all())
                ->getOptionLabelUsing(fn ($value): ?string => Product::query()->find($value)?->name)
                ->live(),
            Select::make('variant_id')
                ->label('Variant')
                ->searchable()
                ->nullable()
                ->options(function ($get) use ($productField): array {
                    $productId = $get($productField);

                    if (blank($productId)) {
                        return [];
                    }

                    return Variant::query()
                        ->where('product_id', $productId)
                        ->pluck('sku', 'id')
                        ->all();
                }),
            Grid::make(2)->schema([
                TextInput::make('price')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$'),
                TextInput::make('shipping_price')
                    ->label('Shipping price')
                    ->numeric()
                    ->minValue(0)
                    ->prefix('$'),
            ]),
            Grid::make(2)->schema([
                TextInput::make('bill_delay_days')
                    ->label('Bill delay (days)')
                    ->numeric()
                    ->minValue(0)
                    ->default(10)
                    ->live(debounce: 300)
                    ->helperText('Used as the fulfillment delay when “Delay fulfillment” is enabled.'),
                TextInput::make('inventory_quantity')
                    ->label('Inventory qty override')
                    ->numeric()
                    ->minValue(0),
            ]),
            Select::make('inventory_rule')
                ->label('Inventory rule')
                ->options(collect(CampaignProductInventoryRule::cases())->mapWithKeys(
                    fn (CampaignProductInventoryRule $rule): array => [$rule->value => str($rule->name)->headline()]
                )->all())
                ->default(CampaignProductInventoryRule::Inherit->value),
            TextInput::make('warehouse')
                ->maxLength(120),
            Grid::make(3)->schema([
                Toggle::make('delay_fulfillment')
                    ->label(fn ($get): string => sprintf(
                        'Delay fulfillment (%d days)',
                        max(0, (int) ($get('bill_delay_days') ?? 10)),
                    ))
                    ->helperText('Fulfillment is held for the bill delay period shown above.')
                    ->default(true)
                    ->live(),
                Toggle::make('shippable')->default(true),
                Toggle::make('subscription_enabled')
                    ->label('Subscription enabled')
                    ->visible(fn ($get): bool => (int) ($get('cycle_number') ?? 1) === 1),
            ]),
            Toggle::make('repeat_forever')
                ->label('Repeat forever')
                ->helperText('Typically enabled for Cycle 3 and later rebills.')
                ->visible(fn ($get): bool => (int) ($get('cycle_number') ?? 1) >= 3),
            Fieldset::make('Coupon override')->schema([
                Toggle::make('coupon_override.enabled')->label('Override coupon'),
                TextInput::make('coupon_override.coupon_code')->label('Coupon code'),
                Select::make('coupon_override.discount_type')
                    ->options([
                        'fixed' => 'Fixed amount',
                        'percent' => 'Percent',
                    ]),
                TextInput::make('coupon_override.discount_value')
                    ->numeric()
                    ->minValue(0),
            ])->columns(2),
        ];
    }
}
