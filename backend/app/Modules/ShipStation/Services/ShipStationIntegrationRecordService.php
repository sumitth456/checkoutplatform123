<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Order;
use App\Models\ShippingCarrier;
use App\Models\ShipStationIntegration;
use App\Modules\ShipStation\Enums\ShipStationOrderStatus;
use App\Modules\ShipStation\Enums\ShipStationSyncStatus;
use App\Modules\ShipStation\Support\ShipStationDisplayStatusResolver;
use App\Modules\ShipStation\Support\ShipStationShipmentCarrierResolver;
use App\Modules\ShipStation\Support\ShipStationSubmissionDecision;

class ShipStationIntegrationRecordService
{
    /**
     * @param  array<string, mixed>|null  $requestPayload
     * @param  array<string, mixed>|null  $responsePayload
     */
    public function upsertForOrder(

        Order $order,

        ShipStationOrderStatus $status,

        ?string $externalOrderId = null,

        ?string $externalShipmentId = null,

        ?string $externalLabelId = null,

        ?string $trackingNumber = null,

        ?string $carrier = null,

        ?string $carrierId = null,

        ?string $service = null,

        ?string $serviceCode = null,

        ?string $labelStatus = null,

        ?string $labelUrl = null,

        ?float $shipmentCost = null,

        ?array $requestPayload = null,

        ?array $responsePayload = null,

        ?string $errorMessage = null,

        ?\DateTimeInterface $syncedAt = null,

    ): ShipStationIntegration {

        $existing = ShipStationIntegration::query()

            ->where('order_id', $order->id)

            ->where('provider', 'shipstation')

            ->first();

        return ShipStationIntegration::query()->updateOrCreate(

            [

                'order_id' => $order->id,

                'provider' => 'shipstation',

            ],

            [

                'external_order_id' => $externalOrderId ?? $existing?->external_order_id,

                'external_shipment_id' => $externalShipmentId ?? $existing?->external_shipment_id,

                'external_label_id' => $externalLabelId ?? $existing?->external_label_id,

                'tracking_number' => $trackingNumber ?? $existing?->tracking_number,

                'carrier' => $carrier ?? $existing?->carrier,

                'carrier_id' => $carrierId ?? $existing?->carrier_id,

                'service' => $service ?? $existing?->service,

                'service_code' => $serviceCode ?? $existing?->service_code,

                'status' => $status,

                'label_status' => $labelStatus ?? $existing?->label_status,

                'label_url' => $labelUrl ?? $existing?->label_url,

                'shipment_cost' => $shipmentCost ?? $existing?->shipment_cost,

                'synced_at' => $syncedAt ?? $existing?->synced_at,

                'request_payload' => $requestPayload ?? $existing?->request_payload,

                'response_payload' => $responsePayload ?? $existing?->response_payload,

                'error_message' => $errorMessage,

            ],

        );

    }

    /**
     * @param  array<string, mixed>|null  $request
     * @param  array<string, mixed>|null  $response
     */
    public function recordApiCall(

        Order $order,

        string $method,

        string $endpoint,

        ?array $request = null,

        ?array $response = null,

    ): ShipStationIntegration {

        $integration = ShipStationIntegration::query()->firstOrCreate(

            [

                'order_id' => $order->id,

                'provider' => 'shipstation',

            ],

            [

                'status' => ShipStationOrderStatus::NotSent,

            ],

        );

        $integration->update([

            'last_api_method' => strtoupper($method),

            'last_api_endpoint' => $endpoint,

            'request_payload' => $request ?? $integration->request_payload,

            'response_payload' => $response ?? $integration->response_payload,

            'synced_at' => now(),

        ]);

        return $integration->fresh();

    }

    public function resolveDisplayStatus(Order $order): ShipStationOrderStatus
    {
        return app(ShipStationDisplayStatusResolver::class)->resolveIntegrationStatusEnum($order);
    }

    public function displayStatusLabel(Order $order): string
    {
        return $this->crmSynchronizationStatusLabel($order);
    }

    public function crmSynchronizationStatusLabel(Order $order): string
    {
        return app(ShipStationDisplayStatusResolver::class)->crmSynchronizationStatusLabel($order);
    }

    public function primaryShipmentStatusLabel(Order $order): string
    {
        return app(ShipStationDisplayStatusResolver::class)->shipmentStatusLabel($order);
    }

    public function primaryShipmentStatusSubtitle(Order $order): ?string
    {
        return app(ShipStationDisplayStatusResolver::class)->shipmentStatusSubtitle($order);
    }

    public function labelStatusLabel(Order $order): ?string
    {
        return app(ShipStationDisplayStatusResolver::class)->labelStatusLabel($order);
    }

    public function displayCarrierName(Order $order): ?string
    {
        $integration = $order->shipStationIntegration;
        $order->loadMissing('fulfillments');
        $fulfillment = $order->fulfillments->sortByDesc('id')->first();

        $resolver = app(ShipStationShipmentCarrierResolver::class);
        $code = $resolver->sanitizeStoredCarrier($integration?->carrier)
            ?? $resolver->sanitizeStoredCarrier($fulfillment?->carrier);

        return $resolver->displayNameForCode($code);
    }

    public function resolveTrackingUrl(Order $order): ?string
    {
        $order->loadMissing(['shipStationIntegration', 'fulfillments']);
        $integration = $order->shipStationIntegration;
        $fulfillment = $order->fulfillments->sortByDesc('id')->first();

        if (filled($fulfillment?->tracking_url)) {
            return (string) $fulfillment->tracking_url;
        }

        $fromPayload = data_get($integration?->response_payload, 'tracking_url')
            ?? data_get($integration?->response_payload, 'trackingUrl');

        if (filled($fromPayload)) {
            return (string) $fromPayload;
        }

        $tracking = $integration?->tracking_number ?? $fulfillment?->tracking_number;

        if (! filled($tracking)) {
            return null;
        }

        $resolver = app(ShipStationShipmentCarrierResolver::class);
        $carrierCode = $resolver->sanitizeStoredCarrier($integration?->carrier)
            ?? $resolver->sanitizeStoredCarrier($fulfillment?->carrier);

        if ($carrierCode === null) {
            return null;
        }

        $template = ShippingCarrier::query()->where('code', $carrierCode)->value('tracking_url_template');

        return is_string($template) && $template !== ''
            ? str_replace('{tracking}', (string) $tracking, $template)
            : null;
    }

    public function resolveSkipExplanation(Order $order): ?string
    {
        if ($order->shipstation_status !== ShipStationSyncStatus::Skipped->value) {
            return null;
        }

        $order->loadMissing('fulfillments');

        $fulfillment = $order->fulfillments->sortByDesc('id')->first();
        $skipReason = is_array($fulfillment?->warehouse_response)
            ? ($fulfillment->warehouse_response['skip_reason'] ?? null)
            : null;

        if (is_string($skipReason) && $skipReason !== '') {
            return ShipStationSubmissionDecision::ineligible($skipReason)->skipMessage();
        }

        $decision = app(ShipStationOrderEligibilityService::class)->evaluate($order);

        if (! $decision->eligible && filled($decision->reasonCode)) {
            return $decision->skipMessage();
        }

        return null;
    }
}
