<?php



namespace App\Filament\Admin\Resources\LandingPageAssets;



use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;

use App\Models\LandingPageAsset;

use App\Modules\LandingPage\Enums\LandingPageAssetType;

use App\Modules\LandingPage\Services\LandingPageAssetService;

use BackedEnum;

use Filament\Actions\Action;

use Filament\Actions\DeleteAction;

use Filament\Actions\EditAction;

use Filament\Forms\Components\FileUpload;

use Filament\Forms\Components\Select;

use Filament\Forms\Components\TextInput;

use Filament\Forms\Components\Toggle;

use Filament\Notifications\Notification;

use Filament\Resources\Resource;

use Filament\Schemas\Components\Utilities\Get;

use Filament\Schemas\Schema;

use Filament\Support\Icons\Heroicon;

use Filament\Tables\Columns\IconColumn;

use Filament\Tables\Columns\ImageColumn;

use Filament\Tables\Columns\TextColumn;

use Filament\Tables\Filters\SelectFilter;

use Filament\Tables\Filters\TernaryFilter;

use Filament\Tables\Table;

use Illuminate\Support\HtmlString;

use UnitEnum;



class LandingPageAssetResource extends Resource

{

    protected static ?string $model = LandingPageAsset::class;



    protected static ?string $navigationLabel = 'Assets';



    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?string $navigationParentItem = AdminNavigationParent::LANDING_PAGES;

    protected static ?int $navigationSort = 3;



    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $modelLabel = 'Asset';



    public static function form(Schema $schema): Schema

    {

        return $schema->components([

            Select::make('landing_page_id')

                ->relationship('landingPage', 'name')

                ->searchable()

                ->nullable()

                ->helperText('Leave empty for global library assets shared across pages.'),

            Select::make('type')

                ->options(collect(LandingPageAssetType::cases())->mapWithKeys(

                    fn (LandingPageAssetType $t): array => [$t->value => $t->label()]

                )->all())

                ->required()

                ->live()

                ->afterStateUpdated(function ($state, callable $set): void {

                    if ($state instanceof LandingPageAssetType) {

                        $set('is_protected', $state->protectedByDefault());

                    } elseif (is_string($state) && $state !== '') {

                        $set('is_protected', LandingPageAssetType::from($state)->protectedByDefault());

                    }

                }),

            Toggle::make('is_protected')

                ->label('Hotlink protection')

                ->helperText('Protected files are served from private storage and require a signed URL or same-site referer.'),

            FileUpload::make('path')

                ->label('File')

                ->required()

                ->directory(fn (Get $get): string => 'landing-pages/assets/'.($get('type') ?: 'image'))

                ->disk(fn (Get $get): string => ($get('is_protected') ?? false)

                    ? (string) config('landing_pages.assets.protected_disk', 'local')

                    : (string) config('landing_pages.assets.public_disk', 'public'))

                ->visibility(fn (Get $get): string => ($get('is_protected') ?? false) ? 'private' : 'public')

                ->acceptedFileTypes(fn (Get $get): array => self::mimeTypesForFormType($get('type')))

                ->maxSize(fn (Get $get): int => self::maxSizeKbForFormType($get('type')))

                ->storeFileNamesIn('filename'),

            TextInput::make('alt_text')

                ->maxLength(255)

                ->visible(fn (Get $get): bool => in_array($get('type'), ['image', 'svg'], true)),

        ]);

    }



    public static function table(Table $table): Table

    {

        return $table

            ->columns([

                ImageColumn::make('path')
                    ->label('Preview')
                    ->disk(fn (LandingPageAsset $record): string => $record->disk)
                    ->visibility(fn (LandingPageAsset $record): string => $record->is_protected ? 'private' : 'public')
                    ->checkFileExistence(false)
                    ->toggleable(),

                TextColumn::make('filename')->searchable()->limit(40),

                TextColumn::make('type')->badge(),

                IconColumn::make('is_protected')->label('Protected')->boolean(),

                TextColumn::make('landingPage.name')->label('Page')->placeholder('Global'),

                TextColumn::make('size_bytes')

                    ->label('Size')

                    ->formatStateUsing(fn (int $state): string => app(LandingPageAssetService::class)->formatBytes($state)),

                TextColumn::make('embedUrl')

                    ->label('URL')

                    ->state(fn (LandingPageAsset $record): ?string => $record->embedUrl())

                    ->copyable()

                    ->limit(48)

                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')->dateTime()->sortable(),

            ])

            ->filters([

                SelectFilter::make('type')

                    ->options(collect(LandingPageAssetType::cases())->mapWithKeys(

                        fn (LandingPageAssetType $t): array => [$t->value => $t->label()]

                    )->all()),

                TernaryFilter::make('is_protected')->label('Protected'),

                SelectFilter::make('landing_page_id')

                    ->relationship('landingPage', 'name')

                    ->label('Landing page'),

            ])

            ->recordActions([

                Action::make('copySnippet')

                    ->label('Copy HTML')

                    ->icon('heroicon-o-code-bracket')

                    ->action(function (LandingPageAsset $record): void {

                        $snippet = app(LandingPageAssetService::class)->embedSnippet($record);



                        Notification::make()

                            ->title('Embed snippet')

                            ->body(new HtmlString('<pre class="text-xs whitespace-pre-wrap">'.e($snippet).'</pre>'))

                            ->persistent()

                            ->send();

                    }),

                EditAction::make(),

                DeleteAction::make(),

            ]);

    }



    public static function getPages(): array

    {

        return [

            'index' => Pages\ListLandingPageAssets::route('/'),

            'create' => Pages\CreateLandingPageAsset::route('/create'),

            'edit' => Pages\EditLandingPageAsset::route('/{record}/edit'),

        ];

    }



    /**

     * @return list<string>

     */

    private static function mimeTypesForFormType(mixed $type): array

    {

        if ($type instanceof LandingPageAssetType) {

            return $type->allowedMimeTypes();

        }



        if (is_string($type) && $type !== '') {

            return LandingPageAssetType::from($type)->allowedMimeTypes();

        }



        return LandingPageAssetType::Image->allowedMimeTypes();

    }



    private static function maxSizeKbForFormType(mixed $type): int

    {

        $enum = $type instanceof LandingPageAssetType

            ? $type

            : (is_string($type) && $type !== '' ? LandingPageAssetType::from($type) : LandingPageAssetType::Image);



        return (int) ceil($enum->maxSizeBytes() / 1024);

    }

}

