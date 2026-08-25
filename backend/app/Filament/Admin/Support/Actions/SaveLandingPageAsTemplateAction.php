<?php

namespace App\Filament\Admin\Support\Actions;

use App\Models\LandingPage;
use App\Modules\LandingPage\Services\LandingPageTemplateService;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;

final class SaveLandingPageAsTemplateAction
{
    public static function make(): Action
    {
        return Action::make('save_as_template')
            ->label('Save as template')
            ->icon('heroicon-o-bookmark')
            ->visible(fn (): bool => AdminAuthorization::can(auth()->user(), 'landing_pages.templates.manage'))
            ->form([
                TextInput::make('name')
                    ->label('Template name')
                    ->required()
                    ->maxLength(120),
                TextInput::make('category')
                    ->label('Category')
                    ->maxLength(80)
                    ->placeholder('e.g. Sales, Lead gen, Webinar'),
                TextInput::make('description')
                    ->label('Description')
                    ->maxLength(255),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ])
            ->action(function (LandingPage $record, array $data, Action $action): void {
                $template = app(LandingPageTemplateService::class)->createFromPage(
                    $record->fresh(['forms', 'assets']),
                    (string) $data['name'],
                    [
                        'category' => $data['category'] ?? null,
                        'description' => $data['description'] ?? null,
                        'is_active' => (bool) ($data['is_active'] ?? true),
                        'preview_image_path' => $record->og_image_path,
                    ],
                    auth()->id(),
                );

                Notification::make()
                    ->title('Template saved')
                    ->body('“'.$template->name.'” is available in Templates.')
                    ->success()
                    ->send();

                $action->redirect(\App\Filament\Admin\Resources\LandingPageTemplates\LandingPageTemplateResource::getUrl('builder', [
                    'record' => $template,
                ]));
            });
    }
}
