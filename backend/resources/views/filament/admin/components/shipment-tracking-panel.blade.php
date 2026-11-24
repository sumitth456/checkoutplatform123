@php
    /** @var array<string, mixed> $tracking */
    $state = $tracking['state'] ?? 'not_available';
@endphp

<div class="cp-compact-panel">
    <div class="cp-panel-header">
        <div>
            <h3 class="cp-panel-title">Shipment tracking</h3>
            <p class="cp-panel-subtitle">
                ShipStation shipment visibility for support
                @if ($tracking['shadow_preview'] ?? false)
                    <span class="text-amber-600 dark:text-amber-400">— shadow preview (not written to integration columns)</span>
                @endif
            </p>
        </div>
    </div>

    @if ($state === 'empty')
        <p class="cp-empty-state">Not available — no ShipStation shipment is linked to this order.</p>
    @else
        <div class="cp-compact-grid">
            <div class="col-span-full">
                <span class="cp-compact-grid__label">Tracking number</span>
                @if (filled($tracking['tracking_number'] ?? null))
                    <div class="flex flex-wrap items-center gap-2" x-data="{ copied: false }">
                        <span class="cp-compact-grid__value font-mono text-base">{{ $tracking['tracking_number'] }}</span>
                        <button
                            type="button"
                            class="cp-button cp-button--ghost text-xs"
                            @click="navigator.clipboard.writeText(@js($tracking['tracking_number'])); copied = true; setTimeout(() => copied = false, 2000)"
                            x-text="copied ? 'Copied' : 'Copy'"
                        ></button>
                        @if (($tracking['tracking_url_trusted'] ?? false) && filled($tracking['tracking_url'] ?? null))
                            <a href="{{ $tracking['tracking_url'] }}" target="_blank" rel="noopener noreferrer" class="cp-link text-sm">
                                Track shipment
                            </a>
                        @endif
                    </div>
                @else
                    <span class="cp-compact-grid__value text-gray-500">Pending</span>
                @endif
            </div>

            <div>
                <span class="cp-compact-grid__label">Carrier</span>
                <span class="cp-compact-grid__value">{{ $tracking['carrier'] ?? 'Not available' }}</span>
            </div>
            <div>
                <span class="cp-compact-grid__label">Shipping method</span>
                <span class="cp-compact-grid__value">{{ $tracking['shipping_method'] ?? 'Not available' }}</span>
            </div>
            <div>
                <span class="cp-compact-grid__label">Current status</span>
                <span class="cp-compact-grid__value">{{ $tracking['status_label'] ?? 'Pending' }}</span>
                @if (filled($tracking['status_subtitle'] ?? null))
                    <p class="text-xs text-gray-500 mt-1">{{ $tracking['status_subtitle'] }}</p>
                @endif
            </div>
            <div>
                <span class="cp-compact-grid__label">ShipStation shipment ID</span>
                <span class="cp-compact-grid__value font-mono text-sm">{{ $tracking['external_shipment_id'] ?? 'Pending' }}</span>
            </div>
            <div>
                <span class="cp-compact-grid__label">Label created</span>
                <span class="cp-compact-grid__value">{{ $tracking['label_created_at'] ?? 'Not available' }}</span>
            </div>
            <div>
                <span class="cp-compact-grid__label">Shipped</span>
                <span class="cp-compact-grid__value">{{ $tracking['shipped_at'] ?? 'Not available' }}</span>
            </div>
            <div>
                <span class="cp-compact-grid__label">Delivered</span>
                <span class="cp-compact-grid__value">{{ $tracking['delivered_at'] ?? 'Not available' }}</span>
            </div>
            <div>
                <span class="cp-compact-grid__label">Last synchronized</span>
                <span class="cp-compact-grid__value">{{ $tracking['last_synchronized_at'] ?? 'Not available' }}</span>
            </div>
        </div>

        <div class="mt-4 text-xs text-gray-500 space-y-1">
            <p><strong>Update path:</strong> {{ $tracking['sync_mechanism'] ?? '—' }}</p>
            <p><strong>Expected latency:</strong> {{ $tracking['expected_latency'] ?? '—' }}</p>
        </div>

        <div class="mt-6">
            <h4 class="text-sm font-semibold mb-2">Tracking history</h4>
            @php $history = $tracking['history'] ?? []; @endphp
            @if ($history === [])
                <p class="cp-empty-state text-sm">No carrier scan history stored yet.</p>
            @else
                <ol class="cp-timeline">
                    @foreach ($history as $event)
                        <li class="cp-timeline__item border-l border-gray-200 pl-3 py-2">
                            <p class="text-xs text-gray-500">{{ $event['at'] ?? 'Unknown time' }}</p>
                            <p class="text-sm font-medium">{{ $event['status'] ?? 'Update' }}</p>
                            @if (filled($event['description'] ?? null))
                                <p class="text-sm text-gray-700 dark:text-gray-300">{{ $event['description'] }}</p>
                            @endif
                            @if (filled($event['location'] ?? null))
                                <p class="text-xs text-gray-500">Location: {{ is_array($event['location']) ? json_encode($event['location']) : $event['location'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </div>
    @endif
</div>