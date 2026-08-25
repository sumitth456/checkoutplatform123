<?php

namespace App\Filament\Admin\Resources\EmailProviders\Tables;

use App\Models\EmailProvider;
use App\Modules\Email\Enums\EmailProviderStatus;
use App\Modules\Email\Enums\EmailProviderType;
use App\Modules\Email\Services\EmailProviderManager;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ProvidersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('priority')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('provider_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (EmailProviderType|string $state): string => $state instanceof EmailProviderType
                        ? $state->label()
                        : EmailProviderType::from($state)->label())
                    ->sortable(),
                TextColumn::make('from_email')
                    ->label('From')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('priority')
                    ->sortable(),
                TextColumn::make('daily_usage')
                    ->label('Daily usage')
                    ->state(function (EmailProvider $record): string {
                        $limit = $record->daily_limit !== null ? (string) $record->daily_limit : '∞';

                        return "{$record->sent_today} / {$limit}";
                    })
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (EmailProviderStatus|string $state): string => ($state instanceof EmailProviderStatus ? $state : EmailProviderStatus::from($state)) === EmailProviderStatus::Enabled
                        ? 'success'
                        : 'gray')
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                ToggleColumn::make('enabled')
                    ->label('Enabled')
                    ->getStateUsing(fn (EmailProvider $record): bool => $record->status === EmailProviderStatus::Enabled)
                    ->updateStateUsing(function (EmailProvider $record, bool $state): void {
                        $record->update([
                            'status' => $state ? EmailProviderStatus::Enabled : EmailProviderStatus::Disabled,
                        ]);
                    })
                    ->disabled(fn (): bool => ! AdminAuthorization::can(auth()->user(), 'email_providers.manage')),
            ])
            ->filters([
                SelectFilter::make('provider_type')
                    ->label('Type')
                    ->options(collect(EmailProviderType::cases())->mapWithKeys(
                        fn (EmailProviderType $type): array => [$type->value => $type->label()],
                    )->all()),
                SelectFilter::make('status')
                    ->options(collect(EmailProviderStatus::cases())->mapWithKeys(
                        fn (EmailProviderStatus $status): array => [
                            $status->value => str($status->name)->headline(),
                        ],
                    )->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('testConnection')
                    ->label('Test connection')
                    ->icon('heroicon-o-signal')
                    ->authorize(fn (EmailProvider $record): bool => auth()->user()?->can('test', $record) ?? false)
                    ->action(function (EmailProvider $record, EmailProviderManager $manager): void {
                        $result = $manager->testProvider($record);

                        if ($result->success) {
                            Notification::make()
                                ->title('Connection successful')
                                ->success()
                                ->send();

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

                        Notification::make()
                            ->title('Default provider updated')
                            ->success()
                            ->send();
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

                        Notification::make()
                            ->title('Provider cloned')
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
