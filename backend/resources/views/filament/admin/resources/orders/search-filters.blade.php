@php
    $options = $options ?? [];
    $showMore = $showMore ?? false;
@endphp

<div class="cp-orders-search-filters col-span-full" wire:key="orders-search-filters">
    <div class="cp-orders-search-filters__heading">
        <span class="cp-orders-search-filters__title">Search orders</span>
        <span class="cp-orders-search-filters__hint">Filter by customer, order details, and campaign</span>
    </div>

    <form wire:submit="applySearchFilters" class="cp-orders-search-filters__panel">
        <div class="cp-orders-search-filters__grid">
            <div class="cp-orders-search-filters__field">
                <label for="of-order-id">Order Id</label>
                <input
                    id="of-order-id"
                    type="text"
                    wire:model="searchOrderId"
                    class="cp-orders-search-filters__input"
                    placeholder="Order #, ID, or UUID"
                />
            </div>

            <div class="cp-orders-search-filters__field">
                <label for="of-first-name">First Name</label>
                <input
                    id="of-first-name"
                    type="text"
                    wire:model="searchFirstName"
                    class="cp-orders-search-filters__input"
                />
            </div>

            <div class="cp-orders-search-filters__field">
                <label for="of-phone">Phone Number</label>
                <input
                    id="of-phone"
                    type="text"
                    wire:model="searchPhone"
                    class="cp-orders-search-filters__input"
                />
                <label class="cp-orders-search-filters__toggle">
                    <input type="checkbox" wire:model="searchPhoneExact" />
                    <span>Find Exact Match</span>
                </label>
            </div>

            <div class="cp-orders-search-filters__field">
                <label for="of-company">Company Name</label>
                <input
                    id="of-company"
                    type="text"
                    wire:model="searchCompany"
                    class="cp-orders-search-filters__input"
                />
            </div>

            <div class="cp-orders-search-filters__field">
                <label for="of-status">Order Status</label>
                <select id="of-status" wire:model="searchStatus" class="cp-orders-search-filters__input">
                    <option value="">All</option>
                    @foreach ($options['statuses'] ?? [] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cp-orders-search-filters__field">
                <label for="of-last-name">Last Name</label>
                <input
                    id="of-last-name"
                    type="text"
                    wire:model="searchLastName"
                    class="cp-orders-search-filters__input"
                />
            </div>

            <div class="cp-orders-search-filters__field">
                <label for="of-email">Email Address</label>
                <input
                    id="of-email"
                    type="text"
                    wire:model="searchEmail"
                    class="cp-orders-search-filters__input"
                />
                <label class="cp-orders-search-filters__toggle">
                    <input type="checkbox" wire:model="searchEmailExact" />
                    <span>Find Exact Match</span>
                </label>
            </div>

            <div class="cp-orders-search-filters__field">
                <label for="of-ip">IP Address</label>
                <input
                    id="of-ip"
                    type="text"
                    wire:model="searchIpAddress"
                    class="cp-orders-search-filters__input"
                />
            </div>

            <div class="cp-orders-search-filters__field">
                <label for="of-order-type">Order Type</label>
                <select id="of-order-type" wire:model="searchOrderType" class="cp-orders-search-filters__input">
                    <option value="">All</option>
                    @foreach ($options['orderTypes'] ?? [] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cp-orders-search-filters__field">
                <label for="of-campaign">Campaign</label>
                <select id="of-campaign" wire:model="searchCampaignId" class="cp-orders-search-filters__input">
                    <option value="">All Campaigns</option>
                    @foreach ($options['campaigns'] ?? [] as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cp-orders-search-filters__field">
                <label for="of-state">State</label>
                <select id="of-state" wire:model="searchState" class="cp-orders-search-filters__input">
                    <option value="">All</option>
                    @foreach ($options['states'] ?? [] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cp-orders-search-filters__field">
                <label for="of-customer-id">Customer Id</label>
                <input
                    id="of-customer-id"
                    type="text"
                    wire:model="searchCustomerId"
                    class="cp-orders-search-filters__input"
                    placeholder="Customer ID or UUID"
                />
            </div>

            <div class="cp-orders-search-filters__field">
                <label for="of-country">Country</label>
                <select id="of-country" wire:model="searchCountry" class="cp-orders-search-filters__input">
                    <option value="">All</option>
                    @foreach ($options['countries'] ?? [] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="cp-orders-search-filters__field">
                <label for="of-currency">Currency</label>
                <select id="of-currency" wire:model="searchCurrency" class="cp-orders-search-filters__input">
                    <option value="">All</option>
                    @foreach ($options['currencies'] ?? [] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        @if ($showMore)
            <div class="cp-orders-search-filters__grid cp-orders-search-filters__grid--more">
                <div class="cp-orders-search-filters__field">
                    <label for="of-payment-status">Payment Status</label>
                    <select id="of-payment-status" wire:model="searchPaymentStatus" class="cp-orders-search-filters__input">
                        <option value="">All</option>
                        @foreach ($options['paymentStatuses'] ?? [] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        @endif

        <div class="cp-orders-search-filters__actions">
            <button type="submit" class="cp-orders-search-filters__search" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="applySearchFilters,resetSearchFilters,toggleMoreFilters">
                    Search Orders
                </span>
                <span wire:loading wire:target="applySearchFilters,resetSearchFilters,toggleMoreFilters">
                    Searching…
                </span>
            </button>

            <button
                type="button"
                class="cp-orders-search-filters__more"
                wire:click="toggleMoreFilters"
            >
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M2 3.5A1.5 1.5 0 0 1 3.5 2h13A1.5 1.5 0 0 1 18 3.5v2.75a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 2 6.25V3.5Zm0 6.75A1.5 1.5 0 0 1 3.5 9h13a1.5 1.5 0 0 1 1.5 1.5v2.75a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 2 13.25V10.25Zm1.5-5.25a.75.75 0 0 0-.75.75v.5c0 .414.336.75.75.75h.5a.75.75 0 0 0 .75-.75v-.5a.75.75 0 0 0-.75-.75h-.5Zm0 6.75a.75.75 0 0 0-.75.75v.5c0 .414.336.75.75.75h.5a.75.75 0 0 0 .75-.75v-.5a.75.75 0 0 0-.75-.75h-.5Z" clip-rule="evenodd" />
                </svg>
                {{ $showMore ? 'Fewer Filters' : 'More Filters' }}
            </button>

            @if ($hasActiveSearch ?? false)
                <button
                    type="button"
                    class="cp-orders-search-filters__clear"
                    wire:click="resetSearchFilters"
                    wire:loading.attr="disabled"
                >
                    Clear search
                </button>
            @endif
        </div>
    </form>
</div>
