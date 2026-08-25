@php
    $options = $options ?? [];
@endphp

<div class="cp-analytics-toolbar col-span-full">
    <form wire:submit="applyFilters" class="cp-analytics-toolbar__inner">
        <div class="cp-analytics-toolbar__scroll">
            <div class="cp-analytics-toolbar__field">
                <label for="df-range">Range</label>
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
                    wire:model.live="dateFrom"
                    class="cp-analytics-toolbar__input"
                    x-bind:disabled="$wire.quickRange !== 'custom'"
                />
            </div>

            <div class="cp-analytics-toolbar__field">
                <label for="df-to">To</label>
                <input
                    id="df-to"
                    type="datetime-local"
                    wire:model.live="dateTo"
                    class="cp-analytics-toolbar__input"
                    x-bind:disabled="$wire.quickRange !== 'custom'"
                />
            </div>

            <div class="cp-analytics-toolbar__field">
                <label for="df-campaign">Campaign</label>
                <select id="df-campaign" wire:model.live="campaignId" class="cp-analytics-toolbar__input">
                    <option value="">All</option>
                    @foreach ($options['campaigns'] ?? [] as $campaign)
                        <option value="{{ $campaign->id }}">{{ $campaign->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cp-analytics-toolbar__field">
                <label for="df-category">Category</label>
                <select id="df-category" wire:model.live="campaignCategory" class="cp-analytics-toolbar__input">
                    <option value="">All</option>
                    @foreach ($options['categories'] ?? [] as $category)
                        <option value="{{ $category }}">{{ $category }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cp-analytics-toolbar__field">
                <label for="df-traffic">Traffic</label>
                <select id="df-traffic" wire:model.live="trafficSource" class="cp-analytics-toolbar__input">
                    <option value="">All</option>
                    @foreach ($options['trafficSources'] ?? [] as $source)
                        <option value="{{ $source }}">{{ $source }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cp-analytics-toolbar__field">
                <label for="df-affiliate">Affiliate</label>
                <select id="df-affiliate" wire:model.live="affiliateId" class="cp-analytics-toolbar__input">
                    <option value="">All</option>
                    @foreach ($options['affiliates'] ?? [] as $affiliate)
                        <option value="{{ $affiliate->id }}">{{ $affiliate->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cp-analytics-toolbar__field">
                <label for="df-gateway">Gateway</label>
                <select id="df-gateway" wire:model.live="paymentGatewayId" class="cp-analytics-toolbar__input">
                    <option value="">All</option>
                    @foreach ($options['gateways'] ?? [] as $gateway)
                        <option value="{{ $gateway->id }}">{{ $gateway->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cp-analytics-toolbar__field">
                <label for="df-fhouse">Fulfillment</label>
                <select id="df-fhouse" wire:model.live="fulfillmentHouseId" class="cp-analytics-toolbar__input">
                    <option value="">All</option>
                    @foreach ($options['fulfillmentHouses'] ?? [] as $house)
                        <option value="{{ $house->id }}">{{ $house->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <button type="submit" class="cp-analytics-toolbar__apply" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="applyFilters,quickRange,dateFrom,dateTo,campaignId,campaignCategory,trafficSource,affiliateId,paymentGatewayId,fulfillmentHouseId">
                Apply filters
            </span>
            <span wire:loading wire:target="applyFilters,quickRange,dateFrom,dateTo,campaignId,campaignCategory,trafficSource,affiliateId,paymentGatewayId,fulfillmentHouseId">
                Updating…
            </span>
        </button>
    </form>
</div>
