<?php

namespace App\Filament\Admin\Resources\ProductImportBatches\Pages;

use App\Filament\Admin\Resources\ProductImportBatches\ProductImportBatchResource;
use App\Modules\Product\Import\Services\ProductImportBatchService;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;

class ViewProductImportBatch extends ViewRecord
{
    protected static string $resource = ProductImportBatchResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Import batch')->schema([
                TextEntry::make('uuid')->copyable(),
                TextEntry::make('user.name')->label('Imported by'),
                TextEntry::make('original_filename'),
                TextEntry::make('status')->badge(),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('started_at')->dateTime()->placeholder('—'),
                TextEntry::make('completed_at')->dateTime()->placeholder('—'),
                TextEntry::make('stats.imported_count')->label('Products imported')->placeholder('—'),
                TextEntry::make('stats.duration_ms')->label('Duration (ms)')->placeholder('—'),
                TextEntry::make('failure_message')->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download_errors')
                ->label('Error report')
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => $this->record->errors()->exists())
                ->action(function (): mixed {
                    $path = app(ProductImportBatchService::class)->generateErrorReportCsv($this->record);

                    return Storage::disk('local')->download($path, basename($path));
                }),
        ];
    }
}
