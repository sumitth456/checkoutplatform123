<?php



namespace App\Filament\Admin\Exports;



use App\Models\CheckoutPaymentFailure;

use Filament\Actions\Exports\ExportColumn;

use Filament\Actions\Exports\Exporter;

use Filament\Actions\Exports\Models\Export;

use Illuminate\Support\Number;



class CheckoutPaymentFailureExporter extends Exporter

{

    protected static ?string $model = CheckoutPaymentFailure::class;



    public static function getColumns(): array

    {

        return [

            ExportColumn::make('uuid'),

            ExportColumn::make('created_at'),

            ExportColumn::make('customer_email'),

            ExportColumn::make('amount'),

            ExportColumn::make('currency'),

            ExportColumn::make('provider'),

            ExportColumn::make('failure_code'),

            ExportColumn::make('failure_message'),

            ExportColumn::make('payment_intent_id'),

            ExportColumn::make('ip_address'),

        ];

    }



    public static function getCompletedNotificationBody(Export $export): string

    {

        $count = Number::format($export->successful_rows);



        return "Exported {$count} checkout payment failures.";

    }

}

