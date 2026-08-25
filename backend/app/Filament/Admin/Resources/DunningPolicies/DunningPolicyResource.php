<?php

namespace App\Filament\Admin\Resources\DunningPolicies;

use App\Filament\Admin\Resources\DunningPolicies\Pages\CreateDunningPolicy;
use App\Filament\Admin\Resources\DunningPolicies\Pages\EditDunningPolicy;
use App\Filament\Admin\Resources\DunningPolicies\Pages\ListDunningPolicies;
use App\Filament\Admin\Resources\DunningPolicies\Schemas\DunningPolicyForm;
use App\Filament\Admin\Resources\DunningPolicies\Tables\DunningPoliciesTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\DunningPolicy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DunningPolicyResource extends Resource
{
    protected static ?string $model = DunningPolicy::class;

    protected static ?string $navigationLabel = 'Dunning Policies';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?int $navigationSort = 34;

    public static function form(Schema $schema): Schema
    {
        return DunningPolicyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DunningPoliciesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDunningPolicies::route('/'),
            'create' => CreateDunningPolicy::route('/create'),
            'edit' => EditDunningPolicy::route('/{record}/edit'),
        ];
    }
}
