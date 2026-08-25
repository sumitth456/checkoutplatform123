<?php

namespace App\Filament\Admin\Resources\LandingPageTemplates\Tables;

use App\Filament\Admin\Pages\LandingPageManagement\PublishedLandingPages;
use App\Filament\Admin\Resources\LandingPageTemplates\LandingPageTemplateResource;
use App\Models\Campaign;
use App\Models\LandingPage;
use App\Models\LandingPageTemplate;
use App\Modules\LandingPage\Services\LandingPageTemplateService;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LandingPageTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('preview_image_path')
                    ->label('Preview')
                    ->disk('public')
                    ->height(48)
                    ->width(72)
                    ->defaultImageUrl(fn (): string => 'https://ui-avatars.com/api/?name=LP&background=e2e8f0&color=475569'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (LandingPageTemplate $record): ?string => filled($record->description)
                        ? (strlen($record->description) > 60 ? substr($record->description, 0, 60).'…' : $record->description)
                        : null),
                TextColumn::make('category')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('category')
                    ->options(fn (): array => LandingPageTemplate::query()
                        ->whereNotNull('category')
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->url(fn (LandingPageTemplate $record): string => route('landing-page-templates.preview', $record))
                    ->openUrlInNewTab(),
                Action::make('use_template')
                    ->label('Use template')
                    ->icon('heroicon-o-document-plus')
                    ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'landing_pages.create'))
                    ->form([
                        TextInput::make('name')
                            ->label('Landing page name')
                            ->required()
                            ->maxLength(255),
                        Select::make('campaign_id')
                            ->label('Campaign')
                            ->options(fn (): array => Campaign::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (LandingPageTemplate $record, array $data, Action $action): void {
                        $page = app(LandingPageTemplateService::class)->createPageFromTemplate(
                            $record,
                            [
                                'name' => $data['name'],
                                'campaign_id' => $data['campaign_id'],
                            ],
                            auth()->id(),
                        );

                        Notification::make()
                            ->title('Landing page created')
                            ->body('The new page is available in Published Pages.')
                            ->success()
                            ->send();

                        $action->redirect(PublishedLandingPages::getUrl());
                    }),
                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-square-2-stack')
                    ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'landing_pages.templates.manage'))
                    ->requiresConfirmation()
                    ->action(function (LandingPageTemplate $record): void {
                        app(LandingPageTemplateService::class)->duplicate($record, auth()->id());

                        Notification::make()->title('Template duplicated')->success()->send();
                    }),
                EditAction::make()
                    ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'landing_pages.templates.manage')),
                Action::make('update_template')
                    ->label('Update template')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'landing_pages.templates.manage'))
                    ->form([
                        Select::make('landing_page_id')
                            ->label('Source landing page')
                            ->options(fn (LandingPageTemplate $record): array => LandingPage::query()
                                ->when($record->source_landing_page_id, fn (Builder $q) => $q->orderByRaw('id = ? desc', [$record->source_landing_page_id]))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->default(fn (LandingPageTemplate $record): ?int => $record->source_landing_page_id)
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (LandingPageTemplate $record, array $data): void {
                        $page = LandingPage::query()->findOrFail((int) $data['landing_page_id']);

                        app(LandingPageTemplateService::class)->updateFromPage($record, $page->fresh(['forms', 'assets']), auth()->id());

                        Notification::make()
                            ->title('Template updated')
                            ->body('Template content was replaced from “'.$page->name.'”.')
                            ->success()
                            ->send();
                    }),
                DeleteAction::make()
                    ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'landing_pages.templates.manage')),
            ]);
    }
}
