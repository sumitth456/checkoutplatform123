@php
    $pagination = $pagination ?? [];
    $total = (int) ($pagination['total'] ?? 0);
    $page = (int) ($pagination['page'] ?? 1);
    $lastPage = (int) ($pagination['last_page'] ?? 1);
    $from = (int) ($pagination['from'] ?? 0);
    $to = (int) ($pagination['to'] ?? 0);
    $perPage = (int) ($pagination['per_page'] ?? 25);
    $perPageOptions = $perPageOptions ?? [25, 50, 100, 250];
    $cyclesInPeriod = $cyclesInPeriod ?? null;
    $activeFilter = $activeFilter ?? null;
    $activeFilterLabel = $activeFilterLabel ?? null;
@endphp

<div class="cp-rebill-recovery-report__toolbar">
    <div class="cp-rebill-recovery-report__summary">
        @if ($total > 0)
            <p class="cp-rebill-recovery-report__range">
                Showing <strong>{{ number_format($from) }}–{{ number_format($to) }}</strong> of
                <strong>{{ number_format($total) }}</strong> matching cycle{{ $total === 1 ? '' : 's' }}
            </p>
        @else
            <p class="cp-rebill-recovery-report__range">No matching rebill cycles for this filter.</p>
        @endif

        @if (filled($cyclesInPeriod) && (int) $cyclesInPeriod !== $total)
            <p class="cp-rebill-recovery-report__meta text-sm text-gray-500">
                {{ number_format((int) $cyclesInPeriod) }} cycles in period (KPI scope); report filtered to {{ number_format($total) }}.
            </p>
        @elseif (filled($cyclesInPeriod))
            <p class="cp-rebill-recovery-report__meta text-sm text-gray-500">
                {{ number_format((int) $cyclesInPeriod) }} cycles in selected period.
            </p>
        @endif

        @if ($activeFilter && $activeFilterLabel)
            <p class="cp-rebill-recovery-report__filter-badge">
                Status filter: <strong>{{ $activeFilterLabel }}</strong>
                <a href="{{ $clearFilterUrl ?? '#' }}" wire:navigate class="cp-rebill-recovery-report__clear-filter">Clear</a>
            </p>
        @endif
    </div>

    <div class="cp-rebill-recovery-report__pager">
        <label class="cp-rebill-recovery-report__per-page">
            <span class="sr-only">Rows per page</span>
            <span class="cp-rebill-recovery-report__per-page-label">Rows per page</span>
            <select wire:model.live="reportPerPage" class="cp-rebill-recovery-report__per-page-select">
                @foreach ($perPageOptions as $option)
                    <option value="{{ $option }}">{{ $option }}</option>
                @endforeach
            </select>
        </label>

        <div class="cp-rebill-recovery-report__page-controls">
            <button
                type="button"
                wire:click="previousReportPage"
                @disabled($page <= 1)
                class="cp-rebill-recovery-report__page-btn"
            >
                Previous
            </button>
            <span class="cp-rebill-recovery-report__page-status">
                Page {{ $page }} of {{ $lastPage }}
            </span>
            <button
                type="button"
                wire:click="nextReportPage"
                @disabled($page >= $lastPage)
                class="cp-rebill-recovery-report__page-btn"
            >
                Next
            </button>
        </div>
    </div>
</div>