@php
    $journey = $journey ?? [];
    $formatMoney = $formatMoney ?? fn (float $value) => '$' . number_format($value, 2);
    $timeline = $journey['timeline'] ?? [];
    $badgeClass = static fn (string $status): string => match ($status) {
        'successful' => 'cp-badge--success',
        'failed' => 'cp-badge--danger',
        'pending' => 'cp-badge--warning',
        default => 'cp-badge--muted',
    };
@endphp

<div @class([
    'mt-6 rounded-xl border border-gray-200 p-4 dark:border-gray-700',
    'border-dashed border-amber-400/60' => ! empty($journey['is_simulated']),
])>
    <div class="flex flex-col gap-1 text-sm sm:flex-row sm:items-center sm:justify-between">
        <div>
            @if (! empty($journey['is_simulated']))
                <span class="text-xs font-semibold uppercase text-amber-700 dark:text-amber-300">Simulated</span>
            @endif
            <p class="font-medium text-navy dark:text-white">{{ $journey['customer_reference'] ?? '—' }}</p>
            <p class="text-xs text-gray-500">Subscription: <code>{{ $journey['subscription_id'] ?? '—' }}</code></p>
        </div>
        <p class="text-gray-600 dark:text-gray-300">{{ $journey['recovery_outcome'] ?? '' }}</p>
    </div>

    <ol class="cp-rebill-recovery-journey mt-4 space-y-0">
        @foreach ($timeline as $index => $step)
            @php
                $status = (string) ($step['status_badge'] ?? 'pending');
                $badge = $badgeClass($status);
                $isLast = $index === count($timeline) - 1;
            @endphp
            <li class="cp-rebill-recovery-journey__step">
                <div class="cp-rebill-recovery-journey__rail" aria-hidden="true">
                    <span @class([
                        'cp-rebill-recovery-journey__dot',
                        'cp-rebill-recovery-journey__dot--success' => $status === 'successful',
                        'cp-rebill-recovery-journey__dot--failure' => $status === 'failed',
                        'cp-rebill-recovery-journey__dot--pending' => $status === 'pending',
                    ])></span>
                    @if (! $isLast)
                        <span class="cp-rebill-recovery-journey__connector"></span>
                    @endif
                </div>
                <div class="cp-rebill-recovery-journey__body text-sm">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-medium">{{ $step['description'] ?? ('Attempt #' . ($step['rebill_attempt_number'] ?? '—')) }}</span>
                        <span class="cp-badge {{ $badge }}">{{ ucfirst($status) }}</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Attempt #{{ $step['rebill_attempt_number'] ?? '—' }}
                        · {{ isset($step['attempted_at']) ? \Illuminate\Support\Carbon::parse($step['attempted_at'])->format('M j, Y') : '—' }}
                        · {{ $formatMoney((float) ($step['amount'] ?? 0)) }}
                        · {{ $step['gateway'] ?? '—' }}
                        @if (filled($step['transaction_reference'] ?? null))
                            · Txn {{ $step['transaction_reference'] }}
                        @endif
                    </p>
                </div>
            </li>
        @endforeach
    </ol>
</div>