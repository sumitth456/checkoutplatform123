<?php

namespace App\Filament\Admin\Resources\EmailTemplates\Tables;

use App\Models\EmailTemplate;
use App\Modules\Email\Services\EmailDispatchService;
use App\Modules\Email\Services\EmailTemplateRenderer;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class TemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->copyable()
                    ->toggleable(),
                TextColumn::make('category')
                    ->badge()
                    ->sortable(),
                TextColumn::make('trigger_event')
                    ->label('Trigger')
                    ->formatStateUsing(fn (?string $state): string => $state ? str($state)->replace('_', ' ')->title() : '—')
                    ->toggleable(),
                TextColumn::make('subject')
                    ->searchable()
                    ->limit(40),
                TextColumn::make('version')
                    ->sortable()
                    ->toggleable(),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->disabled(fn (): bool => ! AdminAuthorization::can(auth()->user(), 'email_templates.manage')),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(fn (): array => EmailTemplate::query()
                        ->whereNotNull('category')
                        ->distinct()
                        ->orderBy('category')
                        ->pluck('category', 'category')
                        ->all()),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('Template preview')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(function (EmailTemplate $record, EmailTemplateRenderer $renderer): HtmlString {
                        $rendered = $renderer->render($record);

                        return new HtmlString(
                            '<div class="space-y-4">'
                            .'<p><strong>Subject:</strong> '.e($rendered['subject']).'</p>'
                            .'<div class="prose max-w-none">'.$rendered['html_body'].'</div>'
                            .'</div>'
                        );
                    }),
                Action::make('sendTest')
                    ->label('Send test')
                    ->icon('heroicon-o-paper-airplane')
                    ->authorize(fn (EmailTemplate $record): bool => auth()->user()?->can('sendTest', $record) ?? false)
                    ->form([
                        TextInput::make('recipient_email')
                            ->label('Recipient email')
                            ->email()
                            ->required()
                            ->default(fn (): ?string => auth()->user()?->email),
                    ])
                    ->action(function (EmailTemplate $record, array $data, EmailDispatchService $dispatch): void {
                        $dispatch->sendTestEmail(
                            template: $record,
                            recipientEmail: $data['recipient_email'],
                        );

                        Notification::make()
                            ->title('Test email sent')
                            ->success()
                            ->send();
                    }),
                Action::make('duplicate')
                    ->label('Duplicate')
                    ->icon('heroicon-o-document-duplicate')
                    ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'email_templates.manage'))
                    ->requiresConfirmation()
                    ->action(function (EmailTemplate $record): void {
                        $copy = $record->replicate(['uuid', 'slug']);
                        $copy->slug = $record->slug.'-copy-'.Str::lower(Str::random(4));
                        $copy->name = $record->name.' (Copy)';
                        $copy->save();

                        Notification::make()
                            ->title('Template duplicated')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
