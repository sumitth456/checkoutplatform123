<?php



namespace App\Filament\Admin\Resources\PaymentGateways\Pages;



use App\Filament\Admin\Resources\PaymentGateways\PaymentGatewayResource;

use App\Models\PaymentGatewayConfig;

use App\Modules\Payment\Enums\GatewayWebhookStatus;

use Filament\Resources\Pages\CreateRecord;



class CreatePaymentGateway extends CreateRecord

{

    protected static string $resource = PaymentGatewayResource::class;



    protected function mutateFormDataBeforeCreate(array $data): array

    {

        $data['webhook_status'] = GatewayWebhookStatus::Unknown->value;

        $data['credentials'] = array_filter($data['credentials'] ?? []);



        return $data;

    }



    protected function afterCreate(): void

    {

        $this->ensureSingleDefaultGateway();

    }



    private function ensureSingleDefaultGateway(): void

    {

        if (! $this->record->is_default) {

            return;

        }



        PaymentGatewayConfig::query()

            ->where('id', '!=', $this->record->id)

            ->update(['is_default' => false]);

    }

}

