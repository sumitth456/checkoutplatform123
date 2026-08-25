@include('filament.admin.pages.operational-dashboard', [
    'metrics' => $metrics ?? [],
    'currency' => $currency ?? 'USD',
])
