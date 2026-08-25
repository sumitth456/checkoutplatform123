<?php

namespace App\Modules\Admin\Services;

use App\Models\Transaction;

class TransactionLedgerService
{
    /**
     * @return array{all: int, successful: int, failed: int, refunds: int}
     */
    public function tabCounts(): array
    {
        return [
            'all' => Transaction::query()->count(),
            'successful' => Transaction::query()->whereIn('status', $this->successfulStatuses())->count(),
            'failed' => Transaction::query()->where('status', 'failed')->count(),
            'refunds' => Transaction::query()->where(function ($query): void {
                $query->where('type', 'refund')
                    ->orWhere('status', 'refunded');
            })->count(),
        ];
    }

    /**
     * @return list<string>
     */
    public function successfulStatuses(): array
    {
        return ['captured', 'succeeded', 'completed', 'paid'];
    }
}
