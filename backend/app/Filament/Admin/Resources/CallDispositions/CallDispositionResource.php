<?php

namespace App\Filament\Admin\Resources\CallDispositions;

use App\Filament\Admin\Resources\CallDispositions\Pages\CreateCallDisposition;
use App\Filament\Admin\Resources\CallDispositions\Pages\EditCallDisposition;
use App\Filament\Admin\Resources\CallDispositions\Pages\ListCallDispositions;
use App\Filament\Admin\Resources\CallDispositions\Pages\ViewCallDisposition;
use App\Filament\Admin\Resources\CallDispositions\Schemas\CallDispositionForm;
use App\Filament\Admin\Resources\CallDispositions\Schemas\CallDispositionInfolist;
use App\Filament\Admin\Resources\CallDispositions\Tables\CallDispositionsTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\CallDisposition;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class CallDispositionResource extends Resource
{
    protected static ?string $model = CallDisposition::class;

    protected static ?string $navigationLabel = 'Dispositions';

    protected static ?string $modelLabel = 'Disposition';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::CRM;

    protected static ?string $navigationParentItem = AdminNavigationParent::CALL_CENTER;

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CallDispositionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CallDispositionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CallDispositionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCallDispositions::route('/'),
            'create' => CreateCallDisposition::route('/create'),
            'view' => ViewCallDisposition::route('/{record}'),
            'edit' => EditCallDisposition::route('/{record}/edit'),
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
        return ['name', 'code'];
    }
}
