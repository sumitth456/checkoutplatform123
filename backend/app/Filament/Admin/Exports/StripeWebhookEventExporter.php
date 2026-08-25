<?php



namespace App\Filament\Admin\Exports;



use App\Models\StripeWebhookEvent;

use Filament\Actions\Exports\ExportColumn;

use Filament\Actions\Exports\Exporter;

use Filament\Actions\Exports\Models\Export;

use Illuminate\Support\Number;



class StripeWebhookEventExporter extends Exporter

{

    protected static ?string $model = StripeWebhookEvent::class;



    public static function getColumns(): array

    {

        return [

            ExportColumn::make('uuid'),

            ExportColumn::make('stripe_event_id'),

            ExportColumn::make('event_type'),

            ExportColumn::make('status'),

            ExportColumn::make('attempt_count'),

            ExportColumn::make('received_at'),

            ExportColumn::make('processed_at'),

            ExportColumn::make('last_error'),

        ];

    }



    public static function getCompletedNotificationBody(Export $export): string

    {

        $count = Number::format($export->successful_rows);



        return "Exported {$count} Stripe webhook events.";

    }

}

