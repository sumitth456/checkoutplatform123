<?php

namespace App\Filament\Admin\Resources\EmailLogs\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Modules\Email\Enums\EmailLogStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class EmailLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::detailsSection('Delivery log', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->copyable(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (EmailLogStatus|string $state): string => match ($state instanceof EmailLogStatus ? $state->value : $state) {
                                'delivered', 'sent' => 'success',
                                'opened', 'clicked' => 'info',
                                'failed', 'bounced', 'spam' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('recipient_email')->label('Recipient')->copyable(),
                        TextEntry::make('recipient_name')->label('Recipient name')->placeholder('—'),
                        TextEntry::make('subject')->columnSpanFull(),
                        TextEntry::make('template.name')->label('Template')->placeholder('—'),
                        TextEntry::make('provider.name')->label('Provider')->placeholder('—'),
                        TextEntry::make('retry_count'),
                        TextEntry::make('queued_at')->dateTime()->placeholder('—'),
                        TextEntry::make('sent_at')->dateTime()->placeholder('—'),
                        TextEntry::make('delivered_at')->dateTime()->placeholder('—'),
                        TextEntry::make('opened_at')->dateTime()->placeholder('—'),
                        TextEntry::make('clicked_at')->dateTime()->placeholder('—'),
                        TextEntry::make('bounced_at')->dateTime()->placeholder('—'),
                        TextEntry::make('spam_at')->dateTime()->placeholder('—'),
                        TextEntry::make('unsubscribed_at')->dateTime()->placeholder('—'),
                        TextEntry::make('provider_response')
                            ->label('Provider response')
                            ->formatStateUsing(fn ($state): string => is_array($state) ? json_encode($state, JSON_PRETTY_PRINT) : (string) $state)
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('html_body')->html()->columnSpanFull()->label('HTML body'),
                        TextEntry::make('raw_email')->placeholder('—')->columnSpanFull(),
                    ]),
                ]),
            ]);
    }
}
