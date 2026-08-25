<?php

namespace App\Modules\Admin\Services;

use App\Models\Order;
use App\Models\Transaction;
use App\Modules\Order\Enums\OrderPaymentStatus;
use Illuminate\Support\Collection;

class OrderTimelineService
{
    /**
     * @return Collection<int, object{id: string, occurred_at: string, type: string, title: string, detail: ?string}>
     */
    public function entries(Order $order): Collection
    {
        $order->loadMissing(['transactions', 'fulfillments', 'adminActivityLogs.causer']);

        $entries = collect();

        if ($order->placed_at !== null) {
            $entries->push($this->entry('placed', $order->placed_at, 'Order placed'));
        }

        if ($order->paid_at !== null) {
            $entries->push($this->entry('paid', $order->paid_at, 'Payment captured'));
        }

        foreach ($order->transactions()->orderBy('processed_at')->get() as $transaction) {
            if ($transaction->processed_at === null) {
                continue;
            }

            $entries->push($this->entry(
                'transaction_'.$transaction->type,
                $transaction->processed_at,
                'Transaction: '.$transaction->type,
                strtoupper((string) $transaction->status).' · '.number_format((float) $transaction->amount, 2).' '.$transaction->currency,
            ));
        }

        foreach ($order->fulfillments()->orderBy('created_at')->get() as $fulfillment) {
            $entries->push($this->entry(
                'fulfillment_'.$fulfillment->status?->value,
                $fulfillment->created_at,
                'Fulfillment: '.($fulfillment->status?->value ?? 'updated'),
                filled($fulfillment->tracking_number)
                    ? 'Tracking '.$fulfillment->tracking_number.' ('.($fulfillment->carrier ?? 'carrier n/a').')'
                    : null,
            ));
        }

        if ($order->fulfilled_at !== null) {
            $entries->push($this->entry('fulfilled', $order->fulfilled_at, 'Order fulfilled'));
        }

        if ($order->cancelled_at !== null) {
            $entries->push($this->entry(
                'cancelled',
                $order->cancelled_at,
                'Order cancelled',
                $order->cancellation_reason,
            ));
        }

        if ((float) $order->refunded_total > 0) {
            $entries->push($this->entry(
                'refund',
                $order->updated_at,
                'Refunds recorded',
                number_format((float) $order->refunded_total, 2).' '.$order->currency,
            ));
        }

        foreach ($order->adminActivityLogs as $log) {
            $entries->push($this->entry(
                'admin_'.$log->event,
                $log->created_at,
                'Admin: '.$log->event,
                $log->description,
            ));
        }

        return $entries
            ->sortBy(fn ($row) => $row->occurred_at)
            ->values()
            ->map(function ($row, int $index) {
                $row->id = (string) $index;

                return $row;
            });
    }

    private function entry(string $type, $occurredAt, string $title, ?string $detail = null): object
    {
        return (object) [
            'id' => $type.'-'.$occurredAt,
            'occurred_at' => $occurredAt,
            'type' => $type,
            'title' => $title,
            'detail' => $detail,
        ];
    }
}
