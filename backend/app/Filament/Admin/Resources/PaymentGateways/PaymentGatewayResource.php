<?php

namespace App\Filament\Admin\Resources\PaymentGateways;

use App\Filament\Admin\Resources\PaymentGateways\Pages\CreatePaymentGateway;
use App\Filament\Admin\Resources\PaymentGateways\Pages\EditPaymentGateway;
use App\Filament\Admin\Resources\PaymentGateways\Pages\ListPaymentGateways;
use App\Filament\Admin\Resources\PaymentGateways\Pages\ViewPaymentGateway;
use App\Filament\Admin\Resources\PaymentGateways\RelationManagers\ActivityTimelineRelationManager;
use App\Filament\Admin\Resources\PaymentGateways\Schemas\PaymentGatewayForm;
use App\Filament\Admin\Resources\PaymentGateways\Schemas\PaymentGatewayInfolist;
use App\Filament\Admin\Resources\PaymentGateways\Tables\PaymentGatewaysTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\PaymentGatewayConfig;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class PaymentGatewayResource extends Resource
{
    protected static ?string $model = PaymentGatewayConfig::class;

    protected static ?string $navigationLabel = 'Payment Gateways';

    protected static ?string $modelLabel = 'Payment Gateway';

    protected static ?string $pluralModelLabel = 'Payment Gateways';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::GATEWAYS;

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PaymentGatewayForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentGatewayInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentGatewaysTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ActivityTimelineRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentGateways::route('/'),
            'create' => CreatePaymentGateway::route('/create'),
            'view' => ViewPaymentGateway::route('/{record}'),
            'edit' => EditPaymentGateway::route('/{record}/edit'),
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
        return ['name', 'slug', 'provider'];
    }
}
