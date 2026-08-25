<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

auth()->login(App\Models\User::query()->first());

$panel = Filament\Facades\Filament::getPanel('admin');
Filament\Facades\Filament::setCurrentPanel($panel);
$panel->navigationManager()->mountNavigation();

$groups = $panel->getNavigation();
$items = [];

foreach ($groups as $group) {
    $groupLabel = $group->getLabel() ?? '(Dashboard)';

    foreach ($group->getItems() as $item) {
        $collect = function ($navItem, string $prefix = '') use (&$collect, &$items, $groupLabel): void {
            $label = $prefix.$navItem->getLabel();
            $url = $navItem->getUrl();
            $items[] = [
                'group' => $groupLabel,
                'label' => $label,
                'url' => is_callable($url) ? 'callable' : (string) $url,
            ];

            foreach ($navItem->getChildItems() as $child) {
                $collect($child, $label.' > ');
            }
        };

        $collect($item);
    }
}

echo 'total_nav_items='.count($items).PHP_EOL;

$groupCounts = [];
foreach ($items as $item) {
    $groupCounts[$item['group']] = ($groupCounts[$item['group']] ?? 0) + 1;
}

foreach ($groupCounts as $group => $count) {
    echo "{$group}: {$count}\n";
}

$requiredGroups = ['CRM', 'Customers', 'Fulfillment', 'Gateways', 'Admin'];
foreach ($requiredGroups as $required) {
    echo in_array($required, array_keys($groupCounts), true) ? "PASS group {$required}\n" : "FAIL missing group {$required}\n";
}

$requiredLabels = ['Products', 'Campaigns', 'Affiliates', 'Call Center', 'Customers', 'Orders', 'Landing Pages', 'Payment Gateways'];
foreach ($requiredLabels as $label) {
    $found = collect($items)->contains(fn (array $i): bool => str_contains($i['label'], $label));
    echo ($found ? 'PASS' : 'FAIL')." label {$label}\n";
}
