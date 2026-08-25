<?php

namespace App\Modules\Product;

use App\Modules\Product\Contracts\ProductRepositoryInterface;
use App\Modules\Product\Import\Services\FunnelProductBulkService;
use App\Modules\Product\Import\Services\ProductCatalogExportService;
use App\Modules\Product\Import\Services\ProductImportBatchService;
use App\Modules\Product\Import\Services\ProductImportColumnMapper;
use App\Modules\Product\Import\Services\ProductImportExecutionService;
use App\Modules\Product\Import\Services\ProductImportSpreadsheetReader;
use App\Modules\Product\Import\Services\ProductImportValidationService;
use App\Modules\Product\Repositories\ProductRepository;
use Illuminate\Support\ServiceProvider;

class ProductServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);

        $this->app->singleton(ProductImportSpreadsheetReader::class);
        $this->app->singleton(ProductImportColumnMapper::class);
        $this->app->singleton(ProductImportValidationService::class);
        $this->app->singleton(ProductImportExecutionService::class);
        $this->app->singleton(ProductImportBatchService::class);
        $this->app->singleton(ProductCatalogExportService::class);
        $this->app->singleton(FunnelProductBulkService::class);
        $this->app->singleton(\App\Modules\Product\BillingCycles\ProductBillingCycleService::class);
        $this->app->singleton(\App\Modules\Product\BillingCycles\BillingCycleExportImportService::class);
    }
}
