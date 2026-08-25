@php
    $title = $title ?? '';
    $subtitle = $subtitle ?? null;
@endphp

<div class="cp-analytics-section-header col-span-full">
    <h2 class="cp-analytics-section-header__title">{{ $title }}</h2>
    @if (filled($subtitle))
        <p class="cp-analytics-section-header__subtitle">{{ $subtitle }}</p>
    @endif
</div>
