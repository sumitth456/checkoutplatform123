@php
    $options = $options ?? [];
    $summary = $summary ?? [];
@endphp

<div class="cp-analytics-toolbar cp-dashboard-toolbar col-span-full" wire:key="dashboard-date-filters">
    <form wire:submit="applyFilters" class="cp-analytics-toolbar__inner">
        <div class="cp-analytics-toolbar__scroll">
            <div class="cp-analytics-toolbar__field">
                <label for="df-range">Quick range</label>
                <select id="df-range" wire:model.live="quickRange" class="cp-analytics-toolbar__input">
                    @foreach ($options['quickRanges'] ?? [] as $value => $label)
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
                    x-bind:disabled="$wire.quickRange !== 'custom'"
                />
            </div>

            <div class="cp-analytics-toolbar__field">
                <label for="df-to">To</label>
                <input
                    id="df-to"
                    type="datetime-local"
                    wire:model="dateTo"
                    class="cp-analytics-toolbar__input"
                    x-bind:disabled="$wire.quickRange !== 'custom'"
                />
            </div>

            <div class="cp-analytics-toolbar__field cp-dashboard-toolbar__field--advanced">
                <label for="df-campaign">Campaign</label>
                <select id="df-campaign" wire:model="campaignId" class="cp-analytics-toolbar__input">
                    <option value="">All</option>
                    @foreach ($options['campaigns'] ?? [] as $campaign)
                        <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cp-analytics-toolbar__field cp-dashboard-toolbar__field--advanced">
                <label for="df-category">Category</label>
                <select id="df-category" wire:model="campaignCategory" class="cp-analytics-toolbar__input">
                    <option value="">All</option>
                    @foreach ($options['categories'] ?? [] as $category)
                        <option value="{{ $category }}">{{ $category }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cp-analytics-toolbar__field cp-dashboard-toolbar__field--advanced">
                <label for="df-traffic">Traffic</label>
                <select id="df-traffic" wire:model="trafficSource" class="cp-analytics-toolbar__input">
                    <option value="">All</option>
                    @foreach ($options['trafficSources'] ?? [] as $source)
                        <option value="{{ $source }}">{{ $source }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cp-analytics-toolbar__field cp-dashboard-toolbar__field--advanced">
                <label for="df-affiliate">Affiliate</label>
                <select id="df-affiliate" wire:model="affiliateId" class="cp-analytics-toolbar__input">
                    <option value="">All</option>
                    @foreach ($options['affiliates'] ?? [] as $affiliate)
                        <option value="{{ $affiliate->id }}">{{ $affiliate->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cp-analytics-toolbar__field cp-dashboard-toolbar__field--advanced">
                <label for="df-gateway">Gateway</label>
                <select id="df-gateway" wire:model="paymentGatewayId" class="cp-analytics-toolbar__input">
                    <option value="">All</option>
                    @foreach ($options['gateways'] ?? [] as $gateway)
                        <option value="{{ $gateway->id }}">{{ $gateway->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cp-analytics-toolbar__field cp-dashboard-toolbar__field--advanced">
                <label for="df-fhouse">Fulfillment house</label>
                <select id="df-fhouse" wire:model="fulfillmentHouseId" class="cp-analytics-toolbar__input">
                    <option value="">All</option>
                    @foreach ($options['fulfillmentHouses'] ?? [] as $house)
                        <option value="{{ $house->id }}">{{ $house->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="cp-analytics-toolbar__actions">
            <button type="submit" class="cp-analytics-toolbar__apply" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="applyFilters,quickRange">
                    Apply
                </span>
                <span wire:loading wire:target="applyFilters,quickRange">
                    Updating…
                </span>
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
