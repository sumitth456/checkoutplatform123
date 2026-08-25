<?php

declare(strict_types=1);

/**
 * Static audit of Filament admin navigation registration (no database required).
 */
$root = dirname(__DIR__);
$adminPath = $root.'/app/Filament/Admin';

$files = [];
foreach ([
    $adminPath.'/Resources',
    $adminPath.'/Pages',
] as $scanRoot) {
    if (! is_dir($scanRoot)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanRoot, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isFile() && str_ends_with($fileInfo->getFilename(), '.php')) {
            $files[] = $fileInfo->getPathname();
        }
    }
}

$items = [];

foreach ($files as $file) {
    $contents = file_get_contents($file);
    if ($contents === false) {
        continue;
    }

    if (! preg_match('/class\s+(\w+)/', $contents, $classMatch)) {
        continue;
    }

    if (! str_contains($contents, 'navigationGroup') && ! str_contains($contents, 'navigationLabel')) {
        continue;
    }

    $hidden = preg_match('/shouldRegisterNavigation\(\)[^{]*\{[^}]*return\s+false/s', $contents) === 1
        || preg_match('/protected static bool \$shouldRegisterNavigation\s*=\s*false\s*;/', $contents) === 1;

    preg_match('/navigationLabel\s*=\s*[\'"]([^\'"]+)[\'"]/', $contents, $labelMatch);
    preg_match('/navigationGroup\s*=\s*(?:AdminNavigationGroup::(\w+)|[\'"]([^\'"]+)[\'"])/', $contents, $groupMatch);
    preg_match('/navigationParentItem\s*=\s*(?:AdminNavigationParent::(\w+)|[\'"]([^\'"]+)[\'"])/', $contents, $parentMatch);
    preg_match('/protected static \?string \$slug = [\'"]([^\'"]+)[\'"]/', $contents, $slugMatch);

    $group = $groupMatch[1] ?? ($groupMatch[2] ?? '(none)');
    if ($group === 'CRM') {
        $group = 'CRM';
    } elseif ($group === 'CUSTOMERS') {
        $group = 'Customers';
    } elseif ($group === 'FULFILLMENT') {
        $group = 'Fulfillment';
    } elseif ($group === 'GATEWAYS') {
        $group = 'Gateways';
    } elseif ($group === 'ADMIN') {
        $group = 'Admin';
    }

    $parentConst = $parentMatch[1] ?? null;
    $parent = $parentMatch[2] ?? null;
    if ($parentConst !== null) {
        $parent = match ($parentConst) {
            'PRODUCTS' => 'Products',
            'CAMPAIGNS' => 'Campaigns',
            'AFFILIATES' => 'Affiliates',
            'CALL_CENTER' => 'Call Center',
            'LANDING_PAGES' => 'Landing Pages',
            'USERS' => 'Users',
            default => $parentConst,
        };
    }

    if ($hidden) {
        continue;
    }

    $items[] = [
        'class' => $classMatch[1],
        'file' => str_replace($root.DIRECTORY_SEPARATOR, '', $file),
        'label' => $labelMatch[1] ?? $classMatch[1],
        'group' => $group,
        'parent' => $parent,
        'slug' => $slugMatch[1] ?? null,
    ];
}

usort($items, fn (array $a, array $b): int => [$a['group'], $a['parent'] ?? '', $a['label']] <=> [$b['group'], $b['parent'] ?? '', $b['label']]);

echo 'Registered navigation classes: '.count($items).PHP_EOL.PHP_EOL;

$groupCounts = [];
foreach ($items as $item) {
    $groupCounts[$item['group']] = ($groupCounts[$item['group']] ?? 0) + 1;
}

foreach ($groupCounts as $group => $count) {
    echo str_pad($group, 14)." {$count}\n";
}

echo PHP_EOL.'--- Full inventory ---'.PHP_EOL;

foreach ($items as $item) {
    $path = $item['group'];
    if ($item['parent']) {
        $path .= ' > '.$item['parent'];
    }
    echo str_pad($path, 36)." | {$item['label']} ({$item['class']})\n";
}

$requiredGroups = ['CRM', 'Customers', 'Fulfillment', 'Gateways', 'Admin'];
echo PHP_EOL.'--- Checks ---'.PHP_EOL;
foreach ($requiredGroups as $required) {
    echo (isset($groupCounts[$required]) ? 'PASS' : 'FAIL')." group {$required}\n";
}

$requiredTopLevel = [
    'CRM' => ['Products', 'Campaigns', 'Affiliates', 'Call Center'],
    'Customers' => ['Customers', 'Orders', 'Fulfillment'],
    'Fulfillment' => ['Dashboard', 'Search Fulfillments', 'Fulfillment Houses', 'Warehouses', 'Shipping Carriers', 'Shipping Rules', 'Import Tracking', 'Fulfillment Batches'],
    'Gateways' => ['Payment Gateways', 'Gateway Routing', 'Transactions'],
    'Admin' => ['Analytics', 'Subscriptions', 'Funnels', 'Landing Pages', 'Users'],
];

foreach ($requiredTopLevel as $group => $labels) {
    foreach ($labels as $label) {
        $found = false;
        foreach ($items as $i) {
            if ($i['group'] === $group && $i['label'] === $label && $i['parent'] === null) {
                $found = true;
                break;
            }
        }
        echo ($found ? 'PASS' : 'FAIL')." {$group} top-level {$label}\n";
    }
}

$landingChildren = ['Templates', 'Assets', 'Forms', 'Published Pages', 'Analytics'];
foreach ($landingChildren as $child) {
    $found = false;
    foreach ($items as $i) {
        if ($i['parent'] === 'Landing Pages' && $i['label'] === $child) {
            $found = true;
            break;
        }
    }
    echo ($found ? 'PASS' : 'FAIL')." Admin > Landing Pages > {$child}\n";
}

$legacyGroups = ['Orders', 'Payments', 'Marketing', 'Catalog', 'Access', 'System', 'Subscriptions', 'Funnels', 'Analytics', 'Landing Pages'];
foreach ($legacyGroups as $legacy) {
    $found = false;
    foreach ($items as $i) {
        if ($i['group'] === $legacy) {
            $found = true;
            break;
        }
    }
    echo ($found ? 'FAIL legacy group still used' : 'PASS no legacy')." {$legacy}\n";
}
