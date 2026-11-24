<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Fulfillment;
use App\Models\Order;
use App\Models\ShipStationIntegrationLog;
use App\Modules\Fulfillment\Services\FulfillmentOrchestratorService;
use App\Modules\Order\Enums\DeliveryStatus;
use App\Modules\Order\Enums\FulfillmentRecordStatus;
use App\Modules\Order\Enums\ShipmentStatus;
use App\Modules\ShipStation\Enums\ShipStationOrderStatus;
use App\Modules\ShipStation\Enums\ShipStationSyncStatus;
use App\Modules\ShipStation\Exceptions\ShipStationApiException;
use App\Modules\ShipStation\Exceptions\ShipStationSubmissionSkippedException;
use App\Modules\ShipStation\Support\ShipStationV2OrderReference;
use App\Modules\ShipStation\Support\ShipStationV2PayloadBuilder;
use App\Modules\ShipStation\Support\ShipStationV2ShipmentLookup;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Log;

class ShipStationMockFulfillmentService
{
    public function __construct(
        private readonly ShipStationConfigResolver $config,
        private readonly ShipStationV2ApiClient $v2,
        private readonly ShipStationV2PayloadBuilder $payloadBuilder,
        private readonly ShipStationIntegrationRecordService $integrationRecords,
        private readonly FulfillmentOrchestratorService $orchestrator,
        private readonly ShipStationRebillGuard $rebillGuard,
        private readonly ShipStationOrderEligibilityService $eligibility,
        private readonly ShipStationV2ShipmentLookup $shipmentLookup,
        private readonly ShipStationAutomaticProcessingHoldService $automaticProcessingHold,
        private readonly ShipStationOrderSyncExclusionService $syncExclusion,
    ) {}

    /**
     * @return array{order: Order, endpoint: string, method: string, response: array<string, mixed>, skipped?: bool}
     */
    public function sendOrder(Order $order): array
    {
        $this->ensureMockMode();
        $this->syncExclusion->assertSynchronizationAllowed($order);
        $this->rebillGuard->assertAllowed($order, 'send_order');

        $decision = $this->eligibility->evaluate($order);

        if (! $decision->eligible) {
            $this->eligibility->markSubmissionSkipped($order, $decision);

            return [
                'order' => $order->fresh(['shipStationIntegration']),
                'endpoint' => $this->v2->baseUrl().'/carriers',
                'method' => 'GET',
                'response' => [],
                'skipped' => true,
                'skip_reason' => $decision->reasonCode,
            ];
        }

        $order->loadMissing(['shipStationIntegration']);

        if ($this->hasBeenSent($order)) {
            return [
                'order' => $order->fresh(['shipStationIntegration']),
                'endpoint' => $order->shipStationIntegration?->last_api_endpoint ?? $this->v2->baseUrl().'/carriers',
                'method' => 'GET',
                'response' => is_array($order->shipStationIntegration?->response_payload)
                    ? $order->shipStationIntegration->response_payload
                    : [],
                'skipped' => true,
            ];
        }

        $response = $this->v2->getCarriers();
        $endpoint = $this->v2->baseUrl().'/carriers';

        $order->update([
            'shipstation_status' => ShipStationSyncStatus::Synced->value,
            'shipstation_synced_at' => now(),
            'shipstation_sync_error' => null,
        ]);

        $this->integrationRecords->upsertForOrder(
            order: $order->fresh(),
            status: ShipStationOrderStatus::Sent,
            externalOrderId: ShipStationV2OrderReference::crmExternalReference($order),
            responsePayload: $response,
            labelStatus: 'pending',
            syncedAt: now(),
        );

        $this->logStep($order, 'send_order', $endpoint, 'GET', null, $response, true);

        $this->integrationRecords->recordApiCall(
            order: $order->fresh(),
            method: 'GET',
            endpoint: $endpoint,
            response: $response,
        );

        return [
            'order' => $order->fresh(['shipStationIntegration']),
            'endpoint' => $endpoint,
            'method' => 'GET',
            'response' => $response,
        ];
    }

    /**
     * @return array{order: Order, endpoint: string, method: string, response: array<string, mixed>, skipped?: bool}
     */
    public function createShipment(Order $order): array
    {
        $this->ensureMockMode();
        $this->syncExclusion->assertSynchronizationAllowed($order);
        $this->rebillGuard->assertAllowed($order, 'create_shipment');
        $decision = $this->eligibility->evaluate($order);

        if (! $decision->eligible) {
            $this->eligibility->markSubmissionSkipped($order, $decision);

            return [
                'order' => $order->fresh(['shipStationIntegration']),
                'endpoint' => $this->v2->baseUrl().'/shipments',
                'method' => 'SKIPPED',
                'response' => [],
                'skipped' => true,
                'skip_reason' => $decision->reasonCode,
            ];
        }

        $this->config->assertWarehouseConfiguredForRealShipments();
        $this->config->assertStoreConfiguredForRealShipments();

        $order->loadMissing(['items', 'customer', 'shipStationIntegration']);

        if (! $this->hasBeenSent($order)) {
            throw new ShipStationApiException('Send this order to ShipStation before creating a shipment.');
        }

        $integration = $order->shipStationIntegration;

        if (filled($integration?->external_shipment_id)) {
            $shipmentId = (string) $integration->external_shipment_id;
            $endpoint = $this->v2->baseUrl().'/shipments/'.$shipmentId;

            try {
                $response = $this->v2->getShipment($shipmentId, $order->id);
            } catch (ShipStationApiException) {
                $response = is_array($integration->response_payload) ? $integration->response_payload : [
                    'shipments' => [[
                        'shipment_id' => $shipmentId,
                        'external_order_id' => $integration->external_order_id,
                    ]],
                ];
            }

            return [
                'order' => $order->fresh(['shipStationIntegration']),
                'endpoint' => $endpoint,
                'method' => 'GET',
                'response' => $response,
                'skipped' => true,
            ];
        }

        $crmReference = ShipStationV2OrderReference::crmExternalReference($order);
        $existingRemote = $this->shipmentLookup->findByExternalShipmentId($crmReference, $order->id);

        if ($existingRemote !== null) {
            return $this->linkExistingRemoteShipment($order, $existingRemote, $crmReference);
        }

        if ($this->automaticProcessingHold->holdsAutomaticProcessing($order)) {
            Log::info('ShipStation create shipment skipped — automatic processing hold.', [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return [
                'order' => $order->fresh(['shipStationIntegration']),
                'endpoint' => $this->v2->baseUrl().'/shipments',
                'method' => 'SKIPPED',
                'response' => [],
                'skipped' => true,
                'skip_reason' => 'automatic_processing_hold',
            ];
        }

        $payload = $this->payloadBuilder->buildCreateShipmentsPayload($order);
        $endpoint = $this->v2->baseUrl().'/shipments';

        try {
            $response = $this->v2->createShipments($payload, $order->id);
        } catch (ShipStationApiException $exception) {
            $this->integrationRecords->upsertForOrder(
                order: $order->fresh(),
                status: ShipStationOrderStatus::Error,
                externalOrderId: $integration?->external_order_id ?? (string) $order->order_number,
                requestPayload: $payload,
                errorMessage: $exception->getMessage(),
            );

            throw $exception;
        }

        $shipment = $response['shipments'][0] ?? null;

        if (! is_array($shipment)) {
            throw new ShipStationApiException('ShipStation mock API did not return a shipment.');
        }

        $shipmentId = (string) ($shipment['shipment_id'] ?? '');
        $externalOrderId = ShipStationV2OrderReference::crmExternalReference($order);

        if ($shipmentId === '') {
            throw new ShipStationApiException('ShipStation mock API did not return a shipment_id.');
        }

        $numericOrderId = $this->resolveNumericOrderId($externalOrderId, $shipmentId);

        $order->update([
            'shipstation_order_id' => $numericOrderId,
            'shipstation_status' => ShipStationSyncStatus::Synced->value,
            'shipstation_synced_at' => now(),
            'shipstation_sync_error' => null,
        ]);

        $carrierFields = app(\App\Modules\ShipStation\Support\ShipStationShipmentCarrierResolver::class)
            ->resolveFromShipment($shipment);

        $this->integrationRecords->upsertForOrder(
            order: $order->fresh(),
            status: ShipStationOrderStatus::Processing,
            externalOrderId: $externalOrderId,
            externalShipmentId: $shipmentId,
            carrier: $carrierFields['carrier_code'],
            carrierId: $carrierFields['carrier_id'],
            service: (string) ($shipment['requested_shipment_service'] ?? $shipment['service_code'] ?? null),
            serviceCode: (string) ($shipment['service_code'] ?? null),
            labelStatus: 'shipment_created',
            requestPayload: $payload,
            responsePayload: $response,
            errorMessage: null,
            syncedAt: now(),
        );

        $this->integrationRecords->recordApiCall(
            order: $order->fresh(),
            method: 'POST',
            endpoint: $endpoint,
            request: $payload,
            response: $response,
        );

        $this->logStep($order, 'create_shipment', $endpoint, 'POST', $payload, $response, true, $numericOrderId, $shipmentId);

        Log::info('ShipStation V2 shipment created.', [
            'order_id' => $order->id,
            'shipment_id' => $shipmentId,
            'external_order_id' => $externalOrderId,
            'warehouse_id' => $this->config->warehouseIdForStore($this->config->storeId()),
            'mode' => $this->config->mode()->value,
        ]);

        return [
            'order' => $order->fresh(['shipStationIntegration']),
            'endpoint' => $endpoint,
            'method' => 'POST',
            'response' => $response,
        ];
    }

    /**
     * @return array{fulfillment: ?Fulfillment, order: Order, endpoint: string, method: string, response: array<string, mixed>, skipped?: bool}
     */
    public function createLabel(Order $order, ?Fulfillment $fulfillment = null): array
    {
        $this->ensureMockMode();
        $this->syncExclusion->assertSynchronizationAllowed($order);
        $this->rebillGuard->assertAllowed($order, 'create_label');
        $this->assertEligibleForSubmission($order, $fulfillment?->id, 'label');

        $order->loadMissing(['shipStationIntegration', 'items', 'customer', 'fulfillments']);

        if (! $this->hasBeenSent($order)) {
            $sent = $this->sendOrder($order);
            $order = $sent['order'];
        }

        $integration = $order->shipStationIntegration;

        if ($integration === null || blank($integration->external_shipment_id)) {
            throw new ShipStationApiException(
                'Create a ShipStation shipment before creating a label.',
            );
        }

        $this->assertOrderReadyForLabel($order);

        $shipmentId = (string) $integration->external_shipment_id;

        if ($this->labelAlreadyPurchased($order, $fulfillment)) {
            return $this->returnExistingLabel($order, $fulfillment, $shipmentId);
        }

        $payload = $this->payloadBuilder->buildCreateLabelPayload();
        $endpoint = $this->v2->baseUrl().'/labels/shipment/'.$shipmentId;

        Log::info('ShipStation V2 label creation requested.', [
            'order_id' => $order->id,
            'shipment_id' => $shipmentId,
            'mode' => $this->config->mode()->value,
        ]);

        try {
            $response = $this->v2->createLabelFromShipment($shipmentId, $payload, $order->id);
        } catch (ShipStationApiException $exception) {
            if ($this->shouldReconcileAfterFailure($exception)) {
                $reconciled = $this->reconcileExistingLabel($order->fresh(['shipStationIntegration']), $shipmentId);

                if ($reconciled !== null) {
                    Log::warning('ShipStation label purchase failed but remote label was found — reconciled.', [
                        'order_id' => $order->id,
                        'shipment_id' => $shipmentId,
                        'http_status' => $exception->httpStatus,
                    ]);

                    return $this->persistLabelFromResponse(
                        order: $order->fresh(['shipStationIntegration', 'fulfillments']),
                        fulfillment: $fulfillment,
                        shipmentId: $shipmentId,
                        response: $reconciled,
                        payload: $payload,
                        endpoint: $endpoint,
                        method: 'POST',
                        reconciled: true,
                    );
                }
            }

            throw $exception;
        } catch (ConnectionException $exception) {
            $reconciled = $this->reconcileExistingLabel($order->fresh(['shipStationIntegration']), $shipmentId);

            if ($reconciled !== null) {
                Log::warning('ShipStation label request timed out but remote label was found — reconciled.', [
                    'order_id' => $order->id,
                    'shipment_id' => $shipmentId,
                ]);

                return $this->persistLabelFromResponse(
                    order: $order->fresh(['shipStationIntegration', 'fulfillments']),
                    fulfillment: $fulfillment,
                    shipmentId: $shipmentId,
                    response: $reconciled,
                    payload: $payload,
                    endpoint: $endpoint,
                    method: 'POST',
                    reconciled: true,
                );
            }

            throw new ShipStationApiException(
                'ShipStation label request timed out before a response was received. Reconcile manually before retrying to avoid duplicate postage.',
                null,
            );
        }

        return $this->persistLabelFromResponse(
            order: $order,
            fulfillment: $fulfillment,
            shipmentId: $shipmentId,
            response: $response,
            payload: $payload,
            endpoint: $endpoint,
            method: 'POST',
            reconciled: false,
        );
    }

    /**
     * @return array{order: Order, endpoint: string, method: string, response: array<string, mixed>}
     */
    public function getTracking(Order $order): array
    {
        $this->ensureMockMode();

        $integration = $order->shipStationIntegration;
        $labelId = (string) ($integration?->external_label_id ?? '');

        if ($labelId === '') {
            throw new ShipStationApiException('Create a ShipStation label before fetching tracking.');
        }

        $endpoint = $this->v2->baseUrl().'/labels/'.$labelId.'/track';
        $response = $this->v2->getLabelTracking($labelId, $order->id);
        $trackingNumber = (string) ($response['tracking_number'] ?? '');

        if ($trackingNumber === '') {
            throw new ShipStationApiException('ShipStation mock API did not return a tracking_number.');
        }

        $this->integrationRecords->upsertForOrder(
            order: $order->fresh(),
            status: ShipStationOrderStatus::Shipped,
            externalOrderId: $integration?->external_order_id,
            externalShipmentId: $integration?->external_shipment_id,
            externalLabelId: $labelId,
            trackingNumber: $trackingNumber,
            carrier: $integration?->carrier,
            carrierId: $integration?->carrier_id,
            service: $integration?->service,
            serviceCode: $integration?->service_code,
            labelStatus: $integration?->label_status ?? 'completed',
            labelUrl: $integration?->label_url,
            shipmentCost: $integration?->shipment_cost !== null ? (float) $integration->shipment_cost : null,
            responsePayload: $response,
            syncedAt: now(),
        );

        $this->integrationRecords->recordApiCall(
            order: $order->fresh(),
            method: 'GET',
            endpoint: $endpoint,
            response: $response,
        );

        foreach ($order->fulfillments as $fulfillment) {
            if ($trackingNumber !== '') {
                $this->orchestrator->updateTracking(
                    $fulfillment,
                    $trackingNumber,
                    (string) ($integration?->carrier ?? $response['carrier_code'] ?? ''),
                    (string) ($integration?->service_code ?? ''),
                );
            }
        }

        $this->logStep($order, 'get_tracking', $endpoint, 'GET', null, $response, true, context: [
            'tracking_number' => $trackingNumber,
        ]);

        return [
            'order' => $order->fresh(['shipStationIntegration', 'fulfillments']),
            'endpoint' => $endpoint,
            'method' => 'GET',
            'response' => $response,
        ];
    }

    /**
     * @return array{order: Order, calls: list<array{endpoint: string, method: string, response: array<string, mixed>}>}
     */
    public function refreshStatus(Order $order): array
    {
        $this->ensureMockMode();

        $integration = $order->shipStationIntegration;
        $calls = [];

        if (filled($integration?->external_shipment_id)) {
            $endpoint = $this->v2->baseUrl().'/shipments/'.$integration->external_shipment_id;
            $shipmentResponse = $this->v2->getShipment((string) $integration->external_shipment_id, $order->id);
            $calls[] = ['endpoint' => $endpoint, 'method' => 'GET', 'response' => $shipmentResponse];
        }

        if (filled($integration?->external_label_id)) {
            $labelEndpoint = $this->v2->baseUrl().'/labels/'.$integration->external_label_id;
            $labelResponse = $this->v2->getLabel((string) $integration->external_label_id, $order->id);
            $calls[] = ['endpoint' => $labelEndpoint, 'method' => 'GET', 'response' => $labelResponse];

            $trackingNumber = (string) ($integration->tracking_number ?? '');

            try {
                $trackEndpoint = $this->v2->baseUrl().'/labels/'.$integration->external_label_id.'/track';
                $trackResponse = $this->v2->getLabelTracking((string) $integration->external_label_id, $order->id);
                $calls[] = ['endpoint' => $trackEndpoint, 'method' => 'GET', 'response' => $trackResponse];

                if (filled($trackResponse['tracking_number'] ?? null)) {
                    $trackingNumber = (string) $trackResponse['tracking_number'];
                }
            } catch (ShipStationApiException) {
                $trackingNumber = (string) ($labelResponse['tracking_number'] ?? $trackingNumber);
            }

            $this->integrationRecords->upsertForOrder(
                order: $order->fresh(),
                status: ($labelResponse['status'] ?? '') === 'completed'
                    ? ShipStationOrderStatus::Shipped
                    : ShipStationOrderStatus::Processing,
                externalOrderId: $integration->external_order_id,
                externalShipmentId: (string) ($labelResponse['shipment_id'] ?? $integration->external_shipment_id),
                externalLabelId: (string) ($labelResponse['label_id'] ?? $integration->external_label_id),
                trackingNumber: $trackingNumber ?: null,
                carrier: (string) ($labelResponse['carrier_code'] ?? $integration->carrier),
                carrierId: (string) ($labelResponse['carrier_id'] ?? $integration->carrier_id),
                service: (string) ($labelResponse['service_code'] ?? $integration->service_code),
                serviceCode: (string) ($labelResponse['service_code'] ?? $integration->service_code),
                labelStatus: (string) ($labelResponse['status'] ?? $integration->label_status),
                labelUrl: $this->extractLabelUrl($labelResponse) ?: $integration->label_url,
                shipmentCost: is_numeric(data_get($labelResponse, 'shipment_cost.amount'))
                    ? (float) data_get($labelResponse, 'shipment_cost.amount')
                    : ($integration->shipment_cost !== null ? (float) $integration->shipment_cost : null),
                responsePayload: $labelResponse,
                syncedAt: now(),
            );
        }

        return [
            'order' => $order->fresh(['shipStationIntegration', 'fulfillments']),
            'calls' => $calls,
        ];
    }

    private function ensureMockMode(): void
    {
        if (! $this->config->isMockMode() && ! $this->config->isRealMode()) {
            throw new ShipStationApiException('ShipStation V2 fulfillment requires mock or real mode.');
        }

        if (! $this->config->isEnabled()) {
            throw new ShipStationApiException('ShipStation integration is disabled.');
        }
    }

    private function assertEligibleForSubmission(Order $order, ?int $fulfillmentId = null, string $action = 'shipment'): void
    {
        $decision = $this->eligibility->evaluate($order);

        if ($decision->eligible) {
            return;
        }

        $this->eligibility->markSubmissionSkipped($order, $decision, $fulfillmentId);

        throw new ShipStationSubmissionSkippedException($decision, action: $action);
    }

    private function hasBeenSent(Order $order): bool
    {
        return $order->shipStationIntegration !== null
            && in_array($order->shipStationIntegration->status, [
                ShipStationOrderStatus::Sent,
                ShipStationOrderStatus::Processing,
                ShipStationOrderStatus::Shipped,
            ], true);
    }

    /**
     * @return array{order: Order, steps: list<array<string, mixed>>}
     */
    public function runAutomaticWorkflow(Order $order, ?Fulfillment $fulfillment = null, bool $createLabel = false): array
    {
        $steps = [];

        $sent = $this->sendOrder($order->fresh(['items', 'customer', 'shipStationIntegration']));
        $steps[] = $sent;
        $order = $sent['order'];

        $shipment = $this->createShipment($order->fresh(['items', 'customer', 'shipStationIntegration']));
        $steps[] = $shipment;
        $order = $shipment['order'];

        if ($createLabel) {
            $label = $this->createLabel($order->fresh(['shipStationIntegration']), $fulfillment);
            $steps[] = $label;
            $order = $label['order'];
        }

        return [
            'order' => $order->fresh(['shipStationIntegration', 'fulfillments']),
            'steps' => $steps,
        ];
    }

    private function resolveNumericOrderId(string $externalOrderId, string $shipmentId): int
    {
        if (ctype_digit($externalOrderId)) {
            return (int) $externalOrderId;
        }

        return abs(crc32($shipmentId));
    }

    private function assertOrderReadyForLabel(Order $order): void
    {
        if ($order->items()->count() === 0) {
            throw new ShipStationApiException('Cannot create ShipStation label: order has no line items.');
        }

        $address = $order->shipping_address ?? $order->billing_address ?? [];

        if (blank($address['line1'] ?? $address['address_line1'] ?? null)) {
            throw new ShipStationApiException('Cannot create ShipStation label: shipping address is missing.');
        }

        if (blank($address['city'] ?? null) || blank($address['postal_code'] ?? $address['zip'] ?? null)) {
            throw new ShipStationApiException('Cannot create ShipStation label: shipping city or postal code is missing.');
        }
    }

    private function labelAlreadyPurchased(Order $order, ?Fulfillment $fulfillment): bool
    {
        $integration = $order->shipStationIntegration;

        if (filled($integration?->external_label_id)) {
            return true;
        }

        if (filled($integration?->tracking_number)) {
            return true;
        }

        if ($fulfillment !== null && filled($fulfillment->tracking_number)) {
            return true;
        }

        return false;
    }

    /**
     * @return array{fulfillment: ?Fulfillment, order: Order, endpoint: string, method: string, response: array<string, mixed>, skipped: bool}
     */
    private function returnExistingLabel(Order $order, ?Fulfillment $fulfillment, string $shipmentId): array
    {
        $integration = $order->shipStationIntegration;
        $labelId = (string) ($integration?->external_label_id ?? '');

        if ($labelId !== '') {
            try {
                $response = $this->v2->getLabel($labelId, $order->id);
            } catch (ShipStationApiException) {
                $response = is_array($integration?->response_payload) ? $integration->response_payload : [];
            }
        } else {
            $response = $this->reconcileExistingLabel($order, $shipmentId)
                ?? (is_array($integration?->response_payload) ? $integration->response_payload : []);
        }

        $result = $this->persistLabelFromResponse(
            order: $order,
            fulfillment: $fulfillment,
            shipmentId: $shipmentId,
            response: $response,
            payload: [],
            endpoint: $this->v2->baseUrl().'/labels/'.($response['label_id'] ?? $labelId),
            method: 'GET',
            reconciled: true,
        );

        $result['skipped'] = true;

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function reconcileExistingLabel(Order $order, string $shipmentId): ?array
    {
        $integration = $order->shipStationIntegration;
        $labelId = (string) ($integration?->external_label_id ?? '');

        if ($labelId !== '') {
            try {
                return $this->v2->getLabel($labelId, $order->id);
            } catch (ShipStationApiException|ConnectionException) {
                // Fall through to shipment-based lookup.
            }
        }

        try {
            $listed = $this->v2->listLabels(['shipment_id' => $shipmentId, 'page_size' => 1], $order->id);
            $labels = is_array($listed['labels'] ?? null) ? $listed['labels'] : [];

            if ($labels !== [] && is_array($labels[0])) {
                $candidateId = (string) ($labels[0]['label_id'] ?? '');

                if ($candidateId !== '') {
                    return $this->v2->getLabel($candidateId, $order->id);
                }
            }
        } catch (ShipStationApiException|ConnectionException) {
            // Fall through to external shipment lookup.
        }

        try {
            return $this->v2->getLabelByExternalShipmentId(
                ShipStationV2OrderReference::crmExternalReference($order),
                $order->id,
            );
        } catch (ShipStationApiException|ConnectionException) {
            // Fall through to legacy external id.
        }

        try {
            return $this->v2->getLabelByExternalShipmentId(
                ShipStationV2OrderReference::legacyExternalShipmentId($order),
                $order->id,
            );
        } catch (ShipStationApiException|ConnectionException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $remoteShipment
     * @return array{order: Order, endpoint: string, method: string, response: array<string, mixed>, skipped: bool}
     */
    private function linkExistingRemoteShipment(Order $order, array $remoteShipment, string $crmReference): array
    {
        $shipmentId = (string) ($remoteShipment['shipment_id'] ?? '');

        if ($shipmentId === '') {
            throw new ShipStationApiException('ShipStation returned a shipment without shipment_id.');
        }

        $endpoint = $this->v2->baseUrl().'/shipments/'.$shipmentId;
        $response = $remoteShipment;

        $numericOrderId = $this->resolveNumericOrderId($crmReference, $shipmentId);

        $order->update([
            'shipstation_order_id' => $numericOrderId,
            'shipstation_status' => ShipStationSyncStatus::Synced->value,
            'shipstation_synced_at' => now(),
            'shipstation_sync_error' => null,
        ]);

        $carrierFields = app(\App\Modules\ShipStation\Support\ShipStationShipmentCarrierResolver::class)
            ->resolveFromShipment($remoteShipment);

        $this->integrationRecords->upsertForOrder(
            order: $order->fresh(),
            status: ShipStationOrderStatus::Processing,
            externalOrderId: $crmReference,
            externalShipmentId: $shipmentId,
            carrier: $carrierFields['carrier_code'],
            carrierId: $carrierFields['carrier_id'],
            service: (string) ($remoteShipment['service_code'] ?? $remoteShipment['requested_shipment_service'] ?? null),
            serviceCode: (string) ($remoteShipment['service_code'] ?? null),
            labelStatus: (string) ($remoteShipment['shipment_status'] ?? 'shipment_linked'),
            responsePayload: ['shipments' => [$remoteShipment]],
            syncedAt: now(),
        );

        Log::info('ShipStation V2 shipment linked from existing external_shipment_id (no POST).', [
            'order_id' => $order->id,
            'crm_reference' => $crmReference,
            'shipment_id' => $shipmentId,
        ]);

        return [
            'order' => $order->fresh(['shipStationIntegration']),
            'endpoint' => $endpoint,
            'method' => 'GET',
            'response' => $response,
            'skipped' => true,
        ];
    }

    private function shouldReconcileAfterFailure(ShipStationApiException $exception): bool
    {
        if ($exception->httpStatus === null) {
            return true;
        }

        return in_array($exception->httpStatus, [408, 409, 502, 503, 504], true);
    }

    /**
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $payload
     * @return array{fulfillment: ?Fulfillment, order: Order, endpoint: string, method: string, response: array<string, mixed>}
     */
    private function persistLabelFromResponse(
        Order $order,
        ?Fulfillment $fulfillment,
        string $shipmentId,
        array $response,
        array $payload,
        string $endpoint,
        string $method,
        bool $reconciled,
    ): array {
        $integration = $order->shipStationIntegration;
        $labelId = (string) ($response['label_id'] ?? $integration?->external_label_id ?? '');
        $trackingNumber = (string) ($response['tracking_number'] ?? $integration?->tracking_number ?? '');
        $labelStatus = (string) ($response['status'] ?? $integration?->label_status ?? 'unknown');
        $carrierFields = app(\App\Modules\ShipStation\Support\ShipStationShipmentCarrierResolver::class)
            ->resolveFromShipment($response);
        $carrierId = $carrierFields['carrier_id']
            ?? (string) ($integration?->carrier_id ?? '');
        $carrierCode = $carrierFields['carrier_code']
            ?? app(\App\Modules\ShipStation\Support\ShipStationShipmentCarrierResolver::class)
                ->sanitizeStoredCarrier($integration?->carrier);
        $serviceCode = (string) ($response['service_code'] ?? $integration?->service_code ?? '');
        $labelUrl = $this->extractLabelUrl($response) ?: $integration?->label_url;
        $shipmentCost = data_get($response, 'shipment_cost.amount');

        if ($labelId === '') {
            throw new ShipStationApiException('ShipStation V2 did not return a label_id.');
        }

        if ($trackingNumber === '') {
            $labelDetails = $this->v2->getLabel($labelId, $order->id);
            $trackingNumber = (string) ($labelDetails['tracking_number'] ?? '');
            $labelStatus = (string) ($labelDetails['status'] ?? $labelStatus);
            $labelUrl = $labelUrl ?: $this->extractLabelUrl($labelDetails);
            $response = array_merge($response, $labelDetails);
        }

        $this->integrationRecords->upsertForOrder(
            order: $order->fresh(),
            status: $labelStatus === 'completed' ? ShipStationOrderStatus::Shipped : ShipStationOrderStatus::Processing,
            externalOrderId: $integration?->external_order_id,
            externalShipmentId: (string) ($response['shipment_id'] ?? $shipmentId),
            externalLabelId: $labelId,
            trackingNumber: $trackingNumber !== '' ? $trackingNumber : null,
            carrier: $carrierCode,
            carrierId: $carrierId !== '' ? $carrierId : null,
            service: $serviceCode !== '' ? $serviceCode : null,
            serviceCode: $serviceCode !== '' ? $serviceCode : null,
            labelStatus: $labelStatus,
            labelUrl: $labelUrl,
            shipmentCost: is_numeric($shipmentCost) ? (float) $shipmentCost : null,
            requestPayload: $payload !== [] ? $payload : $integration?->request_payload,
            responsePayload: $response,
            syncedAt: now(),
        );

        if ($method === 'POST' && $payload !== []) {
            $this->integrationRecords->recordApiCall(
                order: $order->fresh(),
                method: 'POST',
                endpoint: $endpoint,
                request: $payload,
                response: $response,
            );
        }

        if ($fulfillment !== null && $trackingNumber !== '') {
            $this->applyLabelToFulfillment(
                $fulfillment,
                $response,
                $trackingNumber,
                $carrierId,
                $serviceCode,
                $labelId,
                $shipmentId,
            );
        }

        $this->logStep(
            order: $order,
            requestType: $reconciled ? 'reconcile_label' : 'create_label',
            endpoint: $endpoint,
            method: $method,
            request: $payload !== [] ? $payload : null,
            response: $response,
            success: true,
            shipStationOrderId: (int) ($order->shipstation_order_id ?? 0) ?: null,
            shipmentId: $shipmentId,
            context: [
                'label_id' => $labelId,
                'tracking_number' => $trackingNumber !== '' ? $trackingNumber : null,
                'reconciled' => $reconciled,
            ],
        );

        Log::info($reconciled ? 'ShipStation V2 label reconciled.' : 'ShipStation V2 label created.', [
            'order_id' => $order->id,
            'shipment_id' => $shipmentId,
            'label_id' => $labelId,
            'tracking_number' => $trackingNumber !== '' ? $trackingNumber : null,
            'label_status' => $labelStatus,
            'mode' => $this->config->mode()->value,
            'reconciled' => $reconciled,
        ]);

        return [
            'fulfillment' => $fulfillment?->fresh(),
            'order' => $order->fresh(['shipStationIntegration', 'fulfillments']),
            'endpoint' => $endpoint,
            'method' => $method,
            'response' => $response,
        ];
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function extractLabelUrl(array $response): ?string
    {
        $download = $response['label_download'] ?? null;

        if (! is_array($download)) {
            return null;
        }

        foreach (['pdf', 'href', 'png', 'zpl'] as $key) {
            if (filled($download[$key] ?? null)) {
                return (string) $download[$key];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     */
    private function applyLabelToFulfillment(
        Fulfillment $fulfillment,
        array $response,
        string $trackingNumber,
        string $carrierId,
        string $serviceCode,
        string $labelId,
        string $shipmentId,
    ): void {
        $metadata = $fulfillment->metadata ?? [];
        $metadata['shipstation'] = array_merge($metadata['shipstation'] ?? [], [
            'shipment_id' => $response['shipment_id'] ?? $shipmentId,
            'label_id' => $labelId,
            'carrier_id' => $carrierId,
            'service_code' => $serviceCode,
            'shipping_cost' => data_get($response, 'shipment_cost.amount'),
            'label_status' => $response['status'] ?? null,
            'label_url' => $this->extractLabelUrl($response),
            'label_created_at' => now()->toIso8601String(),
            'v2_api' => true,
            'mode' => $this->config->mode()->value,
        ]);

        $fulfillment->update([
            'warehouse_reference' => (string) ($response['shipment_id'] ?? $shipmentId),
            'tracking_number' => $trackingNumber,
            'carrier' => app(\App\Modules\ShipStation\Support\ShipStationShipmentCarrierResolver::class)
                ->normalizeCarrierCode($response['carrier_code'] ?? null) ?? '',
            'shipping_method' => $serviceCode,
            'shipment_status' => ShipmentStatus::Processing,
            'delivery_status' => DeliveryStatus::Pending,
            'status' => FulfillmentRecordStatus::Shipped,
            'shipped_at' => now(),
            'warehouse_response' => $response,
            'metadata' => $metadata,
            'failure_reason' => null,
        ]);

        $this->orchestrator->updateTracking(
            $fulfillment->fresh(),
            $trackingNumber,
            app(\App\Modules\ShipStation\Support\ShipStationShipmentCarrierResolver::class)
                ->normalizeCarrierCode($response['carrier_code'] ?? null),
            $serviceCode,
        );
    }

    /**
     * @param  array<string, mixed>|null  $request
     * @param  array<string, mixed>  $response
     * @param  array<string, mixed>  $context
     */
    private function logStep(
        Order $order,
        string $requestType,
        string $endpoint,
        string $method,
        ?array $request,
        array $response,
        bool $success,
        ?int $shipStationOrderId = null,
        ?string $shipmentId = null,
        array $context = [],
    ): void {
        ShipStationIntegrationLog::query()->create([
            'request_type' => $requestType,
            'order_id' => $order->id,
            'shipstation_order_id' => $shipStationOrderId,
            'shipstation_shipment_id' => is_numeric($shipmentId) ? (int) $shipmentId : null,
            'success' => $success,
            'http_status' => 200,
            'request_payload' => $request,
            'response_payload' => $response,
            'context' => array_merge($context, [
                'endpoint' => $endpoint,
                'method' => $method,
                'mock_api' => true,
            ]),
        ]);
    }
}
