<?php

namespace App\Filament\Admin\Resources\ShippingRules;

use App\Filament\Admin\Resources\ShippingRules\Pages\CreateShippingRule;
use App\Filament\Admin\Resources\ShippingRules\Pages\EditShippingRule;
use App\Filament\Admin\Resources\ShippingRules\Pages\ListShippingRules;
use App\Filament\Admin\Resources\ShippingRules\Pages\ViewShippingRule;
use App\Filament\Admin\Resources\ShippingRules\Schemas\ShippingRuleForm;
use App\Filament\Admin\Resources\ShippingRules\Schemas\ShippingRuleInfolist;
use App\Filament\Admin\Resources\ShippingRules\Tables\ShippingRulesTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\ShippingRule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ShippingRuleResource extends Resource
{
    protected static ?string $model = ShippingRule::class;

    protected static ?string $slug = 'shipping-rules';

    protected static ?string $navigationLabel = 'Shipping Rules';

    protected static ?string $modelLabel = 'Shipping rule';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::FULFILLMENT;

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ShippingRuleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ShippingRuleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShippingRulesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShippingRules::route('/'),
            'create' => CreateShippingRule::route('/create'),
            'view' => ViewShippingRule::route('/{record}'),
            'edit' => EditShippingRule::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'uuid'];
    }
}