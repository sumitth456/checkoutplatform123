<?php

namespace App\Filament\Admin\Resources\LandingPageTemplates;

use App\Filament\Admin\Resources\LandingPageTemplates\Pages\BuildLandingPageTemplate;
use App\Filament\Admin\Resources\LandingPageTemplates\Pages\CreateLandingPageTemplate;
use App\Filament\Admin\Resources\LandingPageTemplates\Pages\EditLandingPageTemplate;
use App\Filament\Admin\Resources\LandingPageTemplates\Pages\ListLandingPageTemplates;
use App\Filament\Admin\Resources\LandingPageTemplates\Tables\LandingPageTemplatesTable;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\LandingPage;
use App\Models\LandingPageTemplate;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class LandingPageTemplateResource extends Resource
{
    protected static ?string $model = LandingPageTemplate::class;

    protected static ?string $navigationLabel = 'Templates';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?string $navigationParentItem = AdminNavigationParent::LANDING_PAGES;

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function canViewAny(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'landing_pages.view');
    }

    public static function canCreate(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'landing_pages.templates.manage');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255)
                ->live(onBlur: true)
                ->afterStateUpdated(fn ($set, $state, $record) => $record === null ? $set('slug', \Illuminate\Support\Str::slug($state ?? '')) : null)
                ->columnSpanFull(),
            TextInput::make('slug')
                ->required()
                ->unique(ignoreRecord: true)
                ->maxLength(120),
            TextInput::make('category')
                ->maxLength(80)
                ->placeholder('e.g. T-Shirts, Lead gen'),
            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
            Textarea::make('description')
                ->rows(3)
                ->columnSpanFull(),
            Select::make('source_landing_page_id')
                ->label('Source landing page')
                ->helperText('Optional. Copy an existing landing page into this template on create.')
                ->options(fn (): array => LandingPage::query()->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->visibleOn('create')
                ->columnSpanFull(),
            FileUpload::make('preview_image_path')
                ->label('Preview thumbnail')
                ->image()
                ->directory('landing-pages/templates/previews')
                ->visibility('public')
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return LandingPageTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLandingPageTemplates::route('/'),
            'create' => CreateLandingPageTemplate::route('/create'),
            'builder' => BuildLandingPageTemplate::route('/{record}/builder'),
            'edit' => EditLandingPageTemplate::route('/{record}/edit'),
        ];
    }
}
