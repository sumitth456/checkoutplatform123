<?php

namespace App\Modules\ShipStation\Support;

use App\Models\Order;
use App\Models\ShipStationIntegration;
use App\Modules\ShipStation\Enums\ShipStationOrderStatus;
use App\Modules\ShipStation\Enums\ShipStationSyncStatus;

/**
 * Centralized UI labels for ShipStation shipment, label, and CRM sync states.
 */
final class ShipStationDisplayStatusResolver
{
    public function __construct(
        private readonly ShipStationV2ShipmentStatusInterpreter $interpreter,
    ) {}

    public function shipmentStatusLabel(Order $order): string
    {
        if ($order->shipstation_status === ShipStationSyncStatus::Skipped->value) {
            return 'Skipped';
        }

        $integration = $order->shipStationIntegration;

        if ($integration === null) {
            return $this->orderLevelSyncFallbackLabel($order);
        }

        $remote = $this->effectiveRemoteShipmentStatus($integration);

        if ($remote !== null) {
            return $this->interpreter->uiShipmentStatusLabel($remote);
        }

        if (filled($integration->external_shipment_id)) {
            $stored = strtolower(trim((string) $integration->label_status));
            $hasLabel = filled($integration->external_label_id);

            return match (true) {
                $stored === 'shipment_created' && ! $hasLabel => 'Shipment created',
                in_array($stored, ['pending', 'awaiting_shipment', 'on_hold', 'processing'], true) => $this->interpreter->uiShipmentStatusLabel($stored),
                in_array($stored, ['label_purchased', 'label_created', 'completed'], true) => 'Label purchased',
                $hasLabel => 'Label purchased',
                in_array($stored, ['cancelled', 'voided'], true) => $this->interpreter->uiShipmentStatusLabel($stored),
                default => 'Shipment created',
            };
        }

        return $this->crmSynchronizationStatusLabel($order);
    }

    public function shipmentStatusSubtitle(Order $order): ?string
    {
        $integration = $order->shipStationIntegration;

        if ($integration === null) {
            return null;
        }

        $remote = $this->effectiveRemoteShipmentStatus($integration);

        if ($remote !== null) {
            return $this->interpreter->uiDetailSubtitle($remote, null);
        }

        return $this->interpreter->uiDetailSubtitle(null, $integration->label_status);
    }

    public function labelStatusLabel(Order $order): ?string
    {
        $integration = $order->shipStationIntegration;

        if ($integration === null) {
            return null;
        }

        return $this->interpreter->uiStoredLabelStatusLabel(
            $integration->label_status,
            filled($integration->external_label_id),
        );
    }

    public function crmSynchronizationStatusLabel(Order $order): string
    {
        if ($order->shipstation_status === ShipStationSyncStatus::Skipped->value) {
            return 'Skipped';
        }

        $integration = $order->shipStationIntegration;

        if ($integration !== null) {
            return $integration->status->label();
        }

        return $this->orderLevelSyncFallbackLabel($order);
    }

    public function effectiveRemoteShipmentStatus(ShipStationIntegration $integration): ?string
    {
        if (filled($integration->remote_shipment_status)) {
            return strtolower(trim((string) $integration->remote_shipment_status));
        }

        $fromPayload = $this->extractRemoteShipmentStatusFromPayload($integration->response_payload);

        return $fromPayload !== null ? strtolower(trim($fromPayload)) : null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function extractRemoteShipmentStatusFromPayload(?array $payload): ?string
    {
        if ($payload === null) {
            return null;
        }

        foreach ([
            data_get($payload, 'shipment_status'),
            data_get($payload, 'shipments.0.shipment_status'),
            data_get($payload, 'status'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return $candidate;
            }
        }

        return null;
    }

    public function resolveIntegrationStatusEnum(Order $order): ShipStationOrderStatus
    {
        if ($order->shipstation_status === ShipStationSyncStatus::Skipped->value) {
            return $this->orderStatusSkipped();
        }

        $integration = $order->shipStationIntegration;

        if ($integration !== null) {
            $remote = $this->effectiveRemoteShipmentStatus($integration);

            if ($remote !== null) {
                $interpreted = $this->interpreter->interpret($remote, $integration->label_status);

                return $interpreted['integration_status'];
            }

            if (filled($integration->external_shipment_id) || filled($integration->external_label_id)) {
                return ShipStationOrderStatus::Processing;
            }

            return $integration->status;
        }

        return match ($order->shipstation_status) {
            ShipStationSyncStatus::Failed->value => ShipStationOrderStatus::Error,
            ShipStationSyncStatus::Synced->value => ShipStationOrderStatus::Sent,
            ShipStationSyncStatus::Pending->value => ShipStationOrderStatus::Sent,
            default => filled($order->shipstation_order_id)
                ? ShipStationOrderStatus::Sent
                : ShipStationOrderStatus::NotSent,
        };
    }

    private function orderLevelSyncFallbackLabel(Order $order): string
    {
        return match ($order->shipstation_status) {
            ShipStationSyncStatus::Failed->value => 'Error',
            ShipStationSyncStatus::Synced->value => 'Sent',
            ShipStationSyncStatus::Pending->value => 'Sent',
            default => filled($order->shipstation_order_id) ? 'Sent' : 'Not Sent',
        };
    }

    private function orderStatusSkipped(): ShipStationOrderStatus
    {
        foreach (ShipStationOrderStatus::cases() as $case) {
            if ($case->value === 'skipped') {
                return $case;
            }
        }

        return ShipStationOrderStatus::Processing;
    }
}