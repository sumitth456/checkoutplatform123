<?php

namespace App\Filament\Admin\Resources\SubscriptionPlans;

use App\Filament\Admin\Resources\SubscriptionPlans\Pages\CreateSubscriptionPlan;
use App\Filament\Admin\Resources\SubscriptionPlans\Pages\EditSubscriptionPlan;
use App\Filament\Admin\Resources\SubscriptionPlans\Pages\ListSubscriptionPlans;
use App\Filament\Admin\Resources\SubscriptionPlans\Schemas\SubscriptionPlanForm;
use App\Filament\Admin\Resources\SubscriptionPlans\Tables\SubscriptionPlansTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\SubscriptionPlan;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SubscriptionPlanResource extends Resource
{
    protected static ?string $model = SubscriptionPlan::class;

    protected static ?string $navigationLabel = 'Subscription Plans';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?int $navigationSort = 30;

    public static function form(Schema $schema): Schema
    {
        return SubscriptionPlanForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SubscriptionPlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSubscriptionPlans::route('/'),
            'create' => CreateSubscriptionPlan::route('/create'),
            'edit' => EditSubscriptionPlan::route('/{record}/edit'),
        ];
    }
}
