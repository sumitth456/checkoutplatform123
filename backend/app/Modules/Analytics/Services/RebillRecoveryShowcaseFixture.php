<?php

namespace App\Modules\Analytics\Services;

use Illuminate\Support\Carbon;

/**
 * In-memory recovery journey for dashboard demonstration — never persisted.
 */
final class RebillRecoveryShowcaseFixture
{
    /**
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $amount = 29.99;
        $currency = 'USD';
        $base = Carbon::now()->subDays(60)->startOfDay();
        $gateway = 'PayPal (SIMULATED)';

        $cycles = [
            $this->simulatedCycle(
                cycleNumber: 1,
                status: 'successful',
                dueAt: $base->copy(),
                chargeAt: $base->copy()->addDays(4),
                attempts: [
                    ['failed', $base, 'SIM-MOCK-C1-A1', 'insufficient_funds', 'Demo decline'],
                    ['failed', $base->copy()->addDays(1), 'SIM-MOCK-C1-A2', 'insufficient_funds', 'Demo decline'],
                    ['successful', $base->copy()->addDays(4), 'SIM-MOCK-C1-A3', null, null],
                ],
                amount: $amount,
                currency: $currency,
                gateway: $gateway,
            ),
            $this->simulatedCycle(
                cycleNumber: 2,
                status: 'successful',
                dueAt: $base->copy()->addDays(30),
                chargeAt: $base->copy()->addDays(30),
                attempts: [
                    ['successful', $base->copy()->addDays(30), 'SIM-MOCK-C2-A1', null, null],
                ],
                amount: $amount,
                currency: $currency,
                gateway: $gateway,
            ),
            $this->simulatedCycle(
                cycleNumber: 3,
                status: 'failed',
                dueAt: $base->copy()->addDays(60),
                chargeAt: null,
                attempts: [
                    ['failed', $base->copy()->addDays(60), 'SIM-MOCK-C3-A1', 'card_declined', 'Demo exhausted retries'],
                    ['failed', $base->copy()->addDays(62), 'SIM-MOCK-C3-A2', 'card_declined', 'Demo exhausted retries'],
                ],
                amount: $amount,
                currency: $currency,
                gateway: $gateway,
            ),
        ];

        return [
            'is_simulated' => true,
            'banner' => 'SIMULATED — Demonstration data only. Not included in live financial KPIs or exports.',
            'subscription_id' => 'DEMO-SUB-RECOVERY-7F3A',
            'customer_reference' => 'Demo Member (J. Rivera) · demo-member-7f3a@example.com',
            'subscription_histories' => [
                [
                    'is_simulated' => true,
                    'subscription_id' => 'DEMO-SUB-RECOVERY-7F3A',
                    'customer_name' => 'Demo Member (J. Rivera)',
                    'rebill_cycles' => $cycles,
                ],
            ],
            'cycle_analytics' => [
                'is_simulated' => true,
                'total_cycles' => 3,
                'successful_cycles' => 2,
                'failed_cycles' => 1,
                'pending_cycles' => 0,
                'by_rebill_cycle' => [
                    'cycle_1' => ['total' => 1, 'successful' => 1, 'failed' => 0, 'pending' => 0],
                    'cycle_2' => ['total' => 1, 'successful' => 1, 'failed' => 0, 'pending' => 0],
                    'cycle_3' => ['total' => 1, 'successful' => 0, 'failed' => 1, 'pending' => 0],
                    'cycle_4_plus' => ['total' => 0, 'successful' => 0, 'failed' => 0, 'pending' => 0],
                ],
            ],
            'attempt_analytics' => [
                'is_simulated' => true,
                'total_retry_attempts' => 6,
                'successful_retry_attempts' => 2,
                'failed_retry_attempts' => 4,
                'attempt_success_rate' => 33.33,
                'recovery_rate_after_retries' => 100.0,
            ],
        ];
    }

    /**
     * @param  list<array{0: string, 1: Carbon, 2: string, 3: ?string, 4: ?string}>  $attempts
     * @return array<string, mixed>
     */
    private function simulatedCycle(
        int $cycleNumber,
        string $status,
        Carbon $dueAt,
        ?Carbon $chargeAt,
        array $attempts,
        float $amount,
        string $currency,
        string $gateway,
    ): array {
        $retryAttempts = [];
        foreach ($attempts as $index => [$outcome, $at, $txn, $code, $message]) {
            $failed = $outcome === 'failed';
            $retryAttempts[] = [
                'retry_attempt_number' => $index + 1,
                'ledger_retry_attempt' => $index,
                'attempted_at' => $at->toIso8601String(),
                'amount' => $amount,
                'currency' => $currency,
                'attempt_status' => $outcome,
                'attempt_status_label' => $failed ? 'Failed' : 'Successful',
                'transaction_reference' => $txn,
                'failure_code' => $code,
                'failure_message' => $message,
                'gateway' => $gateway,
                'idempotency_key' => 'SIM-MOCK-'.$txn,
            ];
        }

        $lastSuccess = collect($retryAttempts)->last(fn (array $a) => $a['attempt_status'] === 'successful');

        return [
            'is_simulated' => true,
            'cycle_number' => $cycleNumber,
            'cycle_identifier' => 'demo:7f3a:'.$cycleNumber,
            'billing_due_at' => $dueAt->toIso8601String(),
            'billing_due_note' => 'Simulated due date for demonstration.',
            'charge_at' => $chargeAt?->toIso8601String(),
            'amount' => $amount,
            'collected_amount' => $status === 'successful' ? $amount : 0,
            'currency' => $currency,
            'cycle_status' => $status,
            'cycle_status_label' => ucfirst($status),
            'transaction_reference' => $lastSuccess['transaction_reference'] ?? null,
            'gateway' => $gateway,
            'gateway_response' => $status === 'failed' ? 'All retry attempts exhausted (demo)' : null,
            'retry_attempts' => $retryAttempts,
        ];
    }
}