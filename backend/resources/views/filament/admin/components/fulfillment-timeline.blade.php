@php
    /** @var array<string, mixed> $timeline */
    $events = $timeline['events'] ?? [];
    $olderEvents = $timeline['older_events'] ?? [];
    $hiddenCount = (int) ($timeline['hidden_count'] ?? 0);
@endphp

<div
    class="cp-timeline-panel"
    x-data="{ showOlder: false }"
>
    <div class="cp-panel-header">
        <div>
            <h3 class="cp-panel-title">{{ $heading ?? 'Fulfillment timeline' }}</h3>
            <p class="cp-panel-subtitle">Status milestones and shipment events</p>
        </div>
        @if ($hiddenCount > 0)
            <button
                type="button"
                class="cp-button cp-button--ghost"
                @click="showOlder = !showOlder"
                x-text="showOlder ? 'Hide older events' : 'View full history ({{ $hiddenCount }})'"
            ></button>
        @endif
    </div>

    @if ($events === [] && $olderEvents === [])
        <p class="cp-empty-state">No fulfillment timeline events recorded yet.</p>
    @else
        <ol class="cp-timeline">
            @if ($hiddenCount > 0)
                <div x-show="showOlder" x-cloak class="cp-timeline__older">
                    @foreach ($olderEvents as $event)
                        @include('filament.admin.components.partials.timeline-event', ['event' => $event, 'nested' => true])
                    @endforeach
                </div>
            @endif

            @foreach ($events as $event)
                @include('filament.admin.components.partials.timeline-event', ['event' => $event])
            @endforeach
        </ol>
    @endif
</div>
