<?php

namespace Tests\Feature\Email;

use App\Models\EmailLog;
use App\Models\EmailProvider;
use App\Models\EmailQueueItem;
use App\Models\Fulfillment;
use App\Models\Order;
use App\Modules\Email\Enums\EmailLogStatus;
use App\Modules\Email\Enums\EmailProviderStatus;
use App\Modules\Email\Enums\EmailProviderType;
use App\Modules\Email\Enums\EmailQueueStatus;
use App\Modules\Email\Listeners\DispatchEmailAutomationListener;
use App\Modules\Email\Services\EmailDispatchService;
use App\Modules\Order\DTOs\WarehouseShipmentResponseDTO;
use App\Modules\Order\Enums\DeliveryStatus;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\ShipmentStatus;
use App\Modules\Order\Events\FulfillmentSubmittedToWarehouse;
use Database\Seeders\EmailModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShippingEmailMailjetTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(EmailModuleSeeder::class);

        EmailProvider::query()->update(['is_default' => false]);

        EmailProvider::query()->create([
            'name' => 'Mailjet Production',
            'provider_type' => EmailProviderType::Mailjet,
            'api_key' => 'test-api-key',
            'secret_key' => 'test-secret-key',
            'from_email' => 'noreply@example.com',
            'from_name' => 'Checkout Platform',
            'status' => EmailProviderStatus::Enabled,
            'priority' => 1,
            'is_default' => true,
        ]);

        Http::fake([
            'api.mailjet.com/v3/send' => Http::response([
                'Messages' => [[
                    'Status' => 'success',
                    'To' => [['Email' => 'ship@example.com', 'MessageID' => 987654321]],
                ]],
            ], 200),
        ]);
    }

    #[Test]
    public function shipping_email_is_sent_through_mailjet_when_tracking_exists(): void
    {
        $order = Order::factory()->create([
            'customer_email' => 'ship@example.com',
            'order_number' => 'ORD-SHIP-001',
        ]);

        $fulfillment = Fulfillment::query()->create([
            'order_id' => $order->id,
            'status' => FulfillmentRecordStatus::Scheduled,
            'scheduled_ship_date' => now()->toDateString(),
        ]);

        $response = new WarehouseShipmentResponseDTO(
            warehouseReference: 'WH-001',
            trackingNumber: '782758401696',
            carrier: 'USPS',
            shipmentStatus: ShipmentStatus::Shipped,
            deliveryStatus: DeliveryStatus::InTransit,
            raw: ['tracking_url' => 'https://tools.usps.com/go/TrackConfirmAction?tLabels=782758401696'],
        );

        app(DispatchEmailAutomationListener::class)->handleFulfillmentSubmitted(
            new FulfillmentSubmittedToWarehouse($fulfillment->fresh(['order']), $response),
        );

        $items = EmailQueueItem::query()->where('recipient_email', 'ship@example.com')->get();
        $this->assertGreaterThanOrEqual(1, $items->count());

        foreach ($items as $item) {
            if ($item->status !== EmailQueueStatus::Sent) {
                app(EmailDispatchService::class)->processQueueItem($item->fresh());
                $item->refresh();
            }
        }

        $log = EmailLog::query()->where('recipient_email', 'ship@example.com')->first();
        $this->assertNotNull($log);
        $this->assertSame(EmailLogStatus::Sent, $log->status);
        $this->assertStringContainsString('ORD-SHIP-001', $log->subject);
        $this->assertStringContainsString('782758401696', (string) $log->html_body);
        $this->assertSame('Mailjet Production', $log->provider_response['provider_name'] ?? null);

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'api.mailjet.com/v3/send'));
    }

    #[Test]
    public function shipping_email_is_not_sent_without_tracking_number(): void
    {
        $order = Order::factory()->create([
            'customer_email' => 'no-tracking@example.com',
            'order_number' => 'ORD-SHIP-002',
        ]);

        $fulfillment = Fulfillment::query()->create([
            'order_id' => $order->id,
            'status' => FulfillmentRecordStatus::Scheduled,
            'scheduled_ship_date' => now()->toDateString(),
        ]);

        $response = new WarehouseShipmentResponseDTO(
            warehouseReference: 'WH-002',
            trackingNumber: '',
            carrier: 'USPS',
            shipmentStatus: ShipmentStatus::Pending,
            deliveryStatus: DeliveryStatus::Pending,
        );

        app(DispatchEmailAutomationListener::class)->handleFulfillmentSubmitted(
            new FulfillmentSubmittedToWarehouse($fulfillment->fresh(['order']), $response),
        );

        $this->assertSame(0, EmailQueueItem::query()->where('recipient_email', 'no-tracking@example.com')->count());
        Http::assertNothingSent();
    }

    #[Test]
    public function shipping_email_is_not_sent_for_test_orders(): void
    {
        $order = Order::factory()->create([
            'customer_email' => 'test-order@example.com',
            'order_number' => 'ORD-SHIP-003',
            'metadata' => ['shipstation_test_order' => true],
        ]);

        $fulfillment = Fulfillment::query()->create([
            'order_id' => $order->id,
            'status' => FulfillmentRecordStatus::Scheduled,
            'scheduled_ship_date' => now()->toDateString(),
        ]);

        $response = new WarehouseShipmentResponseDTO(
            warehouseReference: 'WH-003',
            trackingNumber: '782758401696',
            carrier: 'USPS',
            shipmentStatus: ShipmentStatus::Shipped,
            deliveryStatus: DeliveryStatus::InTransit,
        );

        app(DispatchEmailAutomationListener::class)->handleFulfillmentSubmitted(
            new FulfillmentSubmittedToWarehouse($fulfillment->fresh(['order']), $response),
        );

        $this->assertSame(0, EmailQueueItem::query()->where('recipient_email', 'test-order@example.com')->count());
        Http::assertNothingSent();
    }

    #[Test]
    public function shipping_email_is_not_sent_for_shipment_only_submissions(): void
    {
        $order = Order::factory()->create([
            'customer_email' => 'shipment-only@example.com',
            'order_number' => 'ORD-SHIP-004',
        ]);

        $fulfillment = Fulfillment::query()->create([
            'order_id' => $order->id,
            'status' => FulfillmentRecordStatus::Scheduled,
            'scheduled_ship_date' => now()->toDateString(),
        ]);

        $response = new WarehouseShipmentResponseDTO(
            warehouseReference: 'WH-004',
            trackingNumber: '782758401696',
            carrier: 'USPS',
            shipmentStatus: ShipmentStatus::Shipped,
            deliveryStatus: DeliveryStatus::InTransit,
            raw: [],
            shipmentOnly: true,
        );

        app(DispatchEmailAutomationListener::class)->handleFulfillmentSubmitted(
            new FulfillmentSubmittedToWarehouse($fulfillment->fresh(['order']), $response),
        );

        $this->assertSame(0, EmailQueueItem::query()->where('recipient_email', 'shipment-only@example.com')->count());
        Http::assertNothingSent();
    }

    #[Test]
    public function email_failure_does_not_change_order_fulfillment_state(): void
    {
        Http::fake([
            'api.mailjet.com/v3/send' => Http::response(['ErrorMessage' => 'Temporary outage'], 503),
        ]);

        $order = Order::factory()->create([
            'customer_email' => 'fail@example.com',
            'order_number' => 'ORD-SHIP-005',
            'fulfillment_status' => 'processing',
        ]);

        $fulfillment = Fulfillment::query()->create([
            'order_id' => $order->id,
            'status' => FulfillmentRecordStatus::Scheduled,
            'scheduled_ship_date' => now()->toDateString(),
        ]);

        $response = new WarehouseShipmentResponseDTO(
            warehouseReference: 'WH-005',
            trackingNumber: '782758401696',
            carrier: 'USPS',
            shipmentStatus: ShipmentStatus::Shipped,
            deliveryStatus: DeliveryStatus::InTransit,
        );

        app(DispatchEmailAutomationListener::class)->handleFulfillmentSubmitted(
            new FulfillmentSubmittedToWarehouse($fulfillment->fresh(['order']), $response),
        );

        $item = EmailQueueItem::query()->where('recipient_email', 'fail@example.com')->first();
        $this->assertNotNull($item);

        app(EmailDispatchService::class)->processQueueItem($item->fresh());

        $this->assertSame('processing', $order->fresh()->fulfillment_status?->value ?? $order->fresh()->fulfillment_status);
    }

    #[Test]
    public function email_retry_does_not_create_duplicate_shipping_emails(): void
    {
        $order = Order::factory()->create([
            'customer_email' => 'retry@example.com',
            'order_number' => 'ORD-SHIP-006',
        ]);

        $fulfillment = Fulfillment::query()->create([
            'order_id' => $order->id,
            'status' => FulfillmentRecordStatus::Scheduled,
            'scheduled_ship_date' => now()->toDateString(),
        ]);

        $response = new WarehouseShipmentResponseDTO(
            warehouseReference: 'WH-006',
            trackingNumber: '782758401696',
            carrier: 'USPS',
            shipmentStatus: ShipmentStatus::Shipped,
            deliveryStatus: DeliveryStatus::InTransit,
        );

        $listener = app(DispatchEmailAutomationListener::class);
        $listener->handleFulfillmentSubmitted(
            new FulfillmentSubmittedToWarehouse($fulfillment->fresh(['order']), $response),
        );
        $listener->handleFulfillmentSubmitted(
            new FulfillmentSubmittedToWarehouse($fulfillment->fresh(['order']), $response),
        );

        $shippedItems = EmailQueueItem::query()
            ->where('recipient_email', 'retry@example.com')
            ->get();

        $this->assertSame(2, $shippedItems->count());
    }
}
