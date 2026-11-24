<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Services\OrderTestDetector;
use Illuminate\Support\Carbon;

/**
 * Gates the AUTOMATIC ShipStation label/tracking/email workflow only.
 *
 * Manual admin actions (Create Label, Force Submit with manualOverride) do NOT
 * use this service and are not subject to the activation cutoff or FamTees deferral.
 */
class ShipStationAutoLabelEligibilityService
{
    public function __construct(
        private readonly OrderTestDetector $testOrders,
        private readonly ShipStationOrderEligibilityService $shipStationEligibility,
        private readonly ShipStationFamTeesDeferAutomaticShippingService $famTeesDeferAutomaticShipping,
        private readonly ShipStationAutomaticProcessingHoldService $automaticProcessingHold,
        private readonly ShipStationAutomaticFulfillmentGateService $automaticFulfillmentGate,
        private readonly ShipStationOrderSyncExclusionService $syncExclusion,
    ) {}

    public function activationTimestamp(): ?Carbon
    {
        $value = config('shipstation.auto_label_enabled_at');

        if (blank($value)) {
            return null;
        }

        return Carbon::parse((string) $value)->utc();
    }

    public function isFeatureActivated(): bool
    {
        return $this->activationTimestamp() !== null;
    }

    public function isOrderCreatedAfterActivation(Order $order): bool
    {
        $activation = $this->activationTimestamp();

        if ($activation === null) {
            return false;
        }

        $createdAt = $order->created_at?->copy()->utc();

        if ($createdAt === null) {
            return false;
        }

        return $createdAt->greaterThanOrEqualTo($activation);
    }

    public function isOrderEligibleForFulfillment(Order $order): bool
    {
        if ($order->payment_status !== OrderPaymentStatus::Paid) {
            return false;
        }

        if ($order->status === OrderStatus::Cancelled) {
            return false;
        }

        return true;
    }

    public function isOrderEligibleForAutomaticLabel(Order $order): bool
    {
        if ($this->automaticFulfillmentGate->blocksAutomaticFulfillment()) {
            return false;
        }

        if ($order->isRebill()) {
            return false;
        }

        if (! $this->isOrderEligibleForFulfillment($order)) {
            return false;
        }

        if (! $this->isFeatureActivated()) {
            return false;
        }

        if (! $this->isOrderCreatedAfterActivation($order)) {
            return false;
        }

        if ($this->testOrders->isTestOrder($order)) {
            return false;
        }

        if (! $this->shipStationEligibility->isEligibleForShipStationSubmission($order)) {
            return false;
        }

        if ($this->famTeesDeferAutomaticShipping->blocksAutomaticShipping($order)) {
            return false;
        }

        if ($this->automaticProcessingHold->holdsAutomaticProcessing($order)) {
            return false;
        }

        if ($this->syncExclusion->isExcluded($order)) {
            return false;
        }

        return true;
    }

    public function isFulfillmentEligibleForAutomaticLabel(Fulfillment $fulfillment): bool
    {
        $fulfillment->loadMissing('order');

        if ($fulfillment->status === FulfillmentRecordStatus::Cancelled) {
            return false;
        }

        $order = $fulfillment->order;

        if ($order === null) {
            return false;
        }

        if (! $this->isOrderEligibleForAutomaticLabel($order)) {
            return false;
        }

        return $fulfillment->isDueForWarehouseSubmission();
    }

    public function shouldScheduleAutomaticSubmission(Fulfillment $fulfillment): bool
    {
        $fulfillment->loadMissing(['order', 'fulfillmentHouse']);

        if ($fulfillment->fulfillmentHouse?->provider_type !== 'shipstation') {
            return true;
        }

        if ($this->automaticFulfillmentGate->blocksAutomaticFulfillment()) {
            return false;
        }

        if ($fulfillment->status === FulfillmentRecordStatus::Cancelled) {
            return false;
        }

        $order = $fulfillment->order;

        if ($order?->isRebill()) {
            return $fulfillment->isDueForWarehouseSubmission();
        }

        if ($order === null || ! $this->isOrderEligibleForFulfillment($order)) {
            return false;
        }

        if (! $this->shipStationEligibility->isEligibleForShipStationSubmission($order)) {
            return false;
        }

        if (! $this->isFeatureActivated()) {
            return false;
        }

        if (! $this->isOrderCreatedAfterActivation($order)) {
            return false;
        }

        if ($this->famTeesDeferAutomaticShipping->blocksAutomaticShipping($order)) {
            return false;
        }

        if ($this->automaticProcessingHold->holdsAutomaticProcessing($order)) {
            return false;
        }

        if ($this->syncExclusion->isExcluded($order)) {
            return false;
        }

        return $fulfillment->isDueForWarehouseSubmission();
    }
}
