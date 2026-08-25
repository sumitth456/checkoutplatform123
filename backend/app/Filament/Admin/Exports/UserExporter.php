<?php



namespace App\Filament\Admin\Exports;



use App\Models\User;

use Filament\Actions\Exports\ExportColumn;

use Filament\Actions\Exports\Exporter;

use Filament\Actions\Exports\Models\Export;

use Illuminate\Support\Number;



class UserExporter extends Exporter

{

    protected static ?string $model = User::class;



    public static function getColumns(): array

    {

        return [

            ExportColumn::make('uuid'),

            ExportColumn::make('username'),
            ExportColumn::make('name'),
            ExportColumn::make('first_name'),
            ExportColumn::make('last_name'),

            ExportColumn::make('email'),

            ExportColumn::make('status'),

            ExportColumn::make('roles.name')
                ->label('Role'),
            ExportColumn::make('campaigns.name')
                ->label('Campaigns'),

            ExportColumn::make('last_login_at'),

            ExportColumn::make('created_at'),

        ];

    }



    public static function getCompletedNotificationBody(Export $export): string

    {

        $count = Number::format($export->successful_rows);



        return "Exported {$count} admin users.";

    }

}

