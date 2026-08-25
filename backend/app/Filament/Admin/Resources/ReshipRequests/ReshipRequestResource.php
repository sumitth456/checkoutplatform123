<?php

namespace App\Filament\Admin\Resources\ReshipRequests;

use App\Filament\Admin\Resources\ReshipRequests\Pages\CreateReshipRequest;
use App\Filament\Admin\Resources\ReshipRequests\Pages\EditReshipRequest;
use App\Filament\Admin\Resources\ReshipRequests\Pages\ListReshipRequests;
use App\Filament\Admin\Resources\ReshipRequests\Pages\ViewReshipRequest;
use App\Filament\Admin\Resources\ReshipRequests\Schemas\ReshipRequestForm;
use App\Filament\Admin\Resources\ReshipRequests\Schemas\ReshipRequestInfolist;
use App\Filament\Admin\Resources\ReshipRequests\Tables\ReshipRequestsTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Models\ReshipRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ReshipRequestResource extends Resource
{
    protected static ?string $model = ReshipRequest::class;

    protected static ?string $slug = 'reship-requests';

    protected static ?string $navigationLabel = 'Reship Requests';

    protected static ?string $modelLabel = 'Reship request';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::FULFILLMENT;

    protected static ?int $navigationSort = 11;

    protected static ?string $recordTitleAttribute = 'uuid';

    public static function form(Schema $schema): Schema
    {
        return ReshipRequestForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ReshipRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ReshipRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReshipRequests::route('/'),
            'create' => CreateReshipRequest::route('/create'),
            'view' => ViewReshipRequest::route('/{record}'),
            'edit' => EditReshipRequest::route('/{record}/edit'),
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
        return ['uuid', 'reason'];
    }
}