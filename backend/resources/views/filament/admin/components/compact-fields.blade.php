@php
    /** @var array<string, string> $fields */
@endphp

<div class="cp-compact-panel">
    <div class="cp-panel-header">
        <div>
            <h3 class="cp-panel-title">{{ $title ?? 'Details' }}</h3>
            @if (! empty($subtitle))
                <p class="cp-panel-subtitle">{{ $subtitle }}</p>
            @endif
        </div>
    </div>
    <div class="cp-compact-grid">
        @foreach ($fields as $label => $value)
            <div>
                <span class="cp-compact-grid__label">{{ $label }}</span>
                @if (! empty($urls[$label] ?? null))
                    <a href="{{ $urls[$label] }}" class="cp-compact-grid__value cp-link">{!! nl2br(e($value)) !!}</a>
                @else
                    <span class="cp-compact-grid__value">{!! nl2br(e($value)) !!}</span>
                @endif
            </div>
        @endforeach
    </div>
</div>
