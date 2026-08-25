<?php

namespace App\Filament\Admin\Resources\LandingPageForms;

use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\LandingPageForm;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LandingPageFormResource extends Resource
{
    protected static ?string $model = LandingPageForm::class;

    protected static ?string $navigationLabel = 'Forms';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?string $navigationParentItem = AdminNavigationParent::LANDING_PAGES;

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    protected static ?string $modelLabel = 'Checkout form';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('landingPage.name')->label('Landing page')->searchable(),
                TextColumn::make('name')->searchable(),
                TextColumn::make('form_key')->label('Form key'),
                IconColumn::make('is_active')->boolean(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->url(fn (LandingPageForm $record): string => \App\Filament\Admin\Resources\LandingPages\LandingPageResource::getUrl('checkout-form', [
                        'record' => $record->landingPage,
                    ])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListLandingPageForms::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
