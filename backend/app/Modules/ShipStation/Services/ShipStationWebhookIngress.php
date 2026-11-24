<?php

namespace App\Modules\ShipStation\Services;

use App\Models\Order;
use App\Models\ShipStationIntegrationLog;
use App\Models\ShipStationWebhookReceipt;
use App\Modules\ShipStation\Support\ShipStationPilotAutoStatusSyncGate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ShipStationWebhookIngress
{
    public function __construct(
        private readonly ShipStationConfigResolver $config,
        private readonly ShipStationWebhookDeliveryDedupService $dedup,
        private readonly ShipStationWebhookOrderResolver $resolver,
        private readonly ShipStationPilotAutoStatusSyncGate $pilotGate,
        private readonly ShipStationPilotAutoStatusSyncService $pilotSync,
        private readonly ReshipmentWebhookSyncService $reshipmentWebhookSync,
        private readonly ShipStationOrderSyncExclusionService $syncExclusions,
        private readonly ShipStationTrackingSyncEligibilityService $trackingEligibility,
        private readonly ShipStationTrackingJobDispatcher $trackingDispatcher,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function handle(array $payload): void
    {
        if (! $this->config->isEnabled()) {
            return;
        }

        $resourceType = (string) ($payload['resource_type'] ?? $payload['ResourceType'] ?? '');
        $resourceUrl = (string) ($payload['resource_url'] ?? $payload['ResourceUrl'] ?? '');

        ShipStationIntegrationLog::query()->create([
            'request_type' => 'webhook_'.$resourceType,
            'success' => true,
            'context' => [
                'resource_type' => $resourceType,
                'resource_url' => $resourceUrl,
            ],
        ]);

        if (! in_array($resourceType, ['SHIP_NOTIFY', 'ORDER_NOTIFY'], true)) {
            return;
        }

        $fingerprint = $this->dedup->buildFingerprint($payload);

        if ($this->dedup->isStableIdDuplicateAlreadyProcessed($fingerprint, $payload)) {
            ShipStationIntegrationLog::query()->create([
                'request_type' => 'webhook_duplicate_delivery',
                'success' => true,
                'context' => [
                    'resource_type' => $resourceType,
                    'delivery_fingerprint' => $fingerprint,
                ],
            ]);

            return;
        }

        $receipt = ShipStationWebhookReceipt::query()->create([
            'delivery_fingerprint' => $fingerprint,
            'resource_type' => $resourceType,
            'resource_url' => $resourceUrl !== '' ? $resourceUrl : null,
            'status' => ShipStationWebhookReceipt::STATUS_RECEIVED,
            'outbox_status' => 'pending',
            'payload_meta' => [
                'resource_type' => $resourceType,
            ],
        ]);

        if ($this->reshipmentWebhookSync->tryHandleWebhook($payload)) {
            $receipt->update([
                'status' => ShipStationWebhookReceipt::STATUS_PROCESSED,
                'processed_at' => now(),
                'scope_type' => 'reshipment',
            ]);

            return;
        }

        $orderId = $this->resolver->resolveOrderIdFromPayload($payload);
        $order = $orderId !== null ? Order::query()->find($orderId) : null;

        if ($order === null) {
            $receipt->update([
                'status' => ShipStationWebhookReceipt::STATUS_IGNORED,
                'ignore_reason' => 'unresolved_order',
            ]);

            return;
        }

        $receipt->update([
            'order_id' => $order->id,
            'scope_type' => ShipStationWebhookReceipt::SCOPE_ORDER,
            'scope_id' => $order->id,
        ]);

        if ($this->syncExclusions->isExcluded($order)) {
            $receipt->update([
                'status' => ShipStationWebhookReceipt::STATUS_IGNORED,
                'ignore_reason' => 'sync_excluded_uuid',
            ]);

            return;
        }

        if ($this->pilotGate->allowsAutomaticSync($order)) {
            if ($resourceType === 'SHIP_NOTIFY') {
                $this->pilotSync->syncFromWebhook($order, $payload);
            } elseif ($resourceType === 'ORDER_NOTIFY') {
                $this->pilotSync->syncFromWebhook($order, $payload);
            }

            $receipt->update([
                'status' => ShipStationWebhookReceipt::STATUS_PROCESSED,
                'processed_at' => now(),
                'scope_type' => 'pilot',
            ]);

            return;
        }

        if (! (bool) config('shipstation.tracking_sync.enabled', false)) {
            $receipt->update([
                'status' => ShipStationWebhookReceipt::STATUS_IGNORED,
                'ignore_reason' => 'global_tracking_disabled',
            ]);

            return;
        }

        if (! $this->trackingEligibility->isOrderEligibleForGlobalTracking($order)) {
            $receipt->update([
                'status' => ShipStationWebhookReceipt::STATUS_IGNORED,
                'ignore_reason' => 'not_eligible_for_global_tracking',
            ]);

            return;
        }

        if ($this->dedup->isShortWindowBodyDuplicate($fingerprint, $payload)) {
            $receipt->update([
                'status' => ShipStationWebhookReceipt::STATUS_IGNORED,
                'ignore_reason' => 'duplicate_body_short_window',
            ]);

            return;
        }

        $this->dedup->rememberShortWindow($fingerprint);

        DB::afterCommit(function () use ($receipt): void {
            try {
                $this->trackingDispatcher->dispatchForReceipt($receipt->id);
                $receipt->update([
                    'status' => ShipStationWebhookReceipt::STATUS_DISPATCHED,
                    'outbox_status' => 'sent',
                    'dispatched_at' => now(),
                    'dispatch_attempts' => $receipt->dispatch_attempts + 1,
                ]);
            } catch (\Throwable $exception) {
                Log::warning('ShipStation tracking job dispatch failed', [
                    'receipt_id' => $receipt->id,
                    'message' => $exception->getMessage(),
                ]);
                $receipt->update([
                    'outbox_status' => 'pending',
                    'failure_reason' => $exception->getMessage(),
                ]);
            }
        });
    }
}