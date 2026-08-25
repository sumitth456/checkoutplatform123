<?php

namespace App\Filament\Admin\Resources\PaymentGateways\Pages;

use App\Filament\Admin\Exports\PaymentGatewayExporter;
use App\Filament\Admin\Resources\PaymentGateways\PaymentGatewayResource;
use App\Support\Admin\AdminAuthorization;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListPaymentGateways extends ListRecords
{
    protected static string $resource = PaymentGatewayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->exporter(PaymentGatewayExporter::class)
                ->authorize(fn (): bool => AdminAuthorization::can(auth()->user(), 'payment_gateways.export')),
            CreateAction::make(),
        ];
    }
}
