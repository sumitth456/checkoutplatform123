<?php

declare(strict_types=1);

$base = realpath(__DIR__.'/../app/Filament');

if ($base === false) {
    echo "Filament path not found\n";
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
);

$fixed = 0;

foreach ($iterator as $file) {
    if (! $file->isFile() || $file->getExtension() !== 'php' || $file->getSize() > 8) {
        continue;
    }

    $path = $file->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen(dirname(__DIR__)) + 1));
    // app/Filament/...

    if (! preg_match('#^app/Filament/(.+)$#', $relative, $m)) {
        continue;
    }

    $sub = $m[1];
    $class = basename($sub, '.php');
    $namespace = 'App\\Filament\\'.str_replace('/', '\\', dirname($sub));

    $content = stubFor($namespace, $class, $path);

    if ($content === null) {
        echo "SKIP: {$namespace}\\{$class}\n";
        continue;
    }

    file_put_contents($path, $content);
    echo "FIXED: {$namespace}\\{$class}\n";
    $fixed++;
}

echo "Done. Fixed {$fixed} files.\n";

function stubFor(string $namespace, string $class, string $path): ?string
{
    if (str_contains($path, DIRECTORY_SEPARATOR.'Pages'.DIRECTORY_SEPARATOR) || str_contains($path, '/Pages/')) {
        return pageStub($namespace, $class);
    }

    if ((str_contains($path, '/Schemas/') || str_contains($path, '\\Schemas\\')) && str_ends_with($class, 'Form')) {
        return formStub($namespace, $class);
    }

    if ((str_contains($path, '/Schemas/') || str_contains($path, '\\Schemas\\')) && str_ends_with($class, 'Infolist')) {
        return infolistStub($namespace, $class);
    }

    if ((str_contains($path, '/Tables/') || str_contains($path, '\\Tables\\')) && str_ends_with($class, 'Table')) {
        return tableStub($namespace, $class);
    }

    if (str_contains($path, '/Exports/') || str_contains($path, '\\Exports\\')) {
        return exportStub($namespace, $class);
    }

    if (str_contains($path, '/RelationManagers/') || str_contains($path, '\\RelationManagers\\')) {
        return relationManagerStub($namespace, $class);
    }

    return null;
}

function pageStub(string $namespace, string $class): ?string
{
    $resource = guessResourceClass($namespace);
    if ($resource === null) {
        return null;
    }

    $short = basename(str_replace('\\', '/', $resource));

    if (str_starts_with($class, 'List')) {
        return <<<PHP
<?php

namespace {$namespace};

use {$resource};
use Filament\Resources\Pages\ListRecords;

class {$class} extends ListRecords
{
    protected static string \$resource = {$short}::class;
}

PHP;
    }

    if (str_starts_with($class, 'Create')) {
        return <<<PHP
<?php

namespace {$namespace};

use {$resource};
use Filament\Resources\Pages\CreateRecord;

class {$class} extends CreateRecord
{
    protected static string \$resource = {$short}::class;
}

PHP;
    }

    if (str_starts_with($class, 'Edit')) {
        return <<<PHP
<?php

namespace {$namespace};

use {$resource};
use Filament\Resources\Pages\EditRecord;

class {$class} extends EditRecord
{
    protected static string \$resource = {$short}::class;
}

PHP;
    }

    if (str_starts_with($class, 'View')) {
        return <<<PHP
<?php

namespace {$namespace};

use {$resource};
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class {$class} extends ViewRecord
{
    protected static string \$resource = {$short}::class;

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}

PHP;
    }

    return null;
}

function guessResourceClass(string $pageNamespace): ?string
{
    if (! preg_match('#^(App\\\\Filament\\\\Admin\\\\Resources\\\\[^\\\\]+)\\\\Pages$#', $pageNamespace, $m)) {
        return null;
    }

    $resourceNs = $m[1];
    $segment = basename(str_replace('\\', '/', $resourceNs));

    $candidates = [
        $segment.'Resource',
        rtrim($segment, 's').'Resource',
        match ($segment) {
            'Customers' => 'CustomerResource',
            'Permissions' => 'PermissionResource',
            'PlatformSettings' => 'PlatformSettingResource',
            'WarehouseSettings' => 'WarehouseSettingResource',
            'WarehouseShipments' => 'WarehouseShipmentResource',
            'RebillQueue' => 'RebillQueueResource',
            'ProductImportBatches' => 'ProductImportBatchResource',
            'LandingPageForms' => 'LandingPageFormResource',
            'DunningPolicies' => 'DunningPolicyResource',
            'RetryPolicies' => 'RetryPolicyResource',
            'PaymentGateways' => 'PaymentGatewayResource',
            'StripeWebhookEvents' => 'StripeWebhookEventResource',
            default => null,
        },
    ];

    foreach (array_filter($candidates) as $name) {
        $fqcn = "{$resourceNs}\\{$name}";
        if (class_exists($fqcn)) {
            return $fqcn;
        }
    }

    return "{$resourceNs}\\{$segment}Resource";
}

function formStub(string $namespace, string $class): string
{
    return <<<PHP
<?php

namespace {$namespace};

use Filament\Schemas\Schema;

class {$class}
{
    public static function configure(Schema \$schema): Schema
    {
        return \$schema->components([]);
    }
}

PHP;
}

function infolistStub(string $namespace, string $class): string
{
    return formStub($namespace, $class);
}

function tableStub(string $namespace, string $class): string
{
    return <<<PHP
<?php

namespace {$namespace};

use Filament\Tables\Table;

class {$class}
{
    public static function configure(Table \$table): Table
    {
        return \$table->columns([]);
    }
}

PHP;
}

function exportStub(string $namespace, string $class): string
{
    return <<<PHP
<?php

namespace {$namespace};

use Filament\Actions\Exports\Exporter;

class {$class} extends Exporter
{
    public static function getColumns(): array
    {
        return [];
    }
}

PHP;
}

function relationManagerStub(string $namespace, string $class): string
{
    return <<<PHP
<?php

namespace {$namespace};

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class {$class} extends RelationManager
{
    protected static string \$relationship = 'adminActivityLogs';

    public function table(Table \$table): Table
    {
        return \$table->columns([]);
    }
}

PHP;
}
