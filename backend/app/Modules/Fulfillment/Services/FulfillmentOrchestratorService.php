<?php

namespace App\Modules\Fulfillment\Services;

use App\Models\Fulfillment;
use App\Models\FulfillmentItem;
use App\Models\Order;
use App\Modules\Admin\Services\PlatformConfigResolver;
use App\Modules\Fulfillment\Contracts\FulfillmentProviderInterface;
use App\Modules\Fulfillment\Providers\InternalWarehouseProvider;
use App\Modules\Order\Contracts\FulfillmentRepositoryInterface;
use App\Modules\Order\Contracts\OrderRepositoryInterface;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\OrderFulfillmentStatus;
use App\Modules\Order\Events\FulfillmentRecordCreated;
use App\Modules\Order\Services\DelayFulfillmentEngineService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

class FulfillmentOrchestratorService
{
    public function __construct(
        private readonly DelayFulfillmentEngineService $delayEngine,
        private readonly FulfillmentAssignmentEngine $assignment,
        private readonly FulfillmentTimelineService $timeline,
        private readonly FulfillmentRepositoryInterface $fulfillments,
        private readonly OrderRepositoryInterface $orders,
        private readonly PlatformConfigResolver $platformConfig,
    ) {}

    public function createFromPaidOrder(Order $order): Fulfillment
    {
        if (! $this->orderRequiresShipping($order)) {
            $this->orders->update($order, [
                'fulfillment_status' => OrderFulfillmentStatus::Fulfilled,
                'fulfilled_at' => now(),
            ]);

            return Fulfillment::query()->firstOrCreate(
                ['order_id' => $order->id],
                [
                    'delay_days' => 0,
                    'scheduled_ship_date' => now()->toDateString(),
                    'status' => FulfillmentRecordStatus::Delivered,
                    'fulfillment_number' => $this->generateFulfillmentNumber(),
                    'delivered_at' => now(),
                ],
            );
        }

        $existing = Fulfillment::query()->where('order_id', $order->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $assignment = $this->assignment->assignForOrder($order);
        $delayDays = $this->resolveDelayDays($order);
        $shipDate = now()->startOfDay()->addDays($delayDays);

        return DB::transaction(function () use ($order, $assignment, $delayDays, $shipDate): Fulfillment {
            $fulfillment = $this->fulfillments->createForOrder($order, $delayDays, $shipDate);

            $fulfillment->update([
                'fulfillment_number' => $this->generateFulfillmentNumber(),
                'customer_id' => $order->customer_id,
                'subscription_id' => $order->source_subscription_id,
                'fulfillment_house_id' => $assignment['fulfillment_house']?->id,
                'warehouse_id' => $assignment['warehouse']?->id,
                'shipping_carrier_id' => $assignment['shipping_carrier']?->id,
                'shipping_rule_id' => $assignment['shipping_rule']?->id,
                'shipping_method' => $assignment['shipping_method'],
                'status' => $delayDays > 0 ? FulfillmentRecordStatus::Scheduled : FulfillmentRecordStatus::Pending,
                'queued_at' => now(),
            ]);

            $this->syncFulfillmentItems($fulfillment, $order);

            if ($assignment['warehouse']) {
                foreach ($order->items as $item) {
                    if ($item->variant_id) {
                        $this->assignment->reserveInventory(
                            $assignment['warehouse'],
                            (int) $item->variant_id,
                            (int) $item->quantity,
                        );
                    }
                }
            }

            $this->orders->update($order, [
                'fulfillment_status' => OrderFulfillmentStatus::Scheduled,
            ]);

            $this->timeline->record($fulfillment, 'created', 'Fulfillment Created', 'Automatic fulfillment created from paid order');
            if ($assignment['warehouse']) {
                $this->timeline->record($fulfillment, 'warehouse_assigned', 'Assigned to Warehouse', $assignment['warehouse']->name);
            }
            if ($assignment['fulfillment_house']) {
                $this->timeline->record($fulfillment, 'house_assigned', 'Sent to Fulfillment House', $assignment['fulfillment_house']->name);
            }

            Event::dispatch(new FulfillmentRecordCreated($fulfillment->fresh()));

            return $fulfillment->fresh(['items', 'timelineEvents']);
        });
    }

    public function createFromRebillOrder(Order $rebillOrder): Fulfillment
    {
        $rebillOrder->loadMissing(['items.product', 'parentOrder.items.product']);

        if ($rebillOrder->items->isEmpty() && $rebillOrder->parentOrder) {
            foreach ($rebillOrder->parentOrder->items as $parentItem) {
                $rebillOrder->items()->create([
                    'product_id' => $parentItem->product_id,
                    'variant_id' => $parentItem->variant_id,
                    'sku' => $parentItem->sku,
                    'name' => $parentItem->name,
                    'quantity' => $parentItem->quantity,
                    'unit_price' => $parentItem->unit_price,
                    'line_total' => $parentItem->line_total,
                ]);
            }
            $rebillOrder->load('items.product');
            $rebillOrder->update(['fulfillment_status' => \App\Modules\Order\Enums\OrderFulfillmentStatus::Unfulfilled]);
        }

        return $this->createFromPaidOrder($rebillOrder);
    }

    public function updateTracking(
        Fulfillment $fulfillment,
        string $trackingNumber,
        ?string $carrier = null,
        ?string $shippingMethod = null,
    ): Fulfillment {
        $fulfillment->update([
            'tracking_number' => $trackingNumber,
            'carrier' => $carrier ?? $fulfillment->carrier,
            'shipping_method' => $shippingMethod ?? $fulfillment->shipping_method,
            'tracking_url' => $this->buildTrackingUrl($carrier, $trackingNumber),
            'status' => FulfillmentRecordStatus::Shipped,
            'shipped_at' => $fulfillment->shipped_at ?? now(),
        ]);

        $this->timeline->record(
            $fulfillment,
            'tracking_updated',
            'Tracking Updated',
            'Tracking: '.$trackingNumber,
            FulfillmentRecordStatus::Shipped->label(),
        );

        return $fulfillment->fresh();
    }

    public function markDelivered(Fulfillment $fulfillment): Fulfillment
    {
        $from = $fulfillment->status;
        $fulfillment->update([
            'status' => FulfillmentRecordStatus::Delivered,
            'delivered_at' => now(),
        ]);

        $this->timeline->recordStatusChange($fulfillment, $from, FulfillmentRecordStatus::Delivered);

        if ($fulfillment->order) {
            $this->orders->update($fulfillment->order, [
                'fulfillment_status' => OrderFulfillmentStatus::Fulfilled,
                'fulfilled_at' => now(),
            ]);
        }

        return $fulfillment->fresh();
    }

    public function cancel(Fulfillment $fulfillment, ?string $reason = null): Fulfillment
    {
        $from = $fulfillment->status;
        $fulfillment->update([
            'status' => FulfillmentRecordStatus::Cancelled,
            'cancelled_at' => now(),
            'failure_reason' => $reason,
        ]);

        $this->timeline->recordStatusChange($fulfillment, $from, FulfillmentRecordStatus::Cancelled, $reason);

        if ($fulfillment->order) {
            $this->orders->update($fulfillment->order, [
                'fulfillment_status' => OrderFulfillmentStatus::Unfulfilled,
            ]);
        }

        return $fulfillment->fresh();
    }

    public function providerFor(Fulfillment $fulfillment): FulfillmentProviderInterface
    {
        $type = $fulfillment->fulfillmentHouse?->provider_type ?? 'internal';

        return match ($type) {
            'shipstation' => app(\App\Modules\Fulfillment\Providers\ShipStationProvider::class),
            'csv_export' => app(\App\Modules\Fulfillment\Providers\CsvExportProvider::class),
            default => app(InternalWarehouseProvider::class),
        };
    }

    private function syncFulfillmentItems(Fulfillment $fulfillment, Order $order): void
    {
        foreach ($order->items as $item) {
            FulfillmentItem::query()->create([
                'fulfillment_id' => $fulfillment->id,
                'order_item_id' => $item->id,
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ]);
        }
    }

    private function orderRequiresShipping(Order $order): bool
    {
        $order->loadMissing('items.product');

        foreach ($order->items as $item) {
            if ($item->product?->requires_shipping) {
                return true;
            }
        }

        return $order->items->isNotEmpty();
    }

    private function resolveDelayDays(Order $order): int
    {
        $fromMetadata = $order->metadata['fulfillment_delay_days'] ?? null;

        if ($fromMetadata !== null) {
            return max(0, (int) $fromMetadata);
        }

        return $this->platformConfig->fulfillmentDefaultDelayDays();
    }

    private function generateFulfillmentNumber(): string
    {
        do {
            $number = 'FF-'.strtoupper(Str::random(8));
        } while (Fulfillment::query()->where('fulfillment_number', $number)->exists());

        return $number;
    }

    private function buildTrackingUrl(?string $carrier, string $trackingNumber): ?string
    {
        if ($carrier === null) {
            return null;
        }

        $template = \App\Models\ShippingCarrier::query()
            ->where('code', strtolower($carrier))
            ->value('tracking_url_template');

        return $template ? str_replace('{tracking}', $trackingNumber, $template) : null;
    }
}
