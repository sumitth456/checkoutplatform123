@php
    $histories = $histories ?? [];
    $formatMoney = $formatMoney ?? fn (float $value) => '$' . number_format($value, 2);
    $isSimulated = (bool) ($isSimulated ?? false);

    $cycleBadge = static fn (?string $status): array => match ($status) {
        'successful' => ['Successful', 'cp-badge--success'],
        'failed' => ['Failed', 'cp-badge--danger'],
        'pending' => ['Pending', 'cp-badge--warning'],
        'processing' => ['Processing', 'cp-badge--warning'],
        default => [ucfirst((string) $status) ?: '—', 'cp-badge--muted'],
    };

    $attemptBadge = static fn (?string $status): array => match ($status) {
        'successful' => ['Successful', 'cp-badge--success'],
        'failed' => ['Failed', 'cp-badge--danger'],
        default => ['—', 'cp-badge--muted'],
    };
@endphp

@forelse ($histories as $history)
    <article @class([
        'cp-rebill-nested-history rounded-xl border p-4',
        'border-amber-400/70 bg-amber-50/30 dark:bg-amber-950/20' => $isSimulated || ! empty($history['is_simulated']),
        'border-gray-200 dark:border-gray-700' => ! $isSimulated && empty($history['is_simulated']),
    ])>
        <header class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                @if (! empty($history['is_simulated']))
                    <span class="text-xs font-bold uppercase text-amber-800 dark:text-amber-200">Simulated</span>
                @endif
                <h4 class="font-semibold text-navy dark:text-white">{{ $history['customer_name'] ?? '—' }}</h4>
                <p class="text-xs text-gray-500">
                    Subscription <code>{{ $history['subscription_id'] ?? '—' }}</code>
                    @if (filled($history['campaign'] ?? null))
                        · {{ $history['campaign'] }}
                    @endif
                </p>
            </div>
        </header>

        <div class="space-y-4">
            @foreach ($history['rebill_cycles'] ?? [] as $cycle)
                @php
                    [$cycleLabel, $cycleClass] = $cycleBadge($cycle['cycle_status'] ?? null);
                @endphp
                <section class="cp-rebill-nested-history__cycle rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-base font-semibold text-navy dark:text-white">Cycle {{ $cycle['cycle_number'] ?? $cycle['cycle_step'] ?? '—' }}</span>
                        <span class="cp-badge {{ $cycleClass }}">{{ $cycleLabel }}</span>
                        @if (! empty($cycle['is_simulated']))
                            <span class="text-xs uppercase text-amber-600">Demo</span>
                        @endif
                    </div>
                    <dl class="mt-2 grid gap-2 text-xs text-gray-600 dark:text-gray-300 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <dt class="font-medium text-gray-500">Billing due</dt>
                            <dd>{{ isset($cycle['billing_due_at']) ? \Illuminate\Support\Carbon::parse($cycle['billing_due_at'])->format('M j, Y g:i A') : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Charge date</dt>
                            <dd>{{ isset($cycle['charge_at']) ? \Illuminate\Support\Carbon::parse($cycle['charge_at'])->format('M j, Y g:i A') : '—' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Amount</dt>
                            <dd>{{ $formatMoney((float) ($cycle['amount'] ?? 0)) }} {{ $cycle['currency'] ?? '' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Gateway / txn</dt>
                            <dd class="break-all">{{ $cycle['gateway'] ?? '—' }}@if (filled($cycle['transaction_reference'] ?? null)) · {{ $cycle['transaction_reference'] }}@endif</dd>
                        </div>
                    </dl>
                    @if (filled($cycle['gateway_response'] ?? null))
                        <p class="mt-2 text-xs text-gray-500">Gateway response: {{ $cycle['gateway_response'] }}</p>
                    @endif
                    @if (filled($cycle['billing_due_note'] ?? null))
                        <p class="mt-1 text-xs text-gray-400">{{ $cycle['billing_due_note'] }}</p>
                    @endif

                    <ul class="cp-rebill-nested-history__attempts mt-4 space-y-2 border-s-2 border-gray-200 ps-4 dark:border-gray-700">
                        @foreach ($cycle['retry_attempts'] ?? [] as $attempt)
                            @php
                                [$attemptLabel, $attemptClass] = $attemptBadge($attempt['attempt_status'] ?? null);
                            @endphp
                            <li class="text-sm">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-medium text-gray-800 dark:text-gray-100">
                                        Attempt {{ $attempt['retry_attempt_number'] ?? '—' }}
                                    </span>
                                    <span class="cp-badge {{ $attemptClass }}">{{ $attemptLabel }}</span>
                                </div>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ isset($attempt['attempted_at']) ? \Illuminate\Support\Carbon::parse($attempt['attempted_at'])->format('M j, Y g:i A') : '—' }}
                                    · {{ $formatMoney((float) ($attempt['amount'] ?? 0)) }}
                                    @if (filled($attempt['transaction_reference'] ?? null))
                                        · {{ $attempt['transaction_reference'] }}
                                    @endif
                                </p>
                                @if (($attempt['attempt_status'] ?? '') === 'failed' && filled($attempt['failure_code'] ?? null))
                                    <p class="text-xs text-danger-600 dark:text-danger-400">{{ $attempt['failure_code'] }}@if (filled($attempt['failure_message'] ?? null)) — {{ $attempt['failure_message'] }}@endif</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endforeach
        </div>
    </article>
@empty
    <p class="text-sm text-gray-500">{{ $emptyLabel ?? 'No cycle history for this filter.' }}</p>
@endforelse