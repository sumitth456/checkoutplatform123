@php
    $hasDetails = ! empty($event['details']) || ! empty($event['children']);
    $isNested = (bool) ($nested ?? false);
@endphp

@if ($isNested)
<div class="cp-timeline__item cp-timeline__item--nested" x-data="{ open: {{ ($event['collapsed'] ?? false) ? 'false' : 'true' }} }">
@else
<li class="cp-timeline__item" x-data="{ open: {{ ($event['collapsed'] ?? false) ? 'false' : 'true' }} }">
@endif
    <div class="cp-timeline__marker">
        <span class="cp-timeline-dot {{ $event['dot_class'] ?? 'cp-timeline-dot--pending' }}"></span>
    </div>

    <div class="cp-timeline__content">
        <div class="cp-timeline__header">
            <div class="cp-timeline__title-row">
                <h4 class="cp-timeline__title">{{ $event['title'] ?? 'Event' }}</h4>
                <span class="cp-badge {{ $event['badge_class'] ?? 'cp-badge--muted' }}">{{ $event['status_label'] ?? '' }}</span>
            </div>
            <div class="cp-timeline__meta">
                @if (! empty($event['timestamp']))
                    <time>{{ $event['timestamp'] }}</time>
                @endif
                @if ($hasDetails)
                    <button type="button" class="cp-button cp-button--ghost cp-button--xs" @click="open = !open" x-text="open ? 'Collapse' : 'Expand'"></button>
                @endif
            </div>
        </div>

        @if (! empty($event['description']))
            <p class="cp-timeline__description">{{ $event['description'] }}</p>
        @endif

        @if ($hasDetails)
            <div class="cp-timeline__details" x-show="open" x-transition>
                @if (! empty($event['details']))
                    <dl class="cp-detail-grid">
                        @foreach ($event['details'] as $label => $value)
                            @if (filled($value))
                                <div class="cp-detail-grid__item">
                                    <dt>{{ $label }}</dt>
                                    <dd>{{ $value }}</dd>
                                </div>
                            @endif
                        @endforeach
                    </dl>
                @endif

                @if (! empty($event['children']))
                    <div class="cp-timeline__group">
                        @foreach ($event['children'] as $child)
                            @include('filament.admin.components.partials.timeline-event', ['event' => $child, 'nested' => true])
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
    </div>
@if ($isNested)
</div>
@else
</li>
@endif
