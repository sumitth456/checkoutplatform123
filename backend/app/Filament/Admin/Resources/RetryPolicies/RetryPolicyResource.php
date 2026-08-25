<?php

namespace App\Filament\Admin\Resources\RetryPolicies;

use App\Filament\Admin\Resources\RetryPolicies\Pages\CreateRetryPolicy;
use App\Filament\Admin\Resources\RetryPolicies\Pages\EditRetryPolicy;
use App\Filament\Admin\Resources\RetryPolicies\Pages\ListRetryPolicies;
use App\Filament\Admin\Resources\RetryPolicies\Schemas\RetryPolicyForm;
use App\Filament\Admin\Resources\RetryPolicies\Tables\RetryPoliciesTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\RetryPolicy;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RetryPolicyResource extends Resource
{
    protected static ?string $model = RetryPolicy::class;

    protected static ?string $navigationLabel = 'Retry Policies';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?int $navigationSort = 32;

    public static function form(Schema $schema): Schema
    {
        return RetryPolicyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RetryPoliciesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRetryPolicies::route('/'),
            'create' => CreateRetryPolicy::route('/create'),
            'edit' => EditRetryPolicy::route('/{record}/edit'),
        ];
    }
}
