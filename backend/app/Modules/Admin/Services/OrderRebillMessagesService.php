<?php

namespace App\Modules\Admin\Services;

use App\Models\BillingLedgerEntry;
use App\Models\CheckoutPaymentFailure;
use App\Models\Order;
use App\Models\Transaction;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use BackedEnum;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class OrderRebillMessagesService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function messages(Order $order): Collection
    {
        $order->loadMissing([
            'subscriptions.billingLedgerEntries',
            'transactions.paymentGateway',
            'paymentGateway',
        ]);

        $messages = collect();

        foreach ($order->transactions as $transaction) {
            $messages->push($this->fromTransaction($transaction));
        }

        foreach ($order->subscriptions as $subscription) {
            foreach ($subscription->billingLedgerEntries as $entry) {
                $messages->push($this->fromBillingLedgerEntry($entry));
            }
        }

        foreach ($this->checkoutFailuresFor($order) as $failure) {
            $messages->push($this->fromCheckoutFailure($failure));
        }

        return $messages
            ->filter(fn (array $row): bool => filled($row['occurred_at'] ?? null))
            ->sortByDesc(fn (array $row): int => $this->timestampSortKey($row['occurred_at']))
            ->values();
    }

    /**
     * @return Collection<int, CheckoutPaymentFailure>
     */
    private function checkoutFailuresFor(Order $order): Collection
    {
        if (blank($order->customer_email)) {
            return collect();
        }

        $query = CheckoutPaymentFailure::query()
            ->where('customer_email', $order->customer_email);

        if ($order->placed_at !== null) {
            $query->where('created_at', '>=', $order->placed_at->copy()->subDay());
        }

        if ($order->grand_total !== null) {
            $query->where('amount', (string) $order->grand_total);
        }

        return $query->orderByDesc('created_at')->limit(10)->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function fromTransaction(Transaction $transaction): array
    {
        $status = $this->stringValue($transaction->status);
        $isFailure = in_array(strtolower($status), ['failed', 'declined', 'error'], true);

        return [
            'occurred_at' => $transaction->processed_at ?? $transaction->created_at,
            'source' => 'Checkout payment',
            'type' => $isFailure ? 'failure' : 'success',
            'title' => 'Transaction: '.$this->stringValue($transaction->type),
            'message' => $transaction->failure_message
                ?: strtoupper($status).' · '.number_format((float) $transaction->amount, 2).' '.$transaction->currency,
            'transaction_id' => $transaction->external_id,
            'retry_attempt' => null,
            'cycle_step' => null,
            'gateway_response' => $this->formatJson($transaction->gateway_response),
            'error_code' => $transaction->failure_code,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromBillingLedgerEntry(BillingLedgerEntry $entry): array
    {
        $outcome = $entry->outcome instanceof BillingLedgerOutcome
            ? $entry->outcome->value
            : (string) $entry->outcome;

        $isSuccess = $outcome === BillingLedgerOutcome::Success->value;

        return [
            'occurred_at' => $entry->attempted_at,
            'source' => 'Rebill charge',
            'type' => $isSuccess ? 'success' : 'failure',
            'title' => $isSuccess ? 'Rebill payment succeeded' : 'Rebill payment failed',
            'message' => $entry->failure_message
                ?: ($isSuccess
                    ? 'Captured '.number_format((float) $entry->amount, 2).' '.$entry->currency
                    : 'Charge attempt failed'),
            'transaction_id' => $entry->transaction_id,
            'retry_attempt' => $entry->retry_attempt,
            'cycle_step' => $entry->cycle_step,
            'gateway_response' => $this->formatJson($entry->payload),
            'error_code' => $entry->failure_code,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromCheckoutFailure(CheckoutPaymentFailure $failure): array
    {
        return [
            'occurred_at' => $failure->created_at,
            'source' => 'Checkout payment',
            'type' => 'failure',
            'title' => 'Checkout payment failed',
            'message' => $failure->failure_message,
            'transaction_id' => $failure->payment_intent_id,
            'retry_attempt' => null,
            'cycle_step' => null,
            'gateway_response' => $this->formatJson($failure->gateway_response),
            'error_code' => $failure->failure_code,
        ];
    }

    private function stringValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        return (string) $value;
    }

    private function formatJson(mixed $value): ?string
    {
        if ($value === null || $value === [] || $value === '') {
            return null;
        }

        if (is_string($value)) {
            return $value;
        }

        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: null;
    }

    private function timestampSortKey(mixed $value): int
    {
        if ($value instanceof CarbonInterface) {
            return $value->getTimestamp();
        }

        return strtotime((string) $value) ?: 0;
    }
}
