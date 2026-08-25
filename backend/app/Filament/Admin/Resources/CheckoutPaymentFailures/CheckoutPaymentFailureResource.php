<?php



namespace App\Filament\Admin\Resources\CheckoutPaymentFailures;



use App\Filament\Admin\Resources\CheckoutPaymentFailures\Pages\ListCheckoutPaymentFailures;

use App\Filament\Admin\Resources\CheckoutPaymentFailures\Pages\ViewCheckoutPaymentFailure;

use App\Filament\Admin\Resources\CheckoutPaymentFailures\Schemas\CheckoutPaymentFailureInfolist;

use App\Filament\Admin\Resources\CheckoutPaymentFailures\Tables\CheckoutPaymentFailuresTable;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\CheckoutPaymentFailure;

use BackedEnum;

use Filament\Resources\Resource;

use Filament\Schemas\Schema;

use Filament\Support\Icons\Heroicon;

use Filament\Tables\Table;

use Illuminate\Database\Eloquent\Model;

use UnitEnum;



class CheckoutPaymentFailureResource extends Resource

{

    protected static ?string $model = CheckoutPaymentFailure::class;



    protected static ?string $navigationLabel = 'Payment failures';



    protected static ?string $modelLabel = 'Payment failure';



    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;



    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;



    protected static ?int $navigationSort = 61;



    protected static ?string $recordTitleAttribute = 'uuid';



    public static function infolist(Schema $schema): Schema

    {

        return CheckoutPaymentFailureInfolist::configure($schema);

    }



    public static function table(Table $table): Table

    {

        return CheckoutPaymentFailuresTable::configure($table);

    }



    public static function getPages(): array

    {

        return [

            'index' => ListCheckoutPaymentFailures::route('/'),

            'view' => ViewCheckoutPaymentFailure::route('/{record}'),

        ];

    }



    public static function canCreate(): bool

    {

        return false;

    }



    public static function canEdit(Model $record): bool

    {

        return false;

    }



    public static function canDelete(Model $record): bool

    {

        return false;

    }



    public static function getGloballySearchableAttributes(): array

    {

        return ['uuid', 'customer_email', 'payment_intent_id', 'failure_code'];

    }

}

