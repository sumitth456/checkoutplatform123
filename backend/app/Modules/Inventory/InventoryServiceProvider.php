<?php

namespace App\Modules\Inventory;

use App\Modules\Inventory\Contracts\InventoryRepositoryInterface;
use App\Modules\Inventory\Repositories\InventoryRepository;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(InventoryRepositoryInterface::class, InventoryRepository::class);
    }
}
