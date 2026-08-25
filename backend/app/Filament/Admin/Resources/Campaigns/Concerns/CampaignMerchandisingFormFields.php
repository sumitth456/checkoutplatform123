<?php

namespace App\Filament\Admin\Resources\Campaigns\Concerns;

use App\Modules\Campaign\Enums\CampaignDiscountType;
use App\Modules\Campaign\Enums\CampaignOfferPosition;
use App\Modules\Campaign\Enums\CampaignOfferTrigger;
use App\Modules\Campaign\Enums\CampaignOrderBumpDisplayRule;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

final class CampaignMerchandisingFormFields
{
    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function productAndPriority(): array
    {
        return [
            Select::make('product_id')
                ->relationship('product', 'name')
                ->searchable()
                ->preload()
                ->required(),
            TextInput::make('priority')->numeric()->default(100)->required(),
            TextInput::make('sort_order')->numeric()->default(0),
            Toggle::make('is_active')->default(true),
        ];
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function triggerFields(): array
    {
        return [
            Select::make('trigger')
                ->options(collect(CampaignOfferTrigger::cases())->mapWithKeys(
                    fn (CampaignOfferTrigger $t): array => [$t->value => str($t->name)->headline()]
                )->all())
                ->required(),
            KeyValue::make('trigger_config')->label('Trigger config'),
        ];
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function discountFields(): array
    {
        return [
            Select::make('discount_type')
                ->options(collect(CampaignDiscountType::cases())->mapWithKeys(
                    fn (CampaignDiscountType $t): array => [$t->value => str($t->name)->headline()]
                )->all())
                ->required(),
            TextInput::make('discount_value')->numeric()->minValue(0),
        ];
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function upsellPosition(): array
    {
        return [
            Select::make('position')
                ->options(collect(CampaignOfferPosition::cases())->mapWithKeys(
                    fn (CampaignOfferPosition $p): array => [$p->value => str($p->name)->headline()]
                )->all())
                ->required(),
        ];
    }

    /**
     * @return list<\Filament\Forms\Components\Component>
     */
    public static function orderBumpFields(): array
    {
        return [
            TextInput::make('bump_price')->label('Bump price')->numeric()->minValue(0)->prefix('$'),
            Select::make('position')
                ->options(collect(CampaignOfferPosition::cases())->mapWithKeys(
                    fn (CampaignOfferPosition $p): array => [$p->value => str($p->name)->headline()]
                )->all())
                ->required(),
            Select::make('display_rule')
                ->options(collect(CampaignOrderBumpDisplayRule::cases())->mapWithKeys(
                    fn (CampaignOrderBumpDisplayRule $r): array => [$r->value => str($r->name)->headline()]
                )->all())
                ->required(),
            KeyValue::make('display_rule_config')->label('Display rule config'),
        ];
    }
}
