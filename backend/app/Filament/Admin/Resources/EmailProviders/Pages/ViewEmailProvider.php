<?php

namespace App\Filament\Admin\Resources\EmailProviders\Pages;

use App\Filament\Admin\Resources\EmailProviders\EmailProviderResource;
use App\Models\EmailProvider;
use App\Modules\Email\Enums\EmailProviderStatus;
use App\Modules\Email\Enums\EmailProviderType;
use App\Modules\Email\Services\EmailDispatchService;
use App\Modules\Email\Services\EmailProviderManager;
use App\Modules\Email\Support\KlaviyoFlowSetup;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ViewEmailProvider extends ViewRecord
{
    protected static string $resource = EmailProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('klaviyoFlowSetup')
                ->label('Klaviyo flow setup')
                ->icon('heroicon-o-book-open')
                ->visible(fn (EmailProvider $record): bool => $record->provider_type === EmailProviderType::Klaviyo)
                ->modalHeading('Klaviyo transactional flow (Option A)')
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Close')
                ->modalContent(fn (EmailProvider $record): HtmlString => new HtmlString(
                    KlaviyoFlowSetup::instructions(
                        KlaviyoFlowSetup::metricName($record->settings),
                        (string) $record->from_email,
                        (string) $record->from_name,
                    ),
                )),
            Action::make('sendKlaviyoTestEvent')
                ->label('Send test event')
                ->icon('heroicon-o-paper-airplane')
                ->visible(fn (EmailProvider $record): bool => $record->provider_type === EmailProviderType::Klaviyo)
                ->authorize(fn (EmailProvider $record): bool => auth()->user()?->can('test', $record) ?? false)
                ->form([
                    TextInput::make('recipient_email')
                        ->label('Recipient email')
                        ->email()
                        ->required()
                        ->default('sumitthakur8313@gmail.com'),
                    TextInput::make('recipient_name')
                        ->label('Recipient name')
                        ->default('Sumit Thakur'),
                ])
                ->action(function (EmailProvider $record, array $data, EmailDispatchService $dispatch): void {
                    $template = \App\Models\EmailTemplate::query()
                        ->where('slug', 'order-confirmation')
                        ->first();

                    if ($template === null) {
                        Notification::make()
                            ->title('No order-confirmation template found')
                            ->danger()
                            ->send();

                        return;
                    }

                    $item = $dispatch->sendTestEmail(
                        template: $template,
                        recipientEmail: $data['recipient_email'],
                        recipientName: $data['recipient_name'],
                    );

                    $delivery = $item->log?->provider_response['delivery'] ?? null;
                    $note = $item->log?->provider_response['note'] ?? null;

                    if ($item->status->value === 'sent') {
                        Notification::make()
                            ->title('Klaviyo event accepted')
                            ->body(trim(($delivery ? "Delivery mode: {$delivery}. " : '').($note ?? 'Ensure your Klaviyo flow is Live for inbox delivery.')))
                            ->success()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Event failed')
                        ->body($item->last_error ?? 'Unknown error')
                        ->danger()
                        ->send();
                }),
            Action::make('testConnection')
                ->label('Test connection')
                ->icon('heroicon-o-signal')
                ->authorize(fn (EmailProvider $record): bool => auth()->user()?->can('test', $record) ?? false)
                ->action(function (EmailProvider $record, EmailProviderManager $manager): void {
                    $result = $manager->testProvider($record);

                    if ($result->success) {
                        Notification::make()->title('Connection successful')->success()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Connection failed')
                        ->body($result->error ?? 'Unknown error')
                        ->danger()
                        ->send();
                }),
            Action::make('setDefault')
                ->label('Set default')
                ->icon('heroicon-o-star')
                ->visible(fn (EmailProvider $record): bool => ! $record->is_default)
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'email_providers.manage'))
                ->requiresConfirmation()
                ->action(function (EmailProvider $record): void {
                    $record->update(['is_default' => true]);
                    $this->record = $record->fresh();
                    Notification::make()->title('Default provider updated')->success()->send();
                }),
            Action::make('toggleStatus')
                ->label(fn (EmailProvider $record): string => $record->status === EmailProviderStatus::Enabled ? 'Disable' : 'Enable')
                ->icon(fn (EmailProvider $record): string => $record->status === EmailProviderStatus::Enabled ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                ->color(fn (EmailProvider $record): string => $record->status === EmailProviderStatus::Enabled ? 'danger' : 'success')
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'email_providers.manage'))
                ->requiresConfirmation()
                ->action(function (EmailProvider $record): void {
                    $record->update([
                        'status' => $record->status === EmailProviderStatus::Enabled
                            ? EmailProviderStatus::Disabled
                            : EmailProviderStatus::Enabled,
                    ]);
                    $this->record = $record->fresh();
                    Notification::make()->title('Provider status updated')->success()->send();
                }),
            Action::make('clone')
                ->label('Clone')
                ->icon('heroicon-o-document-duplicate')
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'email_providers.manage'))
                ->requiresConfirmation()
                ->action(function (EmailProvider $record): void {
                    $clone = $record->replicate(['uuid', 'is_default', 'sent_today', 'sent_today_date']);
                    $clone->name = $record->name.' (Copy)';
                    $clone->is_default = false;
                    $clone->uuid = (string) Str::uuid();
                    $clone->save();

                    Notification::make()->title('Provider cloned')->success()->send();
                }),
            DeleteAction::make(),
        ];
    }
}
