<?php



namespace App\Filament\Admin\Resources\StripeWebhookEvents;



use App\Filament\Admin\Resources\StripeWebhookEvents\Pages\ListStripeWebhookEvents;

use App\Filament\Admin\Resources\StripeWebhookEvents\Pages\ViewStripeWebhookEvent;

use App\Filament\Admin\Resources\StripeWebhookEvents\Schemas\StripeWebhookEventInfolist;

use App\Filament\Admin\Resources\StripeWebhookEvents\Tables\StripeWebhookEventsTable;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\StripeWebhookEvent;

use BackedEnum;

use Filament\Resources\Resource;

use Filament\Schemas\Schema;

use Filament\Support\Icons\Heroicon;

use Filament\Tables\Table;

use Illuminate\Database\Eloquent\Model;

use UnitEnum;



class StripeWebhookEventResource extends Resource

{

    protected static ?string $model = StripeWebhookEvent::class;



    protected static ?string $navigationLabel = 'Webhook event log';



    protected static ?string $modelLabel = 'Webhook event';



    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSignal;



    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;



    protected static ?int $navigationSort = 62;



    protected static ?string $recordTitleAttribute = 'uuid';



    public static function infolist(Schema $schema): Schema

    {

        return StripeWebhookEventInfolist::configure($schema);

    }



    public static function table(Table $table): Table

    {

        return StripeWebhookEventsTable::configure($table);

    }



    public static function getPages(): array

    {

        return [

            'index' => ListStripeWebhookEvents::route('/'),

            'view' => ViewStripeWebhookEvent::route('/{record}'),

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

        return ['uuid', 'stripe_event_id', 'event_type'];

    }

}

