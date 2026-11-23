@php
    $showcase = $showcase ?? null;
    $formatMoney = $formatMoney ?? fn (float $value) => '$' . number_format($value, 2);
    if (empty($showcase) || empty($showcase['is_simulated'])) {
        return;
    }
    $cycleAnalytics = $showcase['cycle_analytics'] ?? [];
    $attemptAnalytics = $showcase['attempt_analytics'] ?? [];
@endphp

<section class="rounded-2xl border-2 border-dashed border-amber-400/80 bg-amber-50/50 p-6 dark:border-amber-500/50 dark:bg-amber-950/20">
    <header class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-xs font-bold uppercase tracking-wide text-amber-800 dark:text-amber-200">{{ $showcase['banner'] ?? 'SIMULATED' }}</p>
            <h3 class="mt-1 text-lg font-semibold text-navy dark:text-white">Cycle &amp; retry showcase (demo)</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Examples: multi-retry recovery, first-attempt success, and exhausted retries — excluded from live KPIs.</p>
        </div>
        <p class="text-sm text-gray-600 dark:text-gray-400">
            <span class="font-medium">Customer:</span> {{ $showcase['customer_reference'] ?? '—' }}
        </p>
    </header>

    <div class="mt-4 grid gap-4 lg:grid-cols-2">
        <article class="rounded-xl border border-amber-300/50 bg-white/80 p-4 text-sm dark:bg-gray-900">
            <h4 class="font-semibold text-navy dark:text-white">Demo cycle analytics</h4>
            <ul class="mt-2 space-y-1 text-gray-600 dark:text-gray-300">
                <li>Successful cycles: {{ $cycleAnalytics['successful_cycles'] ?? 0 }} / {{ $cycleAnalytics['total_cycles'] ?? 0 }}</li>
                <li>Failed cycles: {{ $cycleAnalytics['failed_cycles'] ?? 0 }}</li>
                <li>Cycle 1: {{ ($cycleAnalytics['by_rebill_cycle']['cycle_1']['successful'] ?? 0) ? 'success' : '—' }} · Cycle 2: success · Cycle 3: failed</li>
            </ul>
        </article>
        <article class="rounded-xl border border-amber-300/50 bg-white/80 p-4 text-sm dark:bg-gray-900">
            <h4 class="font-semibold text-navy dark:text-white">Demo retry attempt analytics</h4>
            <ul class="mt-2 space-y-1 text-gray-600 dark:text-gray-300">
                <li>Total attempts: {{ $attemptAnalytics['total_retry_attempts'] ?? 0 }}</li>
                <li>Successful: {{ $attemptAnalytics['successful_retry_attempts'] ?? 0 }} · Failed: {{ $attemptAnalytics['failed_retry_attempts'] ?? 0 }}</li>
                <li>Recovery rate after retries: {{ number_format((float) ($attemptAnalytics['recovery_rate_after_retries'] ?? 0), 1) }}%</li>
            </ul>
        </article>
    </div>

    <div class="mt-6">
        @include('filament.admin.pages.partials.rebill-recovery-nested-cycles', [
            'histories' => $showcase['subscription_histories'] ?? [],
            'formatMoney' => $formatMoney,
            'isSimulated' => true,
        ])
    </div>
</section>