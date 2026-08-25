<?php

namespace App\Filament\Admin\Resources\EmailQueueItems\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use App\Modules\Email\Enums\EmailQueueStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class EmailQueueItemInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::detailsSection('Queue item', [
                    Grid::make(3)->schema([
                        TextEntry::make('uuid')->copyable(),
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (EmailQueueStatus|string $state): string => match ($state instanceof EmailQueueStatus ? $state->value : $state) {
                                'sent' => 'success',
                                'processing', 'queued' => 'info',
                                'failed', 'dead' => 'danger',
                                'cancelled' => 'gray',
                                'retry' => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('recipient_email')->label('Recipient')->copyable(),
                        TextEntry::make('recipient_name')->label('Recipient name')->placeholder('—'),
                        TextEntry::make('subject')->columnSpanFull(),
                        TextEntry::make('attempts'),
                        TextEntry::make('max_attempts'),
                        TextEntry::make('scheduled_at')->dateTime()->placeholder('—'),
                        TextEntry::make('queued_at')->dateTime()->placeholder('—'),
                        TextEntry::make('processing_at')->dateTime()->placeholder('—'),
                        TextEntry::make('sent_at')->dateTime()->placeholder('—'),
                        TextEntry::make('failed_at')->dateTime()->placeholder('—'),
                        TextEntry::make('template.name')->label('Template')->placeholder('—'),
                        TextEntry::make('provider.name')->label('Provider')->placeholder('—'),
                        TextEntry::make('last_error')->placeholder('—')->columnSpanFull(),
                        TextEntry::make('html_body')->html()->columnSpanFull()->label('HTML body'),
                    ]),
                ]),
            ]);
    }
}
