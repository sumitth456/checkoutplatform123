<?php

namespace App\Modules\Order\Services;

use App\Models\Order;
use App\Models\Transaction;

class OrderTestDetector
{
    public function isTestOrder(Order $order): bool
    {
        if (($order->metadata['shipstation_test_order'] ?? false) === true) {
            return true;
        }

        $orderNumber = (string) ($order->order_number ?? '');

        if ($orderNumber !== '' && str_starts_with($orderNumber, 'SS-TEST-')) {
            return true;
        }

        return $this->hasSandboxCaptureTransaction($order);
    }

    public function hasSandboxCaptureTransaction(Order $order): bool
    {
        $transaction = $order->relationLoaded('transactions')
            ? $order->transactions
                ->whereIn('type', ['capture', 'charge', 'sale', 'payment'])
                ->sortByDesc('processed_at')
                ->first()
            : $order->transactions()
                ->whereIn('type', ['capture', 'charge', 'sale', 'payment'])
                ->orderByDesc('processed_at')
                ->first();

        if (! $transaction instanceof Transaction) {
            return false;
        }

        return $this->isSandboxTransaction($transaction);
    }

    public function isSandboxTransaction(Transaction $transaction): bool
    {
        $externalId = (string) ($transaction->external_id ?? '');

        if (str_starts_with($externalId, 'pi_sandbox_')) {
            return true;
        }

        if (str_contains($externalId, 'sandbox_ach')) {
            return true;
        }

        $response = $transaction->gateway_response ?? [];

        if (str_starts_with((string) ($response['payment_intent_id'] ?? ''), 'pi_sandbox_')) {
            return true;
        }

        if (($response['sandbox'] ?? false) === true) {
            return true;
        }

        if (($response['capture']['metadata']['sandbox'] ?? false) === true) {
            return true;
        }

        return ($response['capture']['raw']['sandbox'] ?? false) === true;
    }
}
