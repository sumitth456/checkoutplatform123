<?php



namespace App\Filament\Admin\Resources\PaymentGateways\Pages;



use App\Filament\Admin\Resources\PaymentGateways\PaymentGatewayResource;

use App\Models\PaymentGatewayConfig;

use Filament\Resources\Pages\EditRecord;



class EditPaymentGateway extends EditRecord

{

    protected static string $resource = PaymentGatewayResource::class;



    protected function mutateFormDataBeforeSave(array $data): array

    {

        $existing = $this->record->credentials ?? [];

        $incoming = $data['credentials'] ?? [];



        $data['credentials'] = array_merge($existing, array_filter($incoming));



        return $data;

    }



    protected function afterSave(): void

    {

        if (! $this->record->is_default) {

            return;

        }



        PaymentGatewayConfig::query()

            ->where('id', '!=', $this->record->id)

            ->update(['is_default' => false]);

    }

}

