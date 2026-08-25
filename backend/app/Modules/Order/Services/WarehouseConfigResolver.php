<?php



namespace App\Modules\Order\Services;



use App\Models\WarehouseSetting;



class WarehouseConfigResolver

{

    /**

     * @return array{

     *     base_url: string,

     *     api_key: string,

     *     timeout_seconds: int,

     *     create_shipment_path: string

     * }

     */

    public function resolve(): array

    {

        $setting = WarehouseSetting::query()->where('is_active', true)->first();



        if ($setting === null) {

            $config = config('fulfillment.warehouse');



            return [

                'base_url' => (string) ($config['base_url'] ?? ''),

                'api_key' => (string) ($config['api_key'] ?? ''),

                'timeout_seconds' => (int) ($config['timeout_seconds'] ?? 30),

                'create_shipment_path' => (string) ($config['create_shipment_path'] ?? '/v1/shipments'),

            ];

        }



        return [

            'base_url' => $setting->base_url,

            'api_key' => (string) $setting->api_key,

            'timeout_seconds' => (int) $setting->timeout_seconds,

            'create_shipment_path' => $setting->create_shipment_path,

        ];

    }

}

