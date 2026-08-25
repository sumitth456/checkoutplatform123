<x-filament-panels::page>
    @php
        $options = $this->filterOptions();
        $summary = $this->activeFilterSummary();
        $metrics = $this->safeMetrics();
        $currency = $this->currency ?? 'USD';
    @endphp

    {{-- Date filter bar — rendered directly on Livewire page (not SchemaView) --}}
    <div class="cp-analytics-toolbar cp-dashboard-toolbar cp-dashboard-toolbar--page" wire:key="dashboard-date-filters">
        <form wire:submit="applyFilters" class="cp-analytics-toolbar__inner">
            <div class="cp-analytics-toolbar__scroll">
                <div class="cp-analytics-toolbar__field">
                    <label for="df-range">Quick range</label>
                    <select id="df-range" wire:model.live="quickRange" class="cp-analytics-toolbar__input">
                        @foreach ($this->dashboardQuickRangeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="cp-analytics-toolbar__field">
                    <label for="df-from">From</label>
                    <input
                        id="df-from"
                        type="datetime-local"
                        wire:model="dateFrom"
                        class="cp-analytics-toolbar__input"
                        @disabled($quickRange !== 'custom')
                    />
                </div>

                <div class="cp-analytics-toolbar__field">
                    <label for="df-to">To</label>
                    <input
                        id="df-to"
                        type="datetime-local"
                        wire:model="dateTo"
                        class="cp-analytics-toolbar__input"
                        @disabled($quickRange !== 'custom')
                    />
                </div>
            </div>

            <div class="cp-analytics-toolbar__actions">
                <button type="submit" class="cp-analytics-toolbar__apply" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="applyFilters,quickRange">Apply</span>
                    <span wire:loading wire:target="applyFilters,quickRange">Updating…</span>
                </button>
            </div>
        </form>

        @if (! empty($summary['range']))
            <div class="cp-dashboard-filter-summary">
                <span class="cp-dashboard-filter-summary__label">Showing:</span>
                <strong>{{ $summary['range'] }}</strong>
                @if ($summary['from'] && $summary['to'])
                    <span class="cp-dashboard-filter-summary__sep">·</span>
                    <span>{{ \Illuminate\Support\Carbon::parse($summary['from'])->format('M j, Y g:i A') }}</span>
                    <span class="cp-dashboard-filter-summary__sep">→</span>
                    <span>{{ \Illuminate\Support\Carbon::parse($summary['to'])->format('M j, Y g:i A') }}</span>
                @endif
            </div>
        @endif
    </div>

    @include('filament.admin.pages.operational-dashboard', [
        'metrics' => $metrics,
        'currency' => $currency,
    ])
</x-filament-panels::page>
