<?php

namespace App\Filament\Admin\Pages\LandingPageManagement;

use App\Filament\Admin\Resources\LandingPages\LandingPageResource;
use App\Filament\Admin\Support\AdminNavigationGroup;
use App\Filament\Admin\Support\AdminNavigationParent;
use App\Models\LandingPage;
use App\Modules\LandingPage\Enums\LandingPageStatus;
use App\Modules\LandingPage\Services\LandingPageService;
use App\Support\Admin\AdminAuthorization;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use InvalidArgumentException;
use UnitEnum;

class PublishedLandingPages extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::ADMIN;

    protected static ?string $navigationParentItem = AdminNavigationParent::LANDING_PAGES;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Published Pages';

    protected static ?string $title = 'Published Pages';

    protected static ?string $slug = 'landing-pages/published';

    public static function canAccess(): bool
    {
        return AdminAuthorization::can(auth()->user(), 'landing_pages.view');
    }

    public static function getNavigationUrl(): string
    {
        return static::getUrl();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            EmbeddedTable::make(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => LandingPage::query()->with(['campaign', 'template']))
            ->columns([
                TextColumn::make('name')
                    ->label('Landing page')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug / URL')
                    ->description(fn (LandingPage $record): string => $record->publicUrl())
                    ->searchable()
                    ->copyable(),
                TextColumn::make('campaign.name')
                    ->label('Campaign')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('template.category')
                    ->label('Category')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('template.name')
                    ->label('Template used')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
                TextColumn::make('view_count')
                    ->label('Views')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        LandingPageStatus::Draft->value => 'Draft',
                        LandingPageStatus::Published->value => 'Published',
                        LandingPageStatus::Archived->value => 'Archived',
                    ]),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-paint-brush')
                    ->url(fn (LandingPage $record): string => LandingPageResource::getUrl('builder', ['record' => $record]))
                    ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'landing_pages.update')),
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->url(fn (LandingPage $record): string => route('landing-pages.preview', $record))
                    ->openUrlInNewTab(),
                Action::make('open_live')
                    ->label('Open published page')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (LandingPage $record): string => $record->publicUrl())
                    ->openUrlInNewTab()
                    ->visible(fn (LandingPage $record): bool => $record->status === LandingPageStatus::Published),
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (LandingPage $record): bool => $record->status !== LandingPageStatus::Published
                        && AdminAuthorization::can(auth()->user(), 'landing_pages.update'))
                    ->action(function (LandingPage $record): void {
                        try {
                            app(LandingPageService::class)->publish($record->fresh(), auth()->id());

                            Notification::make()->title('Landing page published')->success()->send();
                        } catch (InvalidArgumentException $exception) {
                            Notification::make()->title($exception->getMessage())->danger()->send();
                        }
                    }),
                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-square-2-stack')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'landing_pages.create'))
                    ->action(function (LandingPage $record): void {
                        app(LandingPageService::class)->duplicate($record, auth()->id());

                        Notification::make()->title('Landing page duplicated')->success()->send();
                    }),
                Action::make('unpublish')
                    ->label('Unpublish')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (LandingPage $record): bool => $record->status === LandingPageStatus::Published
                        && AdminAuthorization::can(auth()->user(), 'landing_pages.update'))
                    ->action(function (LandingPage $record): void {
                        app(LandingPageService::class)->unpublish($record, auth()->id());

                        Notification::make()->title('Landing page unpublished')->success()->send();
                    }),
                DeleteAction::make()
                    ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'landing_pages.delete')),
            ])
            ->recordUrl(fn (LandingPage $record): string => LandingPageResource::getUrl('builder', ['record' => $record]));
    }
}
