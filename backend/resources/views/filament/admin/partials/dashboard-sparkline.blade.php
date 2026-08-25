@php
    $points = array_values(array_map('floatval', $points ?? []));
    if ($points === []) {
        $points = [0];
    }
    $max = max(1, ...$points);
    $width = 120;
    $height = 32;
    $count = max(count($points), 2);
    $step = $width / ($count - 1);
    $path = collect($points)->values()->map(function ($value, $index) use ($max, $height, $step): string {
        $x = round($index * $step, 2);
        $y = round($height - (($value / $max) * ($height - 4)) - 2, 2);

        return ($index === 0 ? 'M' : 'L') . "{$x},{$y}";
    })->implode(' ');
@endphp

<svg class="cp-dashboard-sparkline" viewBox="0 0 {{ $width }} {{ $height }}" preserveAspectRatio="none" aria-hidden="true">
    <path d="{{ $path }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
</svg>
