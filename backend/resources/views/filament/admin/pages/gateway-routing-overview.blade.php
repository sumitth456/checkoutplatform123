@php
    /** @var array<string, mixed> $plan */
    $hops = $plan['hops'] ?? [];
    $failoverSteps = $plan['failover_steps'] ?? [];
    $selected = $plan['selected'] ?? null;
@endphp

<div class="fi-section col-span-full rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">
                Live routing path
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Simulated selection for automatic checkout (no preferred provider). Active gateways are evaluated in priority order.
            </p>
        </div>
        @if ($selected)
            <span class="inline-flex items-center rounded-md bg-success-50 px-2 py-1 text-xs font-medium text-success-700 ring-1 ring-inset ring-success-600/20 dark:bg-success-400/10 dark:text-success-400">
                Current target: {{ $selected->name }}
            </span>
        @endif
    </div>

    @if ($hops === [])
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
            No active payment gateways configured. Add gateways under Payment Gateways or set a default provider in config.
        </p>
    @else
        <ol class="mt-6 flex flex-col gap-3">
            @foreach ($hops as $hop)
                @php
                    $status = $hop['status'] ?? 'standby';
                    $border = match ($status) {
                        'selected' => 'border-success-500 ring-success-500/20',
                        'skipped' => 'border-danger-500 ring-danger-500/20',
                        default => 'border-gray-200 dark:border-white/10',
                    };
                @endphp
                <li class="relative flex flex-col gap-2 rounded-lg border-l-4 bg-gray-50 p-4 {{ $border }} dark:bg-white/5">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            Priority {{ $hop['rank'] }}
                        </span>
                        <span class="font-medium text-gray-950 dark:text-white">{{ $hop['name'] }}</span>
                        <span class="rounded-md bg-gray-200/60 px-2 py-0.5 text-xs dark:bg-white/10">
                            {{ str($hop['provider'])->replace('_', ' ')->title() }}
                        </span>
                        @if ($status === 'selected')
                            <span class="rounded-md bg-success-100 px-2 py-0.5 text-xs font-medium text-success-800 dark:bg-success-400/20 dark:text-success-300">
                                Routes here
                            </span>
                        @elseif ($status === 'skipped')
                            <span class="rounded-md bg-danger-100 px-2 py-0.5 text-xs font-medium text-danger-800 dark:bg-danger-400/20 dark:text-danger-300">
                                Skipped
                            </span>
                        @else
                            <span class="rounded-md bg-warning-100 px-2 py-0.5 text-xs font-medium text-warning-800 dark:bg-warning-400/20 dark:text-warning-300">
                                Standby
                            </span>
                        @endif
                    </div>
                    @if (! empty($hop['reason']))
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $hop['reason'] }}</p>
                    @endif
                    @if (! empty($hop['stats']))
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            Daily volume {{ number_format($hop['stats']['daily_volume'], 2) }}
                            @if ($hop['stats']['daily_limit'] !== null)
                                / {{ number_format($hop['stats']['daily_limit'], 2) }} limit
                            @else
                                (no daily cap)
                            @endif
                            · Success today {{ $hop['stats']['success_rate_today'] }}%
                        </p>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif

    @if ($failoverSteps !== [])
        <div class="mt-8 border-t border-gray-200 pt-6 dark:border-white/10">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Failover escalation</h3>
            <ul class="mt-3 space-y-2 text-sm text-gray-600 dark:text-gray-300">
                @foreach ($failoverSteps as $step)
                    <li class="flex items-start gap-2">
                        <span class="mt-1 h-1.5 w-1.5 shrink-0 rounded-full bg-amber-500"></span>
                        @if (($step['type'] ?? '') === 'default_gateway' && isset($step['gateway']))
                            <span>Default gateway override: <strong>{{ $step['gateway']->name }}</strong></span>
                        @elseif (($step['type'] ?? '') === 'config_fallback')
                            <span>Config fallback provider: <strong>{{ str($step['provider'])->replace('_', ' ')->title() }}</strong></span>
                        @else
                            <span>{{ $step['label'] ?? 'Additional failover step' }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
