@php
    $rows = $rows ?? [];
    $formatMoney = $formatMoney ?? fn (float $value) => '$' . number_format($value, 2);

    $initialResultBadge = static function (?string $result): array {
        return match (strtolower((string) $result)) {
            'success' => ['label' => 'Success', 'class' => 'cp-badge--success'],
            'failure' => ['label' => 'Failure', 'class' => 'cp-badge--danger'],
            default => ['label' => ucfirst((string) $result) ?: '—', 'class' => 'cp-badge--muted'],
        };
    };

    $finalStatusBadge = static function (?string $key, ?string $label): array {
        return match ($key) {
            'successful_first_attempt' => ['label' => $label, 'class' => 'cp-badge--success'],
            'recovered_on_retry' => ['label' => $label, 'class' => 'cp-badge--success'],
            'recovery_in_progress' => ['label' => $label, 'class' => 'cp-badge--warning'],
            'unrecovered' => ['label' => $label, 'class' => 'cp-badge--danger'],
            default => ['label' => $label ?: '—', 'class' => 'cp-badge--muted'],
        };
    };
@endphp

<div class="cp-rebill-recovery-report__scroll" tabindex="0" role="region" aria-label="Rebill recovery report table">
    <table class="cp-rebill-recovery-report__table">
        <colgroup>
            <col class="cp-rebill-recovery-report__col cp-rebill-recovery-report__col--customer">
            <col class="cp-rebill-recovery-report__col cp-rebill-recovery-report__col--subscription">
            <col class="cp-rebill-recovery-report__col cp-rebill-recovery-report__col--campaign">
            <col class="cp-rebill-recovery-report__col cp-rebill-recovery-report__col--cycle">
            <col class="cp-rebill-recovery-report__col cp-rebill-recovery-report__col--initial">
            <col class="cp-rebill-recovery-report__col cp-rebill-recovery-report__col--retries">
            <col class="cp-rebill-recovery-report__col cp-rebill-recovery-report__col--status">
            <col class="cp-rebill-recovery-report__col cp-rebill-recovery-report__col--amount">
        </colgroup>
        <thead>
            <tr>
                <th scope="col">Customer</th>
                <th scope="col">Subscription</th>
                <th scope="col">Campaign</th>
                <th scope="col">Cycle</th>
                <th scope="col">Initial Result</th>
                <th scope="col">Retry Attempts</th>
                <th scope="col">Final Status</th>
                <th scope="col" class="cp-rebill-recovery-report__th--amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php
                    $initialBadge = $initialResultBadge($row['initial_result'] ?? null);
                    $finalBadge = $finalStatusBadge($row['final_status_key'] ?? null, $row['final_status'] ?? null);
                    $customerId = $row['customer_id'] ?? '';
                    $subscriptionId = $row['subscription_id'] ?? '';
                @endphp
                <tr class="cp-rebill-recovery-report__row">
                    <td class="cp-rebill-recovery-report__cell cp-rebill-recovery-report__cell--customer">
                        <div class="cp-rebill-recovery-report__primary">{{ $row['customer_name'] }}</div>
                        @if (filled($customerId))
                            <span
                                class="cp-rebill-recovery-report__id"
                                title="{{ $customerId }}"
                            >{{ $customerId }}</span>
                        @endif
                    </td>
                    <td class="cp-rebill-recovery-report__cell">
                        @if (filled($subscriptionId))
                            <span
                                class="cp-rebill-recovery-report__id cp-rebill-recovery-report__id--mono"
                                title="{{ $subscriptionId }}"
                            >{{ $subscriptionId }}</span>
                        @else
                            <span class="cp-rebill-recovery-report__muted">—</span>
                        @endif
                    </td>
                    <td class="cp-rebill-recovery-report__cell">
                        <span class="cp-rebill-recovery-report__campaign">{{ $row['campaign'] ?? '—' }}</span>
                    </td>
                    <td class="cp-rebill-recovery-report__cell cp-rebill-recovery-report__cell--cycle">
                        <span class="cp-rebill-recovery-report__cycle">Cycle {{ $row['cycle_step'] }}</span>
                        @if (filled($row['cycle_status_label'] ?? null))
                            <p class="mt-1 text-xs text-gray-500">{{ $row['cycle_status_label'] }}</p>
                        @endif
                    </td>
                    <td class="cp-rebill-recovery-report__cell">
                        <span class="cp-badge {{ $initialBadge['class'] }}">{{ $initialBadge['label'] }}</span>
                    </td>
                    <td class="cp-rebill-recovery-report__cell cp-rebill-recovery-report__cell--retries">
                        @php $retryAttempts = $row['retry_attempts'] ?? $row['attempts'] ?? []; @endphp
                        <p class="cp-rebill-recovery-report__retry-count">
                            {{ count($retryAttempts) }}
                            {{ count($retryAttempts) === 1 ? 'attempt' : 'attempts' }}
                        </p>
                        @if (! empty($retryAttempts))
                            <ul class="cp-rebill-recovery-report__attempts" aria-label="Retry attempt history for this cycle">
                                @foreach ($retryAttempts as $attempt)
                                    @php
                                        $outcome = strtolower((string) ($attempt['attempt_status'] ?? $attempt['outcome'] ?? ''));
                                        $isSuccess = in_array($outcome, ['success', 'successful'], true);
                                    @endphp
                                    <li @class([
                                        'cp-rebill-recovery-report__attempt',
                                        'cp-rebill-recovery-report__attempt--success' => $isSuccess,
                                        'cp-rebill-recovery-report__attempt--failure' => ! $isSuccess,
                                    ])>
                                        <div class="cp-rebill-recovery-report__attempt-head">
                                            <span @class([
                                                'cp-rebill-recovery-report__attempt-dot',
                                                'cp-rebill-recovery-report__attempt-dot--success' => $isSuccess,
                                                'cp-rebill-recovery-report__attempt-dot--failure' => ! $isSuccess,
                                            ]) aria-hidden="true"></span>
                                            <span class="cp-rebill-recovery-report__attempt-label">
                                                Attempt #{{ $attempt['retry_attempt_number'] ?? ($attempt['attempt_number'] ?? ($attempt['charge_sequence'] ?? '—')) }}
                                                · {{ $attempt['attempt_status_label'] ?? ucfirst($outcome ?: '—') }}
                                            </span>
                                        </div>
                                        @if (! $isSuccess && filled($attempt['failure_code'] ?? null))
                                            <span class="cp-rebill-recovery-report__failure-code">{{ $attempt['failure_code'] }}</span>
                                        @endif
                                        @if (! $isSuccess && filled($attempt['failure_message'] ?? null))
                                            <p class="cp-rebill-recovery-report__failure-message">{{ $attempt['failure_message'] }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </td>
                    <td class="cp-rebill-recovery-report__cell">
                        <span class="cp-badge {{ $finalBadge['class'] }}">{{ $finalBadge['label'] }}</span>
                    </td>
                    <td class="cp-rebill-recovery-report__cell cp-rebill-recovery-report__cell--amount">
                        <div class="cp-rebill-recovery-report__amount-collected">{{ $formatMoney((float) ($row['total_collected'] ?? 0)) }}</div>
                        <div class="cp-rebill-recovery-report__amount-scheduled">of {{ $formatMoney((float) ($row['original_amount'] ?? 0)) }}</div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="cp-rebill-recovery-report__empty">No rebill cycles match the selected period and filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>