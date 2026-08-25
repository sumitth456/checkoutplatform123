<?php

namespace Database\Seeders;

use App\Models\BillingLedgerEntry;
use App\Modules\Order\Actions\CreateRebillOrderAction;
use Illuminate\Database\Seeder;

class BackfillRebillOrdersSeeder extends Seeder
{
    public function run(): void
    {
        $action = app(CreateRebillOrderAction::class);

        BillingLedgerEntry::query()
            ->with(['subscription.customer', 'subscription.order', 'subscription.product'])
            ->orderBy('id')
            ->chunkById(100, function ($entries) use ($action): void {
                foreach ($entries as $entry) {
                    if ($entry->subscription === null) {
                        continue;
                    }

                    $action->execute($entry->subscription, $entry);
                }
            });
    }
}
