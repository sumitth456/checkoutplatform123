<?php

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$modelDir = __DIR__.'/../app/Models';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($modelDir));
$missing = [];

foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php') {
        continue;
    }

    $source = file_get_contents($file->getPathname());
    if (! preg_match('/#\[UsePolicy\(([^)]+)::class\)\]/', $source, $match)) {
        continue;
    }

    $policyClass = trim($match[1]);
    if (! class_exists($policyClass)) {
        $missing[] = $policyClass.' (from '.basename($file->getPathname()).')';
    }
}

if ($missing === []) {
    echo "All UsePolicy classes exist.\n";
    exit(0);
}

echo "Missing policy classes:\n";
foreach ($missing as $line) {
    echo "  - {$line}\n";
}
exit(1);
