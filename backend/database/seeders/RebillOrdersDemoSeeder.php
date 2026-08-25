<?php

namespace Database\Seeders;

use App\Models\BillingLedgerEntry;
use App\Models\Order;
use App\Models\Subscription;
use App\Modules\Order\Actions\CreateRebillOrderAction;
use App\Modules\Subscription\Enums\BillingLedgerOutcome;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class RebillOrdersDemoSeeder extends Seeder
{
    public function run(): void
    {
        $action = app(CreateRebillOrderAction::class);

        Subscription::query()
            ->with(['order', 'customer', 'product'])
            ->limit(6)
            ->get()
            ->each(function (Subscription $subscription, int $index) use ($action): void {
                foreach ([BillingLedgerOutcome::Success, BillingLedgerOutcome::Failure] as $outcomeOffset => $outcome) {
                    $cycleStep = max(1, (int) $subscription->current_cycle_step) + $outcomeOffset + 1;
                    $retryAttempt = $outcome === BillingLedgerOutcome::Failure ? 1 : 0;

                    $ledgerEntry = BillingLedgerEntry::query()->create([
                        'subscription_id' => $subscription->id,
                        'cycle_step' => $cycleStep,
                        'retry_attempt' => $retryAttempt,
                        'outcome' => $outcome,
                        'amount' => $subscription->amount ?? 9.99,
                        'currency' => $subscription->currency ?? 'USD',
                        'idempotency_key' => 'demo-rebill-'.$subscription->id.'-'.$cycleStep.'-'.$outcome->value,
                        'transaction_id' => $outcome === BillingLedgerOutcome::Success
                            ? 'pi_sandbox_'.Str::uuid()
                            : null,
                        'failure_code' => $outcome === BillingLedgerOutcome::Failure ? 'card_declined' : null,
                        'failure_message' => $outcome === BillingLedgerOutcome::Failure ? 'Demo failed rebill' : null,
                        'attempted_at' => now()->subDays($index + $outcomeOffset + 1),
                    ]);

                    $action->execute($subscription, $ledgerEntry);
                }
            });

        Order::query()
            ->whereNull('order_type')
            ->update(['order_type' => 'initial']);
    }
}
