@php
    $options = $options ?? [];
    $summary = $summary ?? null;
@endphp

<div class="cp-analytics-toolbar cp-orders-date-filters col-span-full" wire:key="orders-date-filters">
    <div class="cp-orders-date-filters__heading">
        <span class="cp-orders-date-filters__title">Date filters</span>
        <span class="cp-orders-date-filters__hint">Filter orders by placed date</span>
    </div>

    <form wire:submit="applyFilters" class="cp-analytics-toolbar__inner">
        <div class="cp-analytics-toolbar__scroll">
            <div class="cp-analytics-toolbar__field">
                <label for="orders-df-range">Quick range</label>
                <select id="orders-df-range" wire:model.live="quickRange" class="cp-analytics-toolbar__input">
                    @foreach ($options as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cp-analytics-toolbar__field">
                <label for="orders-df-from">From</label>
                <input
                    id="orders-df-from"
                    type="datetime-local"
                    wire:model.live="dateFrom"
                    class="cp-analytics-toolbar__input"
                    x-bind:disabled="$wire.quickRange !== 'custom'"
                />
            </div>

            <div class="cp-analytics-toolbar__field">
                <label for="orders-df-to">To</label>
                <input
                    id="orders-df-to"
                    type="datetime-local"
                    wire:model.live="dateTo"
                    class="cp-analytics-toolbar__input"
                    x-bind:disabled="$wire.quickRange !== 'custom'"
                />
            </div>
        </div>

        <div class="cp-analytics-toolbar__actions">
            <button type="submit" class="cp-analytics-toolbar__apply" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="applyFilters,resetFilters,quickRange,dateFrom,dateTo">
                    Apply filters
                </span>
                <span wire:loading wire:target="applyFilters,resetFilters,quickRange,dateFrom,dateTo">
                    Updating…
                </span>
            </button>

            <button
                type="button"
                class="cp-analytics-toolbar__reset"
                wire:click="resetFilters"
                wire:loading.attr="disabled"
            >
                Reset
            </button>
        </div>
    </form>

    @if ($summary !== null)
        <div class="cp-orders-filter-summary">
            <span class="cp-orders-filter-summary__label">Active filter:</span>
            <span>{{ $summary['range'] }}</span>
            @if ($summary['from'] && $summary['to'])
                <span class="cp-orders-filter-summary__sep">·</span>
                <span>From {{ $summary['from'] }}</span>
                <span class="cp-orders-filter-summary__sep">·</span>
                <span>To {{ $summary['to'] }}</span>
            @endif
        </div>
    @endif
</div>
