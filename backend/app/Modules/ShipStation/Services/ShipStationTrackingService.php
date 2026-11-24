<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\ShipStationIntegrationLog;
use App\Modules\Fulfillment\Services\FulfillmentOrchestratorService;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\ShipStation\Contracts\ShipStationServiceInterface;
use App\Modules\ShipStation\Support\ShipStationStatusMapper;

class ShipStationTrackingService
{
    public function __construct(

        private readonly ShipStationConfigResolver $config,

        private readonly ShipStationServiceInterface $api,

        private readonly FulfillmentOrchestratorService $orchestrator,

        private readonly ShipStationStatusMapper $statusMapper,

        private readonly ShipStationMockFulfillmentService $mockFulfillment,

    ) {}

    public function refreshForOrder(Order $order): Order
    {

        if (! $this->config->isEnabled()) {

            return $order;

        }

        if ($this->config->isMockMode() || $this->config->isRealMode()) {

            return $this->mockFulfillment->getTracking($order->fresh(['shipStationIntegration', 'fulfillments']))['order'];

        }

        if (! filled($order->shipstation_order_id)) {

            return $order;

        }

        $shipments = $this->api->listShipments([

            'orderId' => (int) $order->shipstation_order_id,

        ]);

        foreach ($shipments as $shipment) {

            $this->applyShipmentToFulfillments($order, $shipment);

        }

        ShipStationIntegrationLog::query()->create([

            'request_type' => 'refresh_tracking',

            'order_id' => $order->id,

            'shipstation_order_id' => (int) $order->shipstation_order_id,

            'success' => true,

            'context' => ['shipment_count' => count($shipments)],

        ]);

        return $order->fresh();

    }

    public function refreshForFulfillment(Fulfillment $fulfillment): Fulfillment
    {

        $order = $fulfillment->order;

        if ($order === null) {

            return $fulfillment;

        }

        $this->refreshForOrder($order);

        return $fulfillment->fresh();

    }

    /**
     * @param  array<string, mixed>  $shipment
     */
    private function applyShipmentToFulfillments(Order $order, array $shipment): void
    {

        $trackingNumber = (string) ($shipment['trackingNumber'] ?? '');

        $carrierCode = (string) ($shipment['carrierCode'] ?? '');

        $serviceCode = (string) ($shipment['serviceCode'] ?? '');

        $shipmentId = (int) ($shipment['shipmentId'] ?? 0);

        $shipStatus = (string) ($shipment['shipStatus'] ?? '');

        $fulfillments = $order->fulfillments()->get();

        foreach ($fulfillments as $fulfillment) {

            $metadata = $fulfillment->metadata ?? [];

            $existingShipmentId = (int) ($metadata['shipstation']['shipment_id'] ?? 0);

            if ($shipmentId > 0 && $existingShipmentId > 0 && $existingShipmentId !== $shipmentId) {

                continue;

            }

            if ($trackingNumber !== '') {

                $this->orchestrator->updateTracking(

                    $fulfillment,

                    $trackingNumber,

                    $carrierCode ?: $fulfillment->carrier,

                    $serviceCode ?: $fulfillment->shipping_method,

                );

            }

            $mappedStatus = $this->statusMapper->mapShipmentStatus($shipStatus);

            if ($mappedStatus !== null) {

                $fulfillment->update(['status' => $mappedStatus]);

                if ($mappedStatus === FulfillmentRecordStatus::Delivered) {

                    $this->orchestrator->markDelivered($fulfillment->fresh());

                }

            }

            $metadata['shipstation'] = array_merge($metadata['shipstation'] ?? [], [

                'shipment_id' => $shipmentId ?: ($metadata['shipstation']['shipment_id'] ?? null),

                'ship_status' => $shipStatus,

                'tracking_url' => $shipment['trackingUrl'] ?? null,

                'last_tracking_sync_at' => now()->toIso8601String(),

            ]);

            $fulfillment->update([

                'metadata' => $metadata,

                'tracking_url' => $shipment['trackingUrl'] ?? $fulfillment->tracking_url,

            ]);

        }

    }
}
