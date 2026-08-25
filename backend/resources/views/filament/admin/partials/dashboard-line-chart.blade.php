@php
    $series = $series ?? [];
    $values = array_column($series, 'value');
    $labels = array_column($series, 'label');
    $max = max(1, ...array_map('floatval', $values ?: [1]));
    $width = 100;
    $height = 48;
    $count = max(count($values), 2);
    $barWidth = $width / $count;
@endphp

<div class="cp-dashboard-line-chart" x-data="{ hover: null }">
    <svg viewBox="0 0 {{ $width }} {{ $height }}" class="cp-dashboard-line-chart__svg" preserveAspectRatio="none">
        @foreach ($values as $index => $value)
            @php
                $barHeight = ($value / $max) * ($height - 8);
                $x = $index * $barWidth + ($barWidth * 0.15);
                $w = $barWidth * 0.7;
                $y = $height - $barHeight - 4;
            @endphp
            <rect
                x="{{ $x }}"
                y="{{ $y }}"
                width="{{ $w }}"
                height="{{ max(0.5, $barHeight) }}"
                rx="1"
                class="cp-dashboard-line-chart__bar"
                @mouseenter="hover = {{ $index }}"
                @mouseleave="hover = null"
            />
        @endforeach
    </svg>
    <div class="cp-dashboard-line-chart__labels">
        @foreach ($labels as $index => $label)
            @if ($index === 0 || $index === count($labels) - 1 || $index % max(1, intdiv(count($labels), 4)) === 0)
                <span>{{ $label }}</span>
            @endif
        @endforeach
    </div>
</div>
