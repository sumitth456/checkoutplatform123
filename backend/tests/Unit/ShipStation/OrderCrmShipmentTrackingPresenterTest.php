<?php

namespace Tests\Unit\ShipStation;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\ShipStationIntegration;
use App\Models\ShippingCarrier;
use App\Modules\ShipStation\Enums\ShipStationOrderStatus;
use App\Modules\ShipStation\Support\OrderCrmShipmentTrackingPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class OrderCrmShipmentTrackingPresenterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_shows_tracking_number_and_carrier_from_integration(): void
    {
        $order = Order::factory()->create();

        ShipStationIntegration::query()->create([
            'order_id' => $order->id,
            'provider' => 'shipstation',
            'external_shipment_id' => 'se-123',
            'tracking_number' => '1ZTRACK999',
            'carrier' => 'ups',
            'service' => 'ups_ground',
            'status' => ShipStationOrderStatus::Shipped,
            'remote_shipment_status' => 'shipped',
        ]);

        ShippingCarrier::query()->create([
            'code' => 'ups',
            'name' => 'UPS',
            'tracking_url_template' => 'https://www.ups.com/track?tracknum={tracking}',
        ]);

        $present = app(OrderCrmShipmentTrackingPresenter::class)->present($order->fresh());

        $this->assertSame('ready', $present['state']);
        $this->assertSame('1ZTRACK999', $present['tracking_number']);
        $this->assertSame('UPS', $present['carrier']);
        $this->assertSame('ups_ground', $present['shipping_method']);
        $this->assertTrue($present['tracking_url_trusted']);
        $this->assertStringContainsString('1ZTRACK999', (string) $present['tracking_url']);
    }

    #[Test]
    public function it_reports_pending_when_shipment_exists_without_tracking(): void
    {
        $order = Order::factory()->create();

        ShipStationIntegration::query()->create([
            'order_id' => $order->id,
            'provider' => 'shipstation',
            'external_shipment_id' => 'se-pending',
            'status' => ShipStationOrderStatus::Processing,
        ]);

        $present = app(OrderCrmShipmentTrackingPresenter::class)->present($order->fresh());

        $this->assertSame('pending', $present['state']);
        $this->assertNull($present['tracking_number']);
        $this->assertSame('Shipment created', $present['status_label']);
    }

    #[Test]
    public function it_uses_shadow_snapshot_for_crm_preview_without_mutating_columns(): void
    {
        $order = Order::factory()->create();

        ShipStationIntegration::query()->create([
            'order_id' => $order->id,
            'provider' => 'shipstation',
            'external_shipment_id' => 'se-shadow',
            'tracking_number' => null,
            'status' => ShipStationOrderStatus::Processing,
            'tracking_sync_meta' => [
                'shadow_last_snapshot' => [
                    'tracking_number' => '1ZSHADOW',
                    'shipment_status' => 'shipped',
                    'carrier_code' => 'ups',
                    'service_code' => 'ups_ground',
                ],
                'shadow_last_at' => now()->toIso8601String(),
            ],
        ]);

        $present = app(OrderCrmShipmentTrackingPresenter::class)->present($order->fresh());

        $this->assertTrue($present['shadow_preview']);
        $this->assertSame('1ZSHADOW', $present['tracking_number']);
        $this->assertNull($order->fresh()->shipStationIntegration->tracking_number);
    }

    #[Test]
    public function it_builds_tracking_history_from_payload_events(): void
    {
        $order = Order::factory()->create();

        ShipStationIntegration::query()->create([
            'order_id' => $order->id,
            'provider' => 'shipstation',
            'external_shipment_id' => 'se-hist',
            'tracking_number' => '1ZHIST',
            'status' => ShipStationOrderStatus::Shipped,
            'response_payload' => [
                'events' => [
                    [
                        'occurred_at' => '2026-11-20T10:00:00Z',
                        'status' => 'shipped',
                        'description' => 'Departed facility',
                        'city' => 'Dallas, TX',
                    ],
                ],
            ],
        ]);

        $present = app(OrderCrmShipmentTrackingPresenter::class)->present($order->fresh());

        $this->assertCount(1, $present['history']);
        $this->assertSame('shipped', $present['history'][0]['status']);
        $this->assertSame('Departed facility', $present['history'][0]['description']);
    }

    #[Test]
    public function it_falls_back_to_fulfillment_tracking_when_integration_missing_tracking(): void
    {
        $order = Order::factory()->create();

        ShipStationIntegration::query()->create([
            'order_id' => $order->id,
            'provider' => 'shipstation',
            'external_shipment_id' => 'se-f-1',
            'status' => ShipStationOrderStatus::Processing,
        ]);

        Fulfillment::query()->create([
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'fulfillment_number' => 'FUL-CRM-TEST',
            'scheduled_ship_date' => now()->toDateString(),
            'status' => \App\Modules\Order\Enums\FulfillmentRecordStatus::Shipped,
            'tracking_number' => 'F-TRACK-1',
            'carrier' => 'usps',
        ]);

        $present = app(OrderCrmShipmentTrackingPresenter::class)->present($order->fresh());

        $this->assertSame('F-TRACK-1', $present['tracking_number']);
    }
}