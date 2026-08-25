<?php

namespace App\Modules\Admin\Services;

use App\Models\BillingLedgerEntry;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Support\Admin\AddressFormatter;

final class OrderRebillDetailsService
{
    public function formatAddress(mixed $address): string
    {
        return AddressFormatter::format($address);
    }

    public function orderShippingAddress(Order $order): string
    {
        return AddressFormatter::format($order->shipping_address ?: $order->customer?->shipping_address);
    }

    public function orderBillingAddress(Order $order): string
    {
        return AddressFormatter::format($order->billing_address ?: $order->customer?->billing_address);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rebillQueueRows(Order $order): array
    {
        return $order->subscriptions()
            ->with(['product', 'variant', 'plan', 'paymentGateway'])
            ->orderBy('id')
            ->get()
            ->map(fn (Subscription $subscription): array => [
                'subscription_uuid' => $subscription->uuid,
                'next_rebill_date' => $subscription->next_billing_at,
                'billing_cycle' => $this->formatBillingCycle($subscription),
                'amount' => number_format((float) $subscription->amount, 2).' '.strtoupper((string) $subscription->currency),
                'product' => trim(collect([
                    $subscription->product?->name,
                    $subscription->variant?->sku ? "({$subscription->variant->sku})" : null,
                ])->filter()->implode(' ')) ?: '—',
                'gateway' => $subscription->paymentGateway?->name
                    ?? ($subscription->payment_provider ? str($subscription->payment_provider)->headline() : '—'),
                'queue_status' => $subscription->rebill_queue_status?->value ?? '—',
                'retry_count' => (string) ($subscription->retry_count ?? 0),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rebillMessages(Order $order): array
    {
        $messages = collect();

        $order->transactions()
            ->with('paymentGateway')
            ->orderByDesc('processed_at')
            ->orderByDesc('created_at')
            ->each(function (Transaction $transaction) use ($messages): void {
                $messages->push([
                    'timestamp' => $transaction->processed_at ?? $transaction->created_at,
                    'event_type' => 'Payment · '.str($transaction->type)->headline(),
                    'status' => $this->stringifyStatus($transaction->status),
                    'amount' => number_format((float) $transaction->amount, 2).' '.strtoupper((string) $transaction->currency),
                    'transaction_id' => $transaction->external_id ?: $transaction->uuid,
                    'gateway' => $transaction->paymentGateway?->name ?? '—',
                    'gateway_response' => $this->summarizePayload($transaction->gateway_response),
                    'error_message' => $transaction->failure_message
                        ?: ($transaction->failure_code ?: '—'),
                    'retry_attempt' => '—',
                ]);
            });

        $subscriptionIds = $order->subscriptions()->pluck('id');

        if ($subscriptionIds->isNotEmpty()) {
            BillingLedgerEntry::query()
                ->whereIn('subscription_id', $subscriptionIds)
                ->orderByDesc('attempted_at')
                ->each(function (BillingLedgerEntry $entry) use ($messages): void {
                    $messages->push([
                        'timestamp' => $entry->attempted_at,
                        'event_type' => 'Rebill · Cycle '.$entry->cycle_step,
                        'status' => $entry->outcome?->value ?? '—',
                        'amount' => number_format((float) $entry->amount, 2).' '.strtoupper((string) $entry->currency),
                        'transaction_id' => $entry->transaction_id ?: '—',
                        'gateway' => '—',
                        'gateway_response' => $this->summarizePayload($entry->payload),
                        'error_message' => $entry->failure_message
                            ?: ($entry->failure_code ?: '—'),
                        'retry_attempt' => (string) ($entry->retry_attempt ?? 0),
                    ]);
                });
        }

        return $messages
            ->sortByDesc(fn (array $message): int => $this->timestampValue($message['timestamp'] ?? null))
            ->values()
            ->all();
    }

    private function formatBillingCycle(Subscription $subscription): string
    {
        $step = $subscription->current_cycle_step ?? 1;
        $planName = $subscription->plan?->name;

        if ($planName) {
            return "Step {$step} · {$planName}";
        }

        if ($subscription->billing_cycle_count) {
            return "Cycle {$step} of {$subscription->billing_cycle_count}";
        }

        return "Cycle {$step}";
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function summarizePayload(?array $payload): string
    {
        if ($payload === null || $payload === []) {
            return '—';
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if ($encoded === false) {
            return '—';
        }

        return strlen($encoded) > 240 ? substr($encoded, 0, 240).'…' : $encoded;
    }

    private function timestampValue(mixed $timestamp): int
    {
        if ($timestamp instanceof \DateTimeInterface) {
            return $timestamp->getTimestamp();
        }

        return 0;
    }

    private function stringifyStatus(mixed $status): string
    {
        if ($status instanceof \BackedEnum) {
            return (string) $status->value;
        }

        if ($status instanceof \UnitEnum) {
            return $status->name;
        }

        return (string) ($status ?? '—');
    }
}
