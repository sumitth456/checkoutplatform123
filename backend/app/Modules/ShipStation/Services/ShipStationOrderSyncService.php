<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Order;
use App\Models\ShipStationIntegrationLog;
use App\Modules\Order\Enums\OrderPaymentStatus;
use App\Modules\Order\Enums\OrderStatus;
use App\Modules\Order\Services\OrderTestDetector;
use App\Modules\ShipStation\Contracts\ShipStationServiceInterface;
use App\Modules\ShipStation\Enums\ShipStationOrderStatus;
use App\Modules\ShipStation\Enums\ShipStationSyncStatus;
use App\Modules\ShipStation\Exceptions\ShipStationApiException;
use App\Modules\ShipStation\Support\ShipStationPayloadBuilder;
use Illuminate\Support\Facades\Log;

class ShipStationOrderSyncService
{
    public function __construct(
        private readonly ShipStationConfigResolver $config,
        private readonly ShipStationServiceInterface $api,
        private readonly ShipStationPayloadBuilder $payloadBuilder,
        private readonly ShipStationIntegrationRecordService $integrationRecords,
        private readonly ShipStationMockFulfillmentService $mockFulfillment,
        private readonly OrderTestDetector $testOrders,
        private readonly ShipStationRebillGuard $rebillGuard,
        private readonly ShipStationOrderEligibilityService $eligibility,
        private readonly ShipStationOrderSyncExclusionService $syncExclusion,
    ) {}

    public function sync(Order $order, bool $force = false): Order
    {
        if (! $this->config->isEnabled()) {
            return $order;
        }

        if ($this->syncExclusion->isExcluded($order)) {
            Log::info('ShipStation order sync skipped — order is excluded from synchronization.', [
                'order_id' => $order->id,
                'order_uuid' => $order->uuid,
                'order_number' => $order->order_number,
            ]);

            return $order;
        }

        if ($this->rebillGuard->isExcluded($order)) {
            return $this->rebillGuard->markSyncSkipped($order, 'order_sync');
        }

        $decision = $this->eligibility->evaluate($order);

        if (! $decision->eligible) {
            return $this->eligibility->markSubmissionSkipped($order, $decision);
        }

        $order->loadMissing(['items', 'customer']);

        if (! $this->orderIsEligible($order)) {
            $order->update([
                'shipstation_status' => ShipStationSyncStatus::Skipped->value,
                'shipstation_sync_error' => null,
            ]);

            return $order->fresh();
        }

        if ($this->config->isMockMode() || $this->config->isRealMode()) {
            return $this->syncPaidOrderV2($order, $force);
        }

        if (! $force && filled($order->shipstation_order_id)) {
            return $this->updateExisting($order);
        }

        $payload = $this->payloadBuilder->buildOrderPayload($order);

        try {
            $response = $this->api->createOrder($payload);
            $shipStationOrderId = (int) ($response['orderId'] ?? 0);

            if ($shipStationOrderId <= 0) {
                throw new ShipStationApiException('ShipStation did not return an order ID.');
            }

            $order->update([
                'shipstation_order_id' => $shipStationOrderId,
                'shipstation_status' => ShipStationSyncStatus::Synced->value,
                'shipstation_synced_at' => now(),
                'shipstation_sync_error' => null,
            ]);

            $this->integrationRecords->upsertForOrder(
                order: $order->fresh(),
                status: ShipStationOrderStatus::Sent,
                externalOrderId: (string) $shipStationOrderId,
                requestPayload: $payload,
                responsePayload: $response,
            );

            $this->log('create_order', $order, true, shipStationOrderId: $shipStationOrderId, request: $payload, response: $response);

            return $order->fresh();
        } catch (ShipStationApiException $exception) {
            $order->update([
                'shipstation_status' => ShipStationSyncStatus::Failed->value,
                'shipstation_sync_error' => $exception->getMessage(),
            ]);

            $this->integrationRecords->upsertForOrder(
                order: $order->fresh(),
                status: ShipStationOrderStatus::Error,
                requestPayload: $payload,
                errorMessage: $exception->getMessage(),
            );

            $this->log('create_order', $order, false, error: $exception->getMessage(), httpStatus: $exception->httpStatus, request: $payload);

            Log::warning('ShipStation order sync failed', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
                'http_status' => $exception->httpStatus,
            ]);

            throw $exception;
        }
    }

    public function refreshFromShipStation(Order $order): Order
    {
        if ($this->config->isMockMode() || $this->config->isRealMode()) {
            return $this->mockFulfillment->refreshStatus($order)['order'];
        }

        if (! filled($order->shipstation_order_id)) {
            return $order;
        }

        $remote = $this->api->getOrder((int) $order->shipstation_order_id);

        $order->update([
            'shipstation_status' => ShipStationSyncStatus::Synced->value,
            'shipstation_synced_at' => now(),
            'shipstation_sync_error' => null,
        ]);

        $this->integrationRecords->upsertForOrder(
            order: $order->fresh(),
            status: ShipStationOrderStatus::Sent,
            externalOrderId: (string) $order->shipstation_order_id,
            responsePayload: $remote,
        );

        $this->log('get_order', $order, true, shipStationOrderId: (int) $order->shipstation_order_id, response: $remote, context: [
            'remote_status' => $remote['orderStatus'] ?? null,
        ]);

        return $order->fresh();
    }

    private function updateExisting(Order $order): Order
    {
        $payload = $this->payloadBuilder->buildOrderPayload($order);

        try {
            $response = $this->api->createOrder($payload);

            $order->update([
                'shipstation_order_id' => (int) ($response['orderId'] ?? $order->shipstation_order_id),
                'shipstation_status' => ShipStationSyncStatus::Synced->value,
                'shipstation_synced_at' => now(),
                'shipstation_sync_error' => null,
            ]);

            $this->integrationRecords->upsertForOrder(
                order: $order->fresh(),
                status: ShipStationOrderStatus::Sent,
                externalOrderId: (string) $order->shipstation_order_id,
                requestPayload: $payload,
                responsePayload: $response,
            );

            $this->log('update_order', $order, true, shipStationOrderId: (int) $order->shipstation_order_id, request: $payload, response: $response);

            return $order->fresh();
        } catch (ShipStationApiException $exception) {
            $order->update([
                'shipstation_status' => ShipStationSyncStatus::Failed->value,
                'shipstation_sync_error' => $exception->getMessage(),
            ]);

            $this->integrationRecords->upsertForOrder(
                order: $order->fresh(),
                status: ShipStationOrderStatus::Error,
                externalOrderId: filled($order->shipstation_order_id) ? (string) $order->shipstation_order_id : null,
                requestPayload: $payload,
                errorMessage: $exception->getMessage(),
            );

            $this->log('update_order', $order, false, error: $exception->getMessage(), httpStatus: $exception->httpStatus, request: $payload);

            throw $exception;
        }
    }

    private function syncPaidOrderV2(Order $order, bool $force): Order
    {
        if (! $force && filled($order->shipStationIntegration?->external_shipment_id)) {
            return $order->fresh(['shipStationIntegration']);
        }

        try {
            $sent = $this->mockFulfillment->sendOrder($order);
            $order = $sent['order']->fresh(['items', 'customer', 'shipStationIntegration']);

            if ($this->paidOrderSyncCreatesPendingShipment()) {
                $shipment = $this->mockFulfillment->createShipment(
                    $order->fresh(['items', 'customer', 'shipStationIntegration']),
                );

                return $shipment['order'];
            }

            return $order;
        } catch (ShipStationApiException $exception) {
            $order->update([
                'shipstation_status' => ShipStationSyncStatus::Failed->value,
                'shipstation_sync_error' => $exception->getMessage(),
            ]);

            $this->integrationRecords->upsertForOrder(
                order: $order->fresh(),
                status: ShipStationOrderStatus::Error,
                errorMessage: $exception->getMessage(),
            );

            $this->log('sync_paid_order_v2', $order, false, error: $exception->getMessage(), httpStatus: $exception->httpStatus);

            Log::warning('ShipStation V2 paid-order sync failed', [
                'order_id' => $order->id,
                'message' => $exception->getMessage(),
                'http_status' => $exception->httpStatus,
            ]);

            throw $exception;
        }
    }

    private function paidOrderSyncCreatesPendingShipment(): bool
    {
        return (string) config('shipstation.paid_order_sync_mode', 'crm_preflight') === 'pending_shipment_import';
    }

    private function orderIsEligible(Order $order): bool
    {
        if ($this->rebillGuard->isExcluded($order)) {
            return false;
        }

        if ($order->payment_status !== OrderPaymentStatus::Paid) {
            Log::info('ShipStation sync skipped — order payment status ineligible.', [
                'order_id' => $order->id,
                'payment_status' => $order->payment_status->value,
            ]);

            return false;
        }

        if ($order->status === OrderStatus::Cancelled) {
            Log::info('ShipStation sync skipped — order cancelled.', [
                'order_id' => $order->id,
            ]);

            return false;
        }

        if (blank($order->shipping_address) && blank($order->billing_address)) {
            return false;
        }

        if ($order->items()->count() === 0) {
            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $request
     * @param  array<string, mixed>|null  $response
     * @param  array<string, mixed>|null  $context
     */
    private function log(
        string $requestType,
        Order $order,
        bool $success,
        ?int $shipStationOrderId = null,
        ?string $error = null,
        ?int $httpStatus = null,
        ?array $request = null,
        ?array $response = null,
        ?array $context = null,
    ): void {
        ShipStationIntegrationLog::query()->create([
            'request_type' => $requestType,
            'order_id' => $order->id,
            'shipstation_order_id' => $shipStationOrderId,
            'success' => $success,
            'http_status' => $httpStatus,
            'error_message' => $error,
            'request_payload' => $request,
            'response_payload' => $response,
            'context' => $context,
        ]);
    }
}
