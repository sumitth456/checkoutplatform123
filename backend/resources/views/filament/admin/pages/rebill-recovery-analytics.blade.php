<x-filament-panels::page>
    @php
        $options = $this->filterOptions();
        $data = $this->safeSnapshot();
        $kpis = $data['kpis'] ?? [];
        $rates = $data['rates'] ?? [];
        $recovery = $data['recovery_by_attempt'] ?? [];
        $recoverySequence = $data['recovery_sequence'] ?? [];
        $paymentAttemptStats = $data['payment_attempt_statistics'] ?? [];
        $featuredJourneys = $data['featured_journeys'] ?? [];
        $cycleAnalytics = $data['cycle_analytics'] ?? [];
        $attemptAnalytics = $data['attempt_analytics'] ?? [];
        $subscriptionHistories = $data['subscription_histories'] ?? [];
        $showcase = $data['showcase'] ?? null;
        $charts = $data['charts'] ?? [];
        $allReportRows = $data['rows'] ?? [];
        $reportMeta = $data['report'] ?? [];
        $reportPagination = $this->paginateReportRows($allReportRows);
        $rows = $reportPagination['rows'];
        $limitations = $data['limitations'] ?? [];
        $widgetData = $this->getWidgetData();
        $kpiFilter = $this->kpiFilter ?? $this->reportFilter;
        $reportFilterLabel = $this->reportFilterLabel($kpiFilter);
        $error = ! empty($data['error']);

        $formatMoney = fn (float $value) => '$' . number_format($value, 2);

        $kpiCards = [
            ['key' => 'due', 'label' => 'Total rebills due', 'value' => $kpis['total_due'] ?? 0, 'format' => 'number'],
            ['key' => 'attempted', 'label' => 'Total rebills attempted', 'value' => $kpis['total_attempted'] ?? 0, 'format' => 'number'],
            ['key' => 'failed_first', 'label' => 'Total failed rebills', 'value' => $kpis['failed_first_attempt'] ?? 0, 'format' => 'number'],
            ['key' => 'recovered', 'label' => 'Successfully recovered', 'value' => $kpis['recovered_after_retry'] ?? 0, 'format' => 'number'],
            ['key' => null, 'label' => 'Second attempt success', 'value' => $kpis['second_attempt_success'] ?? 0, 'format' => 'number'],
            ['key' => null, 'label' => 'Third attempt success', 'value' => $kpis['third_attempt_success'] ?? 0, 'format' => 'number'],
            ['key' => 'success_first', 'label' => 'Successful on first attempt', 'value' => $kpis['successful_first_attempt'] ?? 0, 'format' => 'number'],
            ['key' => 'unrecovered', 'label' => 'Permanently failed / unrecovered', 'value' => $kpis['permanently_unrecovered'] ?? 0, 'format' => 'number'],
            ['key' => 'in_progress', 'label' => 'Pending recovery', 'value' => $kpis['pending_recovery'] ?? 0, 'format' => 'number'],
            ['key' => null, 'label' => 'Recovery success rate', 'value' => $kpis['recovery_success_rate'] ?? 0, 'format' => 'percent'],
            ['key' => null, 'label' => 'Overall rebill success rate', 'value' => $kpis['overall_success_rate'] ?? 0, 'format' => 'percent'],
            ['key' => null, 'label' => 'Revenue recovered (retries)', 'value' => $kpis['revenue_recovered_retries'] ?? 0, 'format' => 'currency'],
            ['key' => null, 'label' => 'Revenue lost (unrecovered)', 'value' => $kpis['revenue_lost_unrecovered'] ?? 0, 'format' => 'currency'],
        ];
    @endphp

    <div class="space-y-8" wire:loading.class="opacity-60">
        @include('filament.admin.pages.analytics-filters', ['options' => $options])

        @if ($error)
            <div class="rounded-2xl border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-800 dark:border-danger-500/40 dark:bg-danger-500/10 dark:text-danger-200">
                Unable to load rebill recovery analytics. Adjust filters or try again.
            </div>
        @endif

        @if (filled($showcase))
            @include('filament.admin.pages.partials.rebill-recovery-showcase', [
                'showcase' => $showcase,
                'formatMoney' => $formatMoney,
            ])
        @endif

        @if (count($featuredJourneys) > 0)
            <section class="rounded-2xl border border-stone bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold text-navy dark:text-white">Recovery journeys (live data)</h3>
                <p class="mt-1 text-sm text-gray-500">Subscriptions that failed a rebill, recovered on retry, then collected the next cycle — from billing ledger records in this period.</p>
                @foreach ($featuredJourneys as $journey)
                    @include('filament.admin.pages.partials.rebill-recovery-journey-timeline', [
                        'journey' => $journey,
                        'formatMoney' => $formatMoney,
                    ])
                @endforeach
            </section>
        @endif

        @if (filled($cycleAnalytics) && empty($cycleAnalytics['is_simulated']))
            <section class="rounded-2xl border border-stone bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold text-navy dark:text-white">Rebill cycle analytics <span class="text-sm font-normal text-gray-500">(ledger: subscription_id + cycle_step)</span></h3>
                <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/80">
                        <dt class="text-xs text-gray-500">Successful cycles</dt>
                        <dd class="text-xl font-semibold">{{ number_format((int) ($cycleAnalytics['successful_cycles'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/80">
                        <dt class="text-xs text-gray-500">Failed cycles</dt>
                        <dd class="text-xl font-semibold text-danger-600">{{ number_format((int) ($cycleAnalytics['failed_cycles'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/80">
                        <dt class="text-xs text-gray-500">Pending cycles</dt>
                        <dd class="text-xl font-semibold text-amber-600">{{ number_format((int) ($cycleAnalytics['pending_cycles'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/80">
                        <dt class="text-xs text-gray-500">Total cycles in period</dt>
                        <dd class="text-xl font-semibold">{{ number_format((int) ($cycleAnalytics['total_cycles'] ?? 0)) }}</dd>
                    </div>
                </dl>
                <table class="mt-4 w-full text-left text-sm">
                    <thead class="text-xs uppercase text-gray-500">
                        <tr>
                            <th class="py-2 pe-4">Rebill cycle</th>
                            <th class="py-2 pe-4">Total</th>
                            <th class="py-2 pe-4">Successful</th>
                            <th class="py-2 pe-4">Failed</th>
                            <th class="py-2">Pending</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach (['cycle_1' => 'Cycle 1', 'cycle_2' => 'Cycle 2', 'cycle_3' => 'Cycle 3', 'cycle_4_plus' => 'Cycle 4+'] as $key => $label)
                            @php $row = $cycleAnalytics['by_rebill_cycle'][$key] ?? []; @endphp
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="py-2 pe-4 font-medium">{{ $label }}</td>
                                <td class="py-2 pe-4">{{ number_format((int) ($row['total'] ?? 0)) }}</td>
                                <td class="py-2 pe-4 text-success-600">{{ number_format((int) ($row['successful'] ?? 0)) }}</td>
                                <td class="py-2 pe-4 text-danger-600">{{ number_format((int) ($row['failed'] ?? 0)) }}</td>
                                <td class="py-2">{{ number_format((int) ($row['pending'] ?? 0)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </section>
        @endif

        @if (filled($attemptAnalytics) && empty($attemptAnalytics['is_simulated']))
            <section class="rounded-2xl border border-stone bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold text-navy dark:text-white">Retry attempt analytics <span class="text-sm font-normal text-gray-500">(payment attempts within cycles)</span></h3>
                <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/80">
                        <dt class="text-xs text-gray-500">Successful attempts</dt>
                        <dd class="text-xl font-semibold">{{ number_format((int) ($attemptAnalytics['successful_retry_attempts'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/80">
                        <dt class="text-xs text-gray-500">Failed attempts</dt>
                        <dd class="text-xl font-semibold">{{ number_format((int) ($attemptAnalytics['failed_retry_attempts'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/80">
                        <dt class="text-xs text-gray-500">Attempt success rate</dt>
                        <dd class="text-xl font-semibold">{{ number_format((float) ($attemptAnalytics['attempt_success_rate'] ?? 0), 1) }}%</dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/80">
                        <dt class="text-xs text-gray-500">Recovery rate after retries</dt>
                        <dd class="text-xl font-semibold">{{ number_format((float) ($attemptAnalytics['recovery_rate_after_retries'] ?? 0), 1) }}%</dd>
                    </div>
                </dl>
            </section>
        @endif

        <section class="cp-dashboard-section">
            <header class="cp-dashboard-section__header">
                <h2 class="cp-dashboard-section__title">Rebill Recovery &amp; Retry Analytics</h2>
                <p class="cp-dashboard-section__subtitle">Aggregate KPIs — cycle outcomes and retry outcomes reported separately above</p>
            </header>

            <div class="cp-dashboard-kpis cp-dashboard-kpis--primary">
                @foreach ($kpiCards as $card)
                    @php
                        $formatted = match ($card['format']) {
                            'currency' => $formatMoney((float) $card['value']),
                            'percent' => number_format((float) $card['value'], 1) . '%',
                            default => number_format((int) $card['value']),
                        };
                    @endphp
                    @if ($card['key'])
                        <a href="{{ $this->kpiUrl($card['key']) }}" wire:navigate @class(['cp-dashboard-kpi cp-dashboard-kpi--compact', 'ring-2 ring-primary-500' => $kpiFilter === $card['key']])>
                            <div class="cp-dashboard-kpi__head">
                                <span class="cp-dashboard-kpi__label">{{ $card['label'] }}</span>
                            </div>
                            <div class="cp-dashboard-kpi__value">{{ $formatted }}</div>
                        </a>
                    @else
                        <div class="cp-dashboard-kpi cp-dashboard-kpi--compact">
                            <div class="cp-dashboard-kpi__head">
                                <span class="cp-dashboard-kpi__label">{{ $card['label'] }}</span>
                            </div>
                            <div class="cp-dashboard-kpi__value">{{ $formatted }}</div>
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <article class="rounded-xl border border-stone bg-white px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">First-attempt success rate</p>
                    <p class="mt-1 text-2xl font-semibold text-navy dark:text-white">{{ number_format((float) ($rates['first_attempt_success_rate'] ?? 0), 1) }}%</p>
                </article>
                <article class="rounded-xl border border-stone bg-white px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Retry recovery rate</p>
                    <p class="mt-1 text-2xl font-semibold text-navy dark:text-white">{{ number_format((float) ($rates['retry_recovery_rate'] ?? 0), 1) }}%</p>
                </article>
                <article class="rounded-xl border border-stone bg-white px-4 py-3 text-sm dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Overall collection rate</p>
                    <p class="mt-1 text-2xl font-semibold text-navy dark:text-white">{{ number_format((float) ($rates['overall_collection_rate'] ?? 0), 1) }}%</p>
                </article>
            </div>
        </section>

        @if (filled($recoverySequence) || count($paymentAttemptStats) > 0)
            <section class="rounded-2xl border border-stone bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold text-navy dark:text-white">Recovery analytics</h3>
                <p class="mt-1 text-sm text-gray-500">Payment attempts by sequence for the selected period (ledger-backed).</p>
                <dl class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/80">
                        <dt class="text-xs text-gray-500">Total recovered amount</dt>
                        <dd class="text-lg font-semibold">{{ $formatMoney((float) ($recoverySequence['total_recovered_amount'] ?? 0)) }}</dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/80">
                        <dt class="text-xs text-gray-500">Recovery success %</dt>
                        <dd class="text-lg font-semibold">{{ number_format((float) ($recoverySequence['recovery_success_rate'] ?? 0), 1) }}%</dd>
                    </div>
                    <div class="rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/80">
                        <dt class="text-xs text-gray-500">Second-attempt successes</dt>
                        <dd class="text-lg font-semibold">{{ number_format((int) ($recoverySequence['second_attempt_success'] ?? 0)) }}</dd>
                    </div>
                </dl>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[520px] text-left text-sm">
                        <thead class="text-xs uppercase text-gray-500">
                            <tr>
                                <th class="py-2 pe-4">Sequence</th>
                                <th class="py-2 pe-4">Attempts</th>
                                <th class="py-2 pe-4">Successful</th>
                                <th class="py-2 pe-4">Failed</th>
                                <th class="py-2 pe-4">Success rate</th>
                                <th class="py-2 text-right">Revenue</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($paymentAttemptStats as $row)
                                <tr class="border-t border-gray-100 dark:border-gray-800">
                                    <td class="py-2 pe-4 font-medium">{{ $row['description'] ?? '' }}</td>
                                    <td class="py-2 pe-4">{{ number_format((int) ($row['total_attempts'] ?? 0)) }}</td>
                                    <td class="py-2 pe-4 text-success-600 dark:text-success-400">{{ number_format((int) ($row['successful'] ?? 0)) }}</td>
                                    <td class="py-2 pe-4 text-danger-600 dark:text-danger-400">{{ number_format((int) ($row['failed'] ?? 0)) }}</td>
                                    <td class="py-2 pe-4">{{ number_format((float) ($row['success_rate'] ?? 0), 1) }}%</td>
                                    <td class="py-2 text-right">{{ $formatMoney((float) ($row['revenue_collected'] ?? 0)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        <section class="grid gap-6 lg:grid-cols-2">
            @livewire(\App\Filament\Admin\Widgets\RebillRecovery\RebillRecoveryOutcomeChartWidget::class, $widgetData, key('rr-outcome-' . md5(json_encode($widgetData))))
            @livewire(\App\Filament\Admin\Widgets\RebillRecovery\RebillRecoveryTrendChartWidget::class, $widgetData, key('rr-trend-' . md5(json_encode($widgetData))))
        </section>

        <section class="rounded-2xl border border-stone bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h3 class="text-lg font-semibold text-navy dark:text-white">Recovery by attempt</h3>
            <p class="mt-1 text-sm text-gray-500">Initially failed cycles recovered on each subsequent attempt</p>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="text-xs uppercase text-gray-500">
                        <tr>
                            <th class="py-2 pe-4">Attempt</th>
                            <th class="py-2 pe-4">Cycles</th>
                            <th class="py-2 pe-4">Recovered revenue</th>
                            <th class="py-2 text-right">Share</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recovery as $row)
                            <tr class="border-t border-gray-100 dark:border-gray-800">
                                <td class="py-2 pe-4 font-medium">{{ $row['label'] }}</td>
                                <td class="py-2 pe-4">{{ number_format($row['count']) }}</td>
                                <td class="py-2 pe-4">{{ $formatMoney((float) $row['recovered_revenue']) }}</td>
                                <td class="py-2 text-right">{{ number_format((float) $row['percent'], 1) }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-gray-500">No rebill cycles in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="grid gap-6 lg:grid-cols-2">
            <article class="rounded-2xl border border-stone bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold">Gateway recovery</h3>
                <ul class="mt-4 space-y-2 text-sm">
                    @forelse ($charts['gateway'] ?? [] as $item)
                        <li class="flex items-center justify-between gap-4 rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/80">
                            <span class="font-medium">{{ $item['label'] }}</span>
                            <span class="text-gray-500">{{ $item['collected'] }}/{{ $item['attempts'] }} · {{ $item['rate'] }}%</span>
                        </li>
                    @empty
                        <li class="text-gray-500">No gateway data for this filter.</li>
                    @endforelse
                </ul>
            </article>
            <article class="rounded-2xl border border-stone bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold">Campaign recovery</h3>
                <ul class="mt-4 space-y-2 text-sm">
                    @forelse ($charts['campaign'] ?? [] as $item)
                        <li class="flex items-center justify-between gap-4 rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800/80">
                            <span class="font-medium">{{ $item['label'] }}</span>
                            <span class="text-gray-500">{{ $item['collected'] }}/{{ $item['attempts'] }} · {{ $item['rate'] }}%</span>
                        </li>
                    @empty
                        <li class="text-gray-500">No campaign data for this filter.</li>
                    @endforelse
                </ul>
            </article>
        </section>

        <section class="rounded-2xl border border-stone bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h3 class="text-lg font-semibold">Decline reasons (initial failure)</h3>
            <ul class="mt-4 flex flex-wrap gap-2 text-sm">
                @forelse ($charts['decline_reasons'] ?? [] as $item)
                    <li class="rounded-full bg-gray-100 px-3 py-1 dark:bg-gray-800">{{ $item['label'] }} · {{ $item['value'] }}</li>
                @empty
                    <li class="text-gray-500">No failures recorded in this period.</li>
                @endforelse
            </ul>
        </section>

        @if (count($subscriptionHistories) > 0)
            <section class="rounded-2xl border border-stone bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold text-navy dark:text-white">Rebill cycle &amp; retry history</h3>
                <p class="mt-1 text-sm text-gray-500">Nested view: each rebill cycle (cycle_step) contains its retry attempts. Data from billing_ledger_entries.</p>
                @include('filament.admin.pages.partials.rebill-recovery-nested-cycles', [
                    'histories' => $subscriptionHistories,
                    'formatMoney' => $formatMoney,
                    'emptyLabel' => 'No subscriptions with cycles in this period.',
                ])
            </section>
        @endif

        <section class="rounded-2xl border border-stone bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 class="text-lg font-semibold">Rebill recovery report</h3>
                    <p class="text-sm text-gray-500">Flat cycle summary; see nested history above for cycle vs retry detail. KPI cards reflect the full period.</p>
                </div>
            </div>

            <div class="mt-4 cp-rebill-recovery-report">
                @include('filament.admin.pages.partials.rebill-recovery-report-pagination', [
                    'pagination' => $reportPagination,
                    'perPageOptions' => $this->reportPerPageOptions(),
                    'cyclesInPeriod' => $reportMeta['cycles_in_period'] ?? ($kpis['total_due'] ?? null),
                    'activeFilter' => $kpiFilter,
                    'activeFilterLabel' => $reportFilterLabel,
                    'clearFilterUrl' => $this->clearReportFilterUrl(),
                ])

                @include('filament.admin.pages.partials.rebill-recovery-report-table', [
                    'rows' => $rows,
                    'formatMoney' => $formatMoney,
                ])
            </div>
        </section>

        <section class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-900/40 dark:text-gray-300">
            <h4 class="font-semibold text-gray-800 dark:text-gray-100">Data notes &amp; limitations</h4>
            <ul class="mt-2 list-disc space-y-1 ps-5">
                @foreach ($limitations as $note)
                    <li>{{ $note }}</li>
                @endforeach
            </ul>
        </section>
    </div>
</x-filament-panels::page>