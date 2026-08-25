<?php

namespace Database\Seeders;

use App\Models\Fulfillment;
use App\Models\FulfillmentHouse;
use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingCarrier;
use App\Models\ShippingRule;
use App\Models\Variant;
use App\Models\Warehouse;
use App\Models\WarehouseInventory;
use App\Modules\Fulfillment\Services\FulfillmentOrchestratorService;
use App\Modules\Fulfillment\Services\FulfillmentTimelineService;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\OrderPaymentStatus;
use Illuminate\Database\Seeder;

class FulfillmentDemoSeeder extends Seeder
{
    public function run(): void
    {
        $house = FulfillmentHouse::query()->updateOrCreate(
            ['code' => 'INTERNAL'],
            [
                'name' => 'Internal Fulfillment',
                'provider_type' => 'internal',
                'status' => 'active',
                'priority' => 10,
                'api_type' => 'rest',
                'is_active' => true,
                'auto_sync' => true,
            ],
        );

        $warehouse = Warehouse::query()->updateOrCreate(
            ['code' => 'US-EAST-1'],
            [
                'fulfillment_house_id' => $house->id,
                'name' => 'US East Primary',
                'country_code' => 'US',
                'address_line1' => '100 Warehouse Blvd',
                'city' => 'Newark',
                'state' => 'NJ',
                'postal_code' => '07102',
                'priority' => 10,
                'inventory_sync' => true,
                'is_active' => true,
            ],
        );

        $carrierUps = ShippingCarrier::query()->updateOrCreate(
            ['code' => 'ups'],
            [
                'name' => 'UPS',
                'tracking_url_template' => 'https://www.ups.com/track?tracknum={tracking}',
                'services' => ['ground', '2day', 'next_day'],
                'estimated_delivery_days' => 5,
                'is_active' => true,
            ],
        );

        ShippingCarrier::query()->updateOrCreate(
            ['code' => 'usps'],
            [
                'name' => 'USPS',
                'tracking_url_template' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels={tracking}',
                'services' => ['priority', 'express'],
                'estimated_delivery_days' => 7,
                'is_active' => true,
            ],
        );

        ShippingRule::query()->updateOrCreate(
            ['name' => 'US Standard Shipping'],
            [
                'priority' => 10,
                'country_code' => 'US',
                'warehouse_id' => $warehouse->id,
                'fulfillment_house_id' => $house->id,
                'shipping_carrier_id' => $carrierUps->id,
                'shipping_method' => 'ground',
                'is_active' => true,
            ],
        );

        $variant = Variant::query()->first();
        if ($variant) {
            WarehouseInventory::query()->updateOrCreate(
                [
                    'warehouse_id' => $warehouse->id,
                    'variant_id' => $variant->id,
                ],
                [
                    'available' => 500,
                    'reserved' => 0,
                    'committed' => 0,
                ],
            );
        }

        $orchestrator = app(FulfillmentOrchestratorService::class);
        $timeline = app(FulfillmentTimelineService::class);

        $orders = Order::query()
            ->where('payment_status', OrderPaymentStatus::Paid)
            ->whereDoesntHave('fulfillments')
            ->limit(5)
            ->get();

        foreach ($orders as $order) {
            $fulfillment = $orchestrator->createFromPaidOrder($order->fresh(['items.product']));
            $fulfillment->update([
                'status' => FulfillmentRecordStatus::Pending,
                'fulfillment_house_id' => $house->id,
                'warehouse_id' => $warehouse->id,
                'shipping_carrier_id' => $carrierUps->id,
            ]);
        }

        $shipped = Fulfillment::query()->latest('id')->first();
        if ($shipped) {
            $orchestrator->updateTracking($shipped, '1Z999AA10123456784', 'ups', 'ground');
            $timeline->record($shipped, 'picked', 'Picked', 'Items picked from warehouse');
            $timeline->record($shipped, 'packed', 'Packed', 'Package packed and labeled');
        }
    }
}
