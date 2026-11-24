<?php

namespace App\Modules\ShipStation\Services;

use App\Models\ShipStationWebhookReceipt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class ShipStationWebhookReceiptRecoveryService
{
    public function __construct(
        private readonly ShipStationTrackingJobDispatcher $dispatcher,
    ) {}

    public function recoverStuckReceipts(int $minutes): int
    {
        $cutoff = now()->subMinutes(max(1, $minutes));

        $ids = ShipStationWebhookReceipt::query()
            ->whereIn('status', [
                ShipStationWebhookReceipt::STATUS_RECEIVED,
                ShipStationWebhookReceipt::STATUS_FAILED,
            ])
            ->where(function ($query) use ($cutoff): void {
                $query->where('outbox_status', 'pending')
                    ->orWhere(function ($nested) use ($cutoff): void {
                        $nested->where('outbox_status', 'claiming')
                            ->where('updated_at', '<', $cutoff);
                    });
            })
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('order_id')
            ->orderBy('id')
            ->limit(100)
            ->pluck('id');

        $dispatched = 0;

        foreach ($ids as $id) {
            if ($this->recoverReceiptById((int) $id, $minutes)) {
                $dispatched++;
            }
        }

        return $dispatched;
    }

    public function tryClaimReceiptForRecovery(int $receiptId, int $staleClaimMinutes = 2): ?ShipStationWebhookReceipt
    {
        return DB::transaction(function () use ($receiptId, $staleClaimMinutes): ?ShipStationWebhookReceipt {
            /** @var ShipStationWebhookReceipt|null $receipt */
            $receipt = ShipStationWebhookReceipt::query()
                ->whereKey($receiptId)
                ->lockForUpdate()
                ->first();

            if ($receipt === null) {
                return null;
            }

            if (! in_array($receipt->status, [
                ShipStationWebhookReceipt::STATUS_RECEIVED,
                ShipStationWebhookReceipt::STATUS_FAILED,
            ], true)) {
                return null;
            }

            if ($receipt->outbox_status === 'claiming') {
                if ($receipt->updated_at > now()->subMinutes(max(1, $staleClaimMinutes))) {
                    return null;
                }
            } elseif ($receipt->outbox_status !== 'pending') {
                return null;
            }

            $receipt->update(['outbox_status' => 'claiming']);

            return $receipt->fresh();
        });
    }

    public function recoverReceiptById(int $receiptId, int $staleClaimMinutes = 2): bool
    {
        $receipt = $this->tryClaimReceiptForRecovery($receiptId, $staleClaimMinutes);

        if ($receipt === null) {
            return false;
        }

        try {
            $this->dispatcher->dispatchForReceipt($receipt->id);

            $receipt->update([
                'status' => ShipStationWebhookReceipt::STATUS_DISPATCHED,
                'outbox_status' => 'sent',
                'dispatched_at' => now(),
                'dispatch_attempts' => $receipt->dispatch_attempts + 1,
                'failure_reason' => null,
            ]);

            return true;
        } catch (\Throwable $exception) {
            Log::warning('ShipStation webhook receipt recovery dispatch failed', [
                'receipt_id' => $receipt->id,
                'message' => $exception->getMessage(),
            ]);

            $receipt->update([
                'outbox_status' => 'pending',
                'failure_reason' => $exception->getMessage(),
            ]);

            return false;
        }
    }
}