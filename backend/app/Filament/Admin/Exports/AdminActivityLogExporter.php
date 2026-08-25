<?php



namespace App\Filament\Admin\Exports;



use App\Models\AdminActivityLog;

use Filament\Actions\Exports\ExportColumn;

use Filament\Actions\Exports\Exporter;

use Filament\Actions\Exports\Models\Export;

use Illuminate\Support\Number;



class AdminActivityLogExporter extends Exporter

{

    protected static ?string $model = AdminActivityLog::class;



    public static function getColumns(): array

    {

        return [

            ExportColumn::make('uuid'),

            ExportColumn::make('created_at'),

            ExportColumn::make('module'),

            ExportColumn::make('event'),

            ExportColumn::make('description'),

            ExportColumn::make('causer.email')

                ->label('User email'),

            ExportColumn::make('subject_type'),

            ExportColumn::make('subject_id'),

        ];

    }



    public static function getCompletedNotificationBody(Export $export): string

    {

        $count = Number::format($export->successful_rows);



        return "Exported {$count} admin activity log entries.";

    }

}

