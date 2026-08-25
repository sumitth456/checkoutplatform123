<?php

namespace App\Filament\Admin\Resources\EmailTemplates\Pages;

use App\Filament\Admin\Resources\EmailTemplates\EmailTemplateResource;
use App\Models\EmailTemplate;
use App\Modules\Email\Services\EmailDispatchService;
use App\Modules\Email\Services\EmailTemplateRenderer;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ViewEmailTemplate extends ViewRecord
{
    protected static string $resource = EmailTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
                        .'<div class="prose max-w-none">'.$rendered['html'].'</div>'
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
                    $dispatch->queueTemplate(
                        template: $record,
                        recipientEmail: $data['recipient_email'],
                    );

                    Notification::make()->title('Test email queued')->success()->send();
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

                    Notification::make()->title('Template duplicated')->success()->send();
                }),
            DeleteAction::make(),
        ];
    }
}
