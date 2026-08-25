<?php

namespace App\Filament\Admin\Resources\EmailTemplates\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class EmailTemplateInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                AdminInfolistComponents::summaryHeader(),
                AdminInfolistComponents::detailsSection('Template details', [
                    Grid::make(3)->schema([
                        TextEntry::make('name'),
                        TextEntry::make('slug')->copyable(),
                        TextEntry::make('category')->placeholder('—'),
                        TextEntry::make('trigger_event')
                            ->label('Trigger event')
                            ->formatStateUsing(fn (?string $state): string => $state ? str($state)->replace('_', ' ')->title() : '—'),
                        TextEntry::make('version'),
                        IconEntry::make('is_active')->boolean()->label('Active'),
                        TextEntry::make('subject')->columnSpanFull(),
                        TextEntry::make('html_body')
                            ->label('HTML body')
                            ->html()
                            ->columnSpanFull(),
                        TextEntry::make('plain_text_body')
                            ->label('Plain text body')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
                ]),
            ]);
    }
}
