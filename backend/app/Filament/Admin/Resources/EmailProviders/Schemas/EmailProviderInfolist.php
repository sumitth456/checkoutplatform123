<?php

namespace App\Filament\Admin\Resources\EmailProviders\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Models\EmailProvider;
use App\Modules\Email\Enums\EmailProviderStatus;
use App\Modules\Email\Enums\EmailProviderType;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class EmailProviderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::detailsSection('Provider configuration', [
                    Grid::make(3)->schema([
                        TextEntry::make('name'),
                        TextEntry::make('provider_type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn (EmailProviderType|string $state): string => $state instanceof EmailProviderType
                                ? $state->label()
                                : EmailProviderType::from($state)->label()),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (EmailProviderStatus|string $state): string => ($state instanceof EmailProviderStatus ? $state : EmailProviderStatus::from($state)) === EmailProviderStatus::Enabled
                                ? 'success'
                                : 'gray'),
                        TextEntry::make('priority'),
                        TextEntry::make('from_name')->label('From name'),
                        TextEntry::make('from_email')->label('From email')->copyable(),
                        TextEntry::make('reply_to_name')->label('Reply-to name')->placeholder('—'),
                        TextEntry::make('reply_to_email')->label('Reply-to email')->placeholder('—'),
                        TextEntry::make('rate_limit_per_minute')->label('Rate limit / min')->placeholder('—'),
                        TextEntry::make('daily_limit')->placeholder('—'),
                        TextEntry::make('sent_today')
                            ->label('Sent today')
                            ->state(fn (EmailProvider $record): string => (string) $record->sent_today),
                        IconEntry::make('is_default')->boolean()->label('Default'),
                        TextEntry::make('smtp_host')->label('SMTP host')->placeholder('—')->visible(fn (EmailProvider $record): bool => $record->provider_type === EmailProviderType::Smtp),
                        TextEntry::make('smtp_port')->label('SMTP port')->placeholder('—')->visible(fn (EmailProvider $record): bool => $record->provider_type === EmailProviderType::Smtp),
                        TextEntry::make('smtp_encryption')->label('Encryption')->placeholder('—')->visible(fn (EmailProvider $record): bool => $record->provider_type === EmailProviderType::Smtp),
                        TextEntry::make('username')->placeholder('—')->visible(fn (EmailProvider $record): bool => $record->provider_type === EmailProviderType::Smtp),
                    ]),
                ]),
            ]);
    }
}
