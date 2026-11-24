<?php

namespace App\Modules\ShipStation\Support;

use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\ShipStation\Enums\ShipStationOrderStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Pilot-safe V2 shipment interpretation (does not treat label creation as shipped).
 */
final class ShipStationV2ShipmentStatusInterpreter
{
    public function normalizeRemoteStatus(?string $shipmentStatus, ?string $labelStatus = null): string
    {
        $status = strtolower(trim((string) $shipmentStatus));
        $label = strtolower(trim((string) $labelStatus));

        if ($status === '') {
            return $label !== '' ? $label : 'unknown';
        }

        return $status;
    }

  /** @return array{integration_status: ShipStationOrderStatus, fulfillment_status: ?FulfillmentRecordStatus, label_status: ?string, is_confirmed_shipped: bool, is_delivered: bool, rank: int} */
    public function interpret(?string $shipmentStatus, ?string $labelStatus = null): array
    {
        $normalized = $this->normalizeRemoteStatus($shipmentStatus, $labelStatus);

        $rank = $this->rank($normalized);

        $integrationStatus = match (true) {
            in_array($normalized, ['delivered'], true) => ShipStationOrderStatus::Shipped,
            in_array($normalized, ['shipped', 'in_transit', 'out_for_delivery'], true) => ShipStationOrderStatus::Shipped,
            in_array($normalized, ['label_purchased', 'shipment_created', 'label_created', 'completed'], true) => ShipStationOrderStatus::Processing,
            in_array($normalized, ['processing', 'awaiting_shipment', 'pending', 'on_hold'], true) => ShipStationOrderStatus::Processing,
            in_array($normalized, ['cancelled', 'voided'], true) => ShipStationOrderStatus::Cancelled,
            default => ShipStationOrderStatus::Processing,
        };

        $fulfillmentStatus = match (true) {
            $normalized === 'delivered' => FulfillmentRecordStatus::Delivered,
            in_array($normalized, ['shipped', 'in_transit', 'out_for_delivery'], true) => FulfillmentRecordStatus::Shipped,
            default => null,
        };

        $displayLabel = match (true) {
            in_array($normalized, ['label_purchased', 'label_created', 'completed'], true) => 'label_purchased',
            $normalized === 'shipment_created' => 'shipment_created',
            in_array($normalized, ['awaiting_shipment', 'pending', 'processing', 'on_hold'], true) => 'processing',
            in_array($normalized, ['shipped', 'in_transit', 'out_for_delivery'], true) => 'shipped',
            $normalized === 'delivered' => 'delivered',
            default => $normalized,
        };

        return [
            'integration_status' => $integrationStatus,
            'fulfillment_status' => $fulfillmentStatus,
            'label_status' => $displayLabel,
            'is_confirmed_shipped' => $fulfillmentStatus === FulfillmentRecordStatus::Shipped,
            'is_delivered' => $fulfillmentStatus === FulfillmentRecordStatus::Delivered,
            'rank' => $rank,
        ];
    }

    public function rank(?string $normalizedStatus): int
    {
        return match (strtolower((string) $normalizedStatus)) {
            'delivered' => 60,
            'in_transit', 'out_for_delivery' => 55,
            'shipped' => 50,
            'label_purchased', 'shipment_created', 'label_created', 'completed' => 30,
            'processing', 'awaiting_shipment', 'on_hold' => 20,
            'pending' => 10,
            'cancelled', 'voided' => 0,
            default => 15,
        };
    }

    public function shouldDowngrade(int $existingRank, int $incomingRank): bool
    {
        return $incomingRank < $existingRank;
    }

    public function parseShipDate(mixed $value): ?CarbonInterface
    {
        if (blank($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }

    public function uiLabel(?string $shipmentStatus, ?string $labelStatus = null): string
    {
        if (filled($shipmentStatus)) {
            return $this->uiShipmentStatusLabel($shipmentStatus);
        }

        return $this->uiStoredLabelStatusLabel($labelStatus, false) ?? 'Unknown';
    }

    public function uiShipmentStatusLabel(?string $shipmentStatus): string
    {
        $normalized = strtolower(trim((string) $shipmentStatus));

        return match (true) {
            in_array($normalized, ['pending', 'awaiting_shipment', 'on_hold'], true) => 'Pending',
            $normalized === 'processing' => 'Processing',
            $normalized === 'shipment_created' => 'Shipment created',
            in_array($normalized, ['label_purchased', 'label_created', 'completed'], true) => 'Label purchased',
            $normalized === 'shipped' => 'Shipped',
            $normalized === 'in_transit' => 'In transit',
            $normalized === 'out_for_delivery' => 'Out for delivery',
            $normalized === 'delivered' => 'Delivered',
            $normalized === 'cancelled' => 'Cancelled',
            $normalized === 'voided' => 'Voided',
            $normalized === 'unknown', $normalized === '' => 'Unknown',
            default => str($normalized)->headline()->toString(),
        };
    }

    public function uiStoredLabelStatusLabel(?string $labelStatus, bool $hasExternalLabelId): ?string
    {
        $normalized = strtolower(trim((string) $labelStatus));

        if ($normalized === '' && ! $hasExternalLabelId) {
            return null;
        }

        return match (true) {
            in_array($normalized, ['shipment_created', 'not_created', 'none', 'no_label'], true) => 'No label',
            in_array($normalized, ['pending', 'awaiting_shipment', 'on_hold', 'processing'], true) && ! $hasExternalLabelId => 'No label',
            in_array($normalized, ['label_purchased', 'label_created', 'completed'], true) => 'Label purchased',
            $hasExternalLabelId && $normalized === '' => 'Label purchased',
            $normalized === 'voided' => 'Voided',
            $normalized === 'cancelled' => 'Cancelled',
            $hasExternalLabelId => 'Label purchased',
            default => str($normalized)->headline()->toString(),
        };
    }

    public function uiDetailSubtitle(?string $shipmentStatus, ?string $labelStatus = null): ?string
    {
        if (filled($shipmentStatus)) {
            $normalized = strtolower(trim((string) $shipmentStatus));

            return in_array($normalized, ['label_purchased', 'label_created', 'completed'], true)
                ? 'Awaiting carrier pickup'
                : null;
        }

        $label = strtolower(trim((string) $labelStatus));

        return in_array($label, ['label_purchased', 'label_created', 'completed'], true)
            ? 'Awaiting carrier pickup'
            : null;
    }
}