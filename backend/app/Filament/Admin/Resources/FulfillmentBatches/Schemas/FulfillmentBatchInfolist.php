<?php

namespace App\Filament\Admin\Resources\FulfillmentBatches\Schemas;

use App\Filament\Admin\Support\Infolists\AdminInfolistComponents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;

class FulfillmentBatchInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            AdminInfolistComponents::summaryHeader(),
            AdminInfolistComponents::activityTimeline('Batch timeline'),
            AdminInfolistComponents::detailsSection('Batch details', [
                Grid::make(3)->schema([
                    TextEntry::make('uuid')->copyable(),
                    TextEntry::make('name'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('warehouse.name')->label('Warehouse')->placeholder('—'),
                    TextEntry::make('record_count')->label('Records'),
                    TextEntry::make('scheduled_at')->dateTime()->placeholder('—'),
                    TextEntry::make('processed_at')->dateTime()->placeholder('—'),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('updated_at')->dateTime(),
                ]),
            ]),
        ]);
    }
}
