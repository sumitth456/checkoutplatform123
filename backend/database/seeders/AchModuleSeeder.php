<?php

namespace Database\Seeders;

use App\Models\AchProvider;
use App\Modules\Ach\Enums\AchProviderStatus;
use App\Modules\Ach\Enums\AchProviderType;
use Illuminate\Database\Seeder;

class AchModuleSeeder extends Seeder
{
    public function run(): void
    {
        AchProvider::query()->updateOrCreate(
            ['slug' => 'sandbox-ach'],
            [
                'name' => 'Sandbox ACH',
                'provider' => AchProviderType::Sandbox->value,
                'status' => AchProviderStatus::Enabled,
                'sandbox_mode' => true,
                'is_default' => true,
                'priority' => 1,
            ],
        );
    }
}
