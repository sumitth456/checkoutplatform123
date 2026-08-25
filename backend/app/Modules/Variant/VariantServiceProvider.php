<?php

namespace App\Modules\Variant;

use App\Modules\Variant\Contracts\VariantRepositoryInterface;
use App\Modules\Variant\Repositories\VariantRepository;
use Illuminate\Support\ServiceProvider;

class VariantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(VariantRepositoryInterface::class, VariantRepository::class);
    }
}
